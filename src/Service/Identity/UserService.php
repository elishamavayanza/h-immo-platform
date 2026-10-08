<?php

declare(strict_types=1);

namespace App\Service\Identity;

use App\Dto\Feedback;
use App\Dto\Request\Identity\UserRequest;
use App\Dto\Request\Identity\UserSuspendRequest;
use App\Dto\Request\PaginationQuery;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Mapper\Identity\UserMapper;
use App\Repository\Identity\OrganizationUserRepository;
use App\Repository\Identity\UserRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use App\Service\System\DateTimeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * UserService
 *
 * Package : Identity & Access — Service Métier
 *
 * Traite la logique métier, la persistance, le hachage des mots de passe
 * et la gestion du cycle de vie des utilisateurs enveloppée dans des Feedback.
 */
final readonly class UserService
{
    /**
     * Initialise les dépendances requises pour la gestion du domaine utilisateur.
     * Injecte l'EntityManager, le repository, le mapper et le validateur.
     */
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $repository,
        private UserMapper $mapper,
        private ValidatorInterface $validator,
        private SecurityServiceInterface $security,
        private OrganizationUserRepository $orgUserRepository,
        private AuditLogService $auditLogService,
        private UserNotificationService $notificationService,
        private DateTimeService $dateTimeService
    ) {
    }

    /**
     * Récupère la liste paginée et filtrée des utilisateurs enregistrés.
     * Renvoie les résultats mappés en DTO dans une enveloppe Feedback HTTP 200.
     */
    public function list(PaginationQuery $query): Feedback
    {
        $feedback = new Feedback();

        // Sans restriction, cette liste exposerait les comptes de tous les
        // tenants de la plateforme. Un utilisateur ne voit que les comptes
        // d'une Organization dont il est membre ; seul le SUPER_ADMIN peut
        // inventorier l'ensemble des comptes.
        $paginatedResult = $this->security->isSuperAdmin()
            ? $this->repository->findPaginatedAll($query->page, $query->limit, $query->search)
            : $this->repository->findPaginatedByOrganizations(
                $this->security->getCurrentUserOrganizations(),
                $query->page,
                $query->limit,
                $query->search
            );

        // Rattachements des comptes de la page en une seule requête : la table
        // affiche organisation + rôle par ligne, et le filtre par organisation
        // ne doit pas reposer sur un chargement paresseux N+1.
        $memberships = $this->membershipsByUser($paginatedResult['items']);

        $data = [
            'items' => array_map(function (User $user) use ($memberships) {
                return $this->mapper->toResponse($user, $memberships[(string) $user->getUuid()] ?? []);
            }, $paginatedResult['items']),
            'total' => $paginatedResult['total'],
            'page' => $query->page,
            'limit' => $query->limit,
        ];

        return $feedback
            ->setData($data)
            ->setFlushDescription('Liste des utilisateurs récupérée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Obtient le détail d'un utilisateur par son identifiant unique UUID.
     * Retourne une réponse Feedback en erreur HTTP 404 si l'utilisateur n'existe pas.
     */
    public function getByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();
        $user = $this->repository->findOneBy(['uuid' => $uuid]);

        if (!$user) {
            return $feedback
                ->addError('uuid', 'L\'utilisateur demandé n\'existe pas.')
                ->setErrorFlushDescription('Utilisateur introuvable.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $this->security->checkUserAccess($user, SecurityAction::VIEW_USER);

        return $feedback
            ->setData($this->mapper->toResponse($user, $this->membershipsFor($user)))
            ->setFlushDescription('Détails de l\'utilisateur récupérés.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Crée un nouveau compte utilisateur après vérification de l'unicité de l'email.
     * Hache le mot de passe, persiste l'entité et retourne un Feedback HTTP 201.
     */
    public function create(UserRequest $request): Feedback
    {
        $feedback = new Feedback();

        // Créer un compte est une opération d'administration de plateforme.
        $this->security->requirePlatformRole();
        $violations = $this->validator->validate($request, groups: ['create']);

        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Données utilisateur invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        if ($this->repository->findOneBy(['email' => $request->email])) {
            return $feedback
                ->addError('email', 'Cette adresse e-mail est déjà utilisée.')
                ->setErrorFlushDescription('Conflit sur l\'adresse e-mail.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $user = $this->mapper->copyToEntity($request, new User());
        // `isActive` est nullable dans le DTO (un PUT ne doit pas réactiver un
        // compte suspendu) ; une création, elle, part toujours d'un compte actif
        // sauf demande explicite de la part d'un SUPER_ADMIN.
        $user->setIsActive($request->isActive ?? true);
        $this->em->persist($user);
        $this->em->flush();

        return $feedback
            ->setData($this->mapper->toResponse($user))
            ->setFlushDescription('Compte utilisateur créé avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    /**
     * Met à jour les informations d'un utilisateur identifié par son UUID.
     * Effectue la validation du payload et la mise à jour sélective des champs.
     */
    public function update(string $uuid, UserRequest $request): Feedback
    {
        $feedback = new Feedback();
        $user = $this->repository->findOneBy(['uuid' => $uuid]);

        if (!$user) {
            return $feedback
                ->addError('uuid', 'Utilisateur introuvable.')
                ->setErrorFlushDescription('Mise à jour impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $this->security->checkUserAccess($user, SecurityAction::UPDATE_USER);

        // Un droit de rôle ne se délègue pas par un PUT sur une fiche.
        // `checkUserAccess()` autorise explicitement l'auto-service, il ne peut
        // donc pas servir de garde-fou sur un champ qui *accorde* des droits :
        // sans ce filtre, `{"platformRole": "super_admin"}` sur sa propre fiche
        // suffirait à devenir administrateur de la plateforme.
        if ($request->platformRole !== null) {
            $this->security->requirePlatformRole();
        }

        // Même raison pour l'activation : un PATRON peut suspendre un compte de
        // sa société, mais un membre ne doit ni réactiver le sien (contournement
        // d'une suspension) ni en suspendre un autre. La matrice de rôle refuse
        // déjà ces deux actions aux ADMIN_IMMOBILIER et ADMIN_VILLE.
        if ($request->isActive !== null) {
            $this->security->checkUserAccess(
                $user,
                $request->isActive
                    ? SecurityAction::ACTIVATE_USER
                    : SecurityAction::SUSPEND_USER
            );
        }

        $violations = $this->validator->validate($request, groups: ['update']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Échec de la validation des données.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        if ($request->email !== null && $request->email !== $user->getEmail()) {
            if ($this->repository->findOneBy(['email' => $request->email])) {
                return $feedback
                    ->addError('email', 'Cette adresse e-mail est déjà attribuée.')
                    ->setErrorFlushDescription('Adresse e-mail indisponible.')
                    ->setStatus(422)
                    ->autoInitFlush();
            }
        }

        $this->mapper->copyToEntity($request, $user);
        $this->em->flush();

        return $feedback
            ->setData($this->mapper->toResponse($user, $this->membershipsFor($user)))
            ->setFlushDescription('Utilisateur mis à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Effectue une suppression logique (Soft Delete) du compte utilisateur.
     * Desactive le compte et enregistre la date de suppression sans destruction physique.
     */
    public function delete(string $uuid): Feedback
    {
        $feedback = new Feedback();
        $user = $this->repository->findOneBy(['uuid' => $uuid]);

        if (!$user) {
            return $feedback
                ->addError('uuid', 'Utilisateur introuvable.')
                ->setErrorFlushDescription('Suppression impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $this->security->checkUserAccess($user, SecurityAction::DELETE_USER);

        $user->softDelete();
        $user->setIsActive(false);
        $this->em->flush();

        return $feedback
            ->setFlushDescription('L\'utilisateur a été supprimé avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Suspend un compte utilisateur, puis notifie l'intéressé par email.
     *
     * Opération d'administration (SUPER_ADMIN sur toute la plateforme, PATRON
     * pour un compte de son organisation). Contraintes :
     *   1. seul un compte actif peut être suspendu ;
     *   2. on ne peut pas suspendre son propre compte (un SUPER_ADMIN qui se
     *      suspendrait se couperait lui-même l'accès) ;
     *   3. la désactivation est tracée dans l'audit, avec le motif le cas échéant.
     *
     * L'envoi de l'email est non bloquant : la suspension est déjà désactivée
     * en base quand le mailer échoue, et l'échec remonte sous forme de
     * `warning`, pas de 500 (verdict « persistance » et verdict
     * « notification » séparés).
     */
    public function suspend(string $uuid, UserSuspendRequest $request): Feedback
    {
        $feedback = new Feedback();

        $user = $this->repository->findOneBy(['uuid' => $uuid]);
        if (!$user) {
            return $feedback
                ->addError('uuid', 'Utilisateur introuvable.')
                ->setErrorFlushDescription('Impossible de suspendre le compte.')
                ->autoInitFlush()
                ->setStatus(404);
        }

        $this->security->checkUserAccess($user, SecurityAction::SUSPEND_USER);

        // La matrice autorise le cas « soi-même » pour `UPDATE_USER` uniquement,
        // jamais pour `SUSPEND_USER` : une auto-suspension de plateforme est un
        // verrouillage volontaire (et nuisible) demandé explicitement ici.
        if ($user->getId() === $this->security->getCurrentUser()->getId()) {
            return $feedback
                ->addError('user', 'Impossible de suspendre votre propre compte.')
                ->setErrorFlushDescription('Suspension impossible : vous ne pouvez pas suspendre votre propre compte.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $violations = $this->validator->validate($request);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Échec de la validation des données.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        if (!$user->isActive()) {
            return $feedback
                ->addError('isActive', 'Ce compte est déjà désactivé.')
                ->setErrorFlushDescription('Suspension impossible : le compte est déjà désactivé.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $reason = trim((string) $request->reason);

        $previous = ['isActive' => true];
        $user->setIsActive(false);
        $user->setUpdatedAt($this->dateTimeService->now());
        $this->em->flush();

        $this->auditLogService->log(
            action: 'SUSPEND_USER',
            entityType: User::class,
            entityId: $user->getId(),
            user: $this->security->getCurrentUser(),
            oldValues: $previous,
            newValues: $reason === ''
                ? ['isActive' => false]
                : ['isActive' => false, 'reason' => $reason],
        );

        // Notifie l'utilisateur APRÈS le commit : l'email ne doit jamais
        // conditionner la persistance de la suspension.
        $failed = $this->notificationService->notifyAccountSuspended($user, $reason === '' ? null : $reason);

        if ($failed !== []) {
            $feedback->addWarning('emails', count($failed) . ' email(s) de notification n\'ont pas pu être envoyé(s).');
        }

        return $feedback
            ->setData($this->mapper->toResponse($user, $this->membershipsFor($user)))
            ->setFlushDescription(
                $failed === []
                    ? 'Le compte a été suspendu. L\'utilisateur a été notifié par email.'
                    : 'Le compte a été suspendu, mais l\'email de notification n\'a pas pu être envoyé.'
            )
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Rattachements d'un ensemble de comptes, groupés par UUID public du User.
     *
     * @param list<User> $users
     *
     * @return array<string, list<array{organizationId: string, organizationName: string, role: string}>>
     */
    private function membershipsByUser(array $users): array
    {
        $grouped = [];

        foreach ($this->orgUserRepository->findByUsers($users) as $membership) {
            $grouped[(string) $membership->getUser()->getUuid()][] = [
                'organizationId' => (string) $membership->getOrganization()->getUuid(),
                'organizationName' => $membership->getOrganization()->getName(),
                'role' => $membership->getRole()->value,
            ];
        }

        return $grouped;
    }

    /**
     * Rattachements d'un compte précis (« le compte est un cas particulier
     * de la liste » : mêmes données de sortie sur show et sur list).
     *
     * @return list<array{organizationId: string, organizationName: string, role: string}>
     */
    private function membershipsFor(User $user): array
    {
        return $this->membershipsByUser([$user])[(string) $user->getUuid()] ?? [];
    }
}
