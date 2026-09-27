<?php

declare(strict_types=1);

namespace App\Service\Identity;

use App\Dto\Feedback;
use App\Dto\Request\Identity\OrganizationRequest;
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
use App\Service\Identity\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
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
        private PasswordResetService $passwordResetService
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

        $organization = $this->mapper->copyToEntity($request, new Organization());
        $this->em->persist($organization);
        $this->em->flush();

        // Créer l'utilisateur PATRON
        $patron = new User();
        $patron->setEmail($request->patronEmail);
        $patron->setFullName($request->patronFullName);
        $patron->setPhone($request->patronPhone);
        $patron->setIsActive(true);
        // Le mot de passe sera défini via le flux "mot de passe oublié"
        $patron->setPassword(''); // Sera mis à jour via reset-password
        $this->em->persist($patron);
        $this->em->flush();

        // Créer le lien OrganizationUser avec rôle PATRON
        $orgUser = new OrganizationUser();
        $orgUser->setOrganization($organization);
        $orgUser->setUser($patron);
        $orgUser->setRole(OrganizationRole::PATRON);
        $this->em->persist($orgUser);
        $this->em->flush();

        // Déclencher l'envoi de l'email de réinitialisation de mot de passe
        // (le PATRON n'a pas de mot de passe, il doit en définir un via le lien)
        $this->passwordResetService->requestReset($request->patronEmail);

        return $feedback
            ->setData($this->mapper->toResponse($organization))
            ->setFlushDescription('L\'organisation et son PATRON ont été créés. Un email de configuration du mot de passe a été envoyé au PATRON.')
            ->setStatus(201)
            ->autoInitFlush();
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
