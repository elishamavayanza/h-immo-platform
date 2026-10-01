<?php

declare(strict_types=1);

namespace App\Service\Identity;

use App\Dto\Feedback;
use App\Dto\Request\Identity\OrganizationRequest;
use App\Dto\Request\Identity\OrganizationShowcaseRequest;
use App\Dto\Request\PaginationQuery;
use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Enum\OrganizationRole;
use App\Mapper\Identity\OrganizationMapper;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Identity\OrganizationUserRepository;
use App\Repository\Identity\UserRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use App\Service\System\DateTimeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * OrganizationService
 *
 * Package : Identity & Access — Service Métier
 *
 * Gère la logique métier, la persistance, la validation et la gestion du cycle
 * de vie des entreprises clientes (Multi-tenant) enveloppées dans des Feedback.
 */
final readonly class OrganizationService
{
    /**
     * Initialise les dépendances nécessaires pour la gestion des organisations.
     * Injecte l'EntityManager, les repositories, le mapper et le système de validation.
     */
    public function __construct(
        private EntityManagerInterface $em,
        private OrganizationRepository $repository,
        private UserRepository $userRepository,
        private OrganizationUserRepository $orgUserRepository,
        private OrganizationMapper $mapper,
        private ValidatorInterface $validator,
        private SecurityServiceInterface $security,
        private PasswordResetService $passwordResetService,
        private AuditLogService $auditLogService,
        private DateTimeService $dateTimeService
    ) {
    }

    /**
     * Récupère la liste paginée et filtrée des organisations en base de données.
     * Traite les paramètres de tri/recherche et encapsule les résultats dans un Feedback.
     */
    public function list(PaginationQuery $query): Feedback
    {
        $feedback = new Feedback();

        // L'Organization est la frontière du multi-tenant : lister sans
        // filtre livrerait à n'importe quel utilisateur authentifié la
        // liste des entreprises clientes de la plateforme. On restreint donc
        // aux Organizations dont l'appelant est membre, sauf pour le
        // SUPER_ADMIN qui administre la plateforme.
        $paginatedResult = $this->security->isSuperAdmin()
            ? $this->repository->findPaginated($query->page, $query->limit, $query->search)
            : $this->repository->findPaginatedByUuids(
                array_map(
                    static fn (Organization $organization): string => $organization->getUuid()->toRfc4122(),
                    $this->security->getCurrentUserOrganizations()
                ),
                $query->page,
                $query->limit,
                $query->search
            );

        $data = [
            'items' => array_map([$this->mapper, 'toResponse'], $paginatedResult['items']),
            'total' => $paginatedResult['total'],
            'page' => $query->page,
            'limit' => $query->limit,
        ];

        return $feedback
            ->setData($data)
            ->setFlushDescription('Liste des organisations récupérée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Recherche et retourne le détail d'une organisation à partir de son UUID public.
     * Renvoie un Feedback en erreur HTTP 404 si l'organisation demandée n'existe pas.
     */
    public function getByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();
        $organization = $this->repository->findOneBy(['uuid' => $uuid]);

        if (!$organization) {
            return $feedback
                ->addError('uuid', 'L\'organisation spécifiée n\'existe pas.')
                ->setErrorFlushDescription('Organisation introuvable.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $this->security->checkOrganizationAccess($organization, SecurityAction::VIEW_ORGANIZATION);

        return $feedback
            ->setData($this->mapper->toResponse($organization))
            ->setFlushDescription('Organisation trouvée.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Crée une nouvelle organisation après validation des contraintes d'unicité et de format.
     * Crée aussi le compte PATRON (utilisateur responsable) et le rattache à l'organisation
     * avec le rôle PATRON. Envoie un email de réinitialisation de mot de passe au PATRON.
     * Persiste l'entité en base et retourne le DTO de réponse dans le Feedback.
     */
    public function create(OrganizationRequest $request): Feedback
    {
        $feedback = new Feedback();

        // Créer une Organization, c'est créer un tenant : seuls les
        // comptes de plateforme en ont le droit.
        $this->security->requirePlatformRole();
        $violations = $this->validator->validate($request, groups: ['create']);

        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Données d\'organisation invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        if ($this->repository->findOneBy(['code' => $request->code])) {
            return $feedback
                ->addError('code', 'Ce code d\'organisation est déjà utilisé.')
                ->setErrorFlushDescription('Le code d\'organisation doit être unique.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        // Vérifier les champs PATRON obligatoires
        if (!$request->patronEmail || !$request->patronFullName || !$request->patronPhone) {
            return $feedback
                ->addError('patron', 'Les informations du PATRON (email, nom, téléphone) sont obligatoires.')
                ->setErrorFlushDescription('Données du PATRON incomplètes.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        // Vérifier unicité de l'email du PATRON
        if ($this->userRepository->findOneBy(['email' => $request->patronEmail])) {
            return $feedback
                ->addError('patronEmail', 'Cette adresse email est déjà utilisée par un autre utilisateur.')
                ->setErrorFlushDescription('Conflit sur l\'email du PATRON.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $organization = null;
        $patron = null;
        $orgUser = null;

        // Transaction unique : le tenant, son PATRON et le rôle forment un
        // tout. Trois flushs intermédiaires laissaient un tenant sans PATRON
        // (donc inaccessible) si l'étape suivante échouait.
        $this->em->wrapInTransaction(function () use ($request, &$organization, &$patron, &$orgUser): void {
            $organization = $this->mapper->copyToEntity($request, new Organization());
            $this->em->persist($organization);

            $patron = new User();
            $patron->setEmail($request->patronEmail);
            $patron->setFullName($request->patronFullName);
            $patron->setPhone($request->patronPhone);
            $patron->setIsActive(true);
            // Le mot de passe sera défini via le flux "mot de passe oublié"
            $patron->setPassword(''); // Sera mis à jour via reset-password
            $this->em->persist($patron);

            $orgUser = new OrganizationUser();
            $orgUser->setOrganization($organization);
            $orgUser->setUser($patron);
            $orgUser->setRole(OrganizationRole::PATRON);
            $this->em->persist($orgUser);

            $this->em->flush();
        });

        // L'envoi a lieu après le commit : une panne SMTP ne doit pas
        // faire échouer la création d'un tenant, ni maintenir une
        // transaction ouverte pendant l'échange réseau.
        $emailSent = $this->passwordResetService->requestResetForNewUser($request->patronEmail);

        $this->auditLogService->log(
            action: 'CREATE_ORGANIZATION',
            entityType: Organization::class,
            entityId: (int) $organization->getId(),
            organization: $organization,
            user: null,
            oldValues: null,
            newValues: [
                'name' => $request->name,
                'code' => $request->code,
                'patronEmail' => $request->patronEmail,
                'emailSent' => $emailSent,
            ]
        );

        $response = $feedback->setData($this->mapper->toResponse($organization));

        if (!$emailSent) {
            return $response
                ->setWarningFlushDescription(
                    'L\'organisation et son PATRON ont été créés, mais l\'email de configuration du mot de passe n\'a pas pu être envoyé. '
                    .'Le PATRON doit utiliser « mot de passe oublié » pour définir son accès.'
                )
                ->addWarning('patronEmail', 'Email de configuration non envoyé.')
                // `autoInitFlush()` doit précéder `setStatus()` : il écrase
                // le statut à 200/422 selon la présence d'erreurs.
                ->autoInitFlush()
                ->setStatus(201);
        }

        return $response
            ->setFlushDescription('L\'organisation et son PATRON ont été créés. Un email de configuration du mot de passe a été envoyé au PATRON.')
            ->autoInitFlush()
            ->setStatus(201);
    }

    /**
     * Met à jour une organisation existante identifiée par son UUID public.
     * Valide les modifications soumises avant d'enregistrer les changements en base.
     */
    public function update(string $uuid, OrganizationRequest $request): Feedback
    {
        $feedback = new Feedback();
        $organization = $this->repository->findOneBy(['uuid' => $uuid]);

        if (!$organization) {
            return $feedback
                ->addError('uuid', 'Organisation introuvable.')
                ->setErrorFlushDescription('Impossible d\'effectuer la mise à jour.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $this->security->checkOrganizationAccess($organization, SecurityAction::UPDATE);

        $violations = $this->validator->validate($request, groups: ['update']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Échec de la validation des données.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $this->mapper->copyToEntity($request, $organization);
        $this->em->flush();

        return $feedback
            ->setData($this->mapper->toResponse($organization))
            ->setFlushDescription('L\'organisation a été mise à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Met à jour la vitrine publique de l'Organization.
     *
     * Réservé au PATRON, via `MANAGE_ORGANIZATION` : la vitrine engage
     * l'image de l'entreprise et son URL publique, alors qu'un
     * `ADMIN_IMMOBILIER` ou un `ADMIN_VILLE` n'a mandat que sur son périmètre
     * immobilier ou son périmètre ville.
     *
     * Les trois champs sont facultatifs et appliqués seulement s'ils sont
     * présents : une omission ne doit pas effacer la présentation existante.
     * Pour vider volontairement la description, le client envoie `""`, qui
     * arrive ici comme une chaîne vide et non comme `null`.
     */
    public function updateShowcase(string $uuid, OrganizationShowcaseRequest $request): Feedback
    {
        $feedback = new Feedback();

        $organization = $this->repository->findOneByUuid(Uuid::fromString($uuid));

        if (!$organization) {
            return $feedback
                ->addError('uuid', 'Organisation introuvable.')
                ->setErrorFlushDescription('Impossible de mettre à jour la vitrine.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $this->security->checkOrganizationAccess($organization, SecurityAction::MANAGE_ORGANIZATION);

        $violations = $this->validator->validate($request);

        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Échec de la validation des données.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $previous = [
            'slug' => $organization->getSlug(),
            'isPubliclyListed' => $organization->isPubliclyListed(),
        ];

        if ($request->slug !== null) {
            // La page est identifiée par son slug : un slug déjà pris par une
            // autre entreprise rendrait deux vitrines ambiguës sur la même
            // URL, d'où le contrôle explicite plutôt qu'un `catch` sur la
            // violation UNIQUE, dont le message n'est pas traduisible.
            if ($this->repository->isSlugTakenByAnother($request->slug, $organization)) {
                return $feedback
                    ->addError('slug', 'Ce slug est déjà utilisé par une autre entreprise.')
                    ->setErrorFlushDescription('Conflit sur le slug.')
                    ->setStatus(409)
                    ->autoInitFlush();
            }

            $organization->setSlug($request->slug);
        }

        if ($request->publicDescription !== null) {
            $organization->setPublicDescription($request->publicDescription);
        }

        if ($request->isPubliclyListed !== null) {
            $organization->setIsPubliclyListed($request->isPubliclyListed);
        }

        $organization->setUpdatedAt($this->dateTimeService->now());
        $this->em->flush();

        $this->auditLogService->log(
            'SHOWCASE_UPDATED',
            Organization::class,
            $organization->getId(),
            $organization,
            $this->security->getCurrentUser(),
            $previous,
            [
                'slug' => $organization->getSlug(),
                'isPubliclyListed' => $organization->isPubliclyListed(),
            ]
        );

        return $feedback
            ->setData($this->mapper->toResponse($organization))
            ->setFlushDescription('La vitrine publique a été mise à jour.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Effectue une suppression logique (Soft Delete) de l'organisation ciblée.
     * Maintient l'intégrité référentielle en désactivant le tenant sans destruction physique.
     */
    public function delete(string $uuid): Feedback
    {
        $feedback = new Feedback();
        $organization = $this->repository->findOneBy(['uuid' => $uuid]);

        if (!$organization) {
            return $feedback
                ->addError('uuid', 'Organisation introuvable.')
                ->setErrorFlushDescription('Impossible de supprimer l\'organisation.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $this->security->checkOrganizationAccess($organization, SecurityAction::DELETE);

        $organization->softDelete();
        $this->em->flush();

        return $feedback
            ->setFlushDescription('L\'organisation a été supprimée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }
}
