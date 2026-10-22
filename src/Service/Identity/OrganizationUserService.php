<?php

declare(strict_types=1);

namespace App\Service\Identity;

use App\Dto\Feedback;
use App\Dto\Request\Identity\OrganizationUserRequest;
use App\Dto\Request\Identity\AssignExistingRoleRequest;
use App\Dto\Request\PaginationQuery;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Entity\Identity\UserCity;
use App\Enum\OrganizationRole;
use App\Mapper\Identity\OrganizationUserMapper;
use App\Entity\Property\City;
use App\Repository\Property\CityRepository;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Identity\OrganizationUserRepository;
use App\Repository\Identity\UserRepository;
use App\Repository\Identity\UserCityRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * OrganizationUserService
 *
 * Package : Identity & Access — Service Métier
 *
 * Gère l'affectation et la révocation des accès/rôles des utilisateurs
 * au sein des différentes organisations (Tenants) via Feedback.
 *
 * Permet au PATRON de créer les administrateurs déléguables et à
 * l'ADMIN_IMMOBILIER de créer un ADMIN_VILLE dans son organisation :
 * - Création de l'utilisateur (email, nom, téléphone)
 * - Rattachement à l'organisation avec le rôle approprié
 * - Pour ADMIN_VILLE : attribution des villes via UserCity
 * - Envoi d'email de configuration du mot de passe (flux forgot-password)
 */
final readonly class OrganizationUserService
{
    /**
     * Prépare le service en injectant les repositories d'accès utilisateur et tenant.
     * Fournit la logique d'assignation des privilèges et des rôles d'organisation.
     */
    public function __construct(
        private EntityManagerInterface $em,
        private OrganizationUserRepository $orgUserRepository,
        private OrganizationRepository $orgRepository,
        private UserRepository $userRepository,
        private CityRepository $cityRepository,
        private UserCityRepository $userCityRepository,
        private OrganizationUserMapper $mapper,
        private ValidatorInterface $validator,
        private SecurityServiceInterface $security,
        private PasswordResetService $passwordResetService,
        private AuditLogService $auditLogService,
        private OrganizationCapacityService $capacityService,
    ) {
    }

    /**
     * Liste les utilisateurs et rôles rattachés à une organisation donnée.
     * Recherche les membres via l'UUID de l'organisation avec pagination.
     */
    public function listByOrganization(string $orgUuid, PaginationQuery $query): Feedback
    {
        $feedback = new Feedback();
        $org = $this->orgRepository->findOneBy(['uuid' => $orgUuid]);

        if (!$org) {
            return $feedback
                ->addError('organizationUuid', 'Organisation introuvable.')
                ->setErrorFlushDescription('Impossible de récupérer la liste des utilisateurs.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        // Consulter la liste des membres d'une Organization est un droit
        // d'administration, pas une simple lecture.
        $this->security->checkOrganizationAccess($org, SecurityAction::MANAGE_USERS);

        $paginatedResult = $this->orgUserRepository->findPaginatedByOrganization(
            $org,
            $query->page,
            $query->limit
        );

        $data = [
            'items' => array_map([$this->mapper, 'toResponse'], $paginatedResult['items']),
            'total' => $paginatedResult['total'],
            'page' => $query->page,
            'limit' => $query->limit,
        ];

        return $feedback
            ->setData($data)
            ->setFlushDescription('Liste des membres récupérée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /** Liste les rôles visibles dans le contexte, avec les membres admissibles aux rôles déléguables. */
    public function listRoleAssignments(string $orgUuid): Feedback
    {
        $organization = $this->orgRepository->findOneBy(['uuid' => $orgUuid]);
        if (!$organization) return (new Feedback())->addError('organizationUuid', 'Organisation introuvable.')
            ->setErrorFlushDescription('Liste indisponible.')->setStatus(404)->autoInitFlush();
        $actorRole = $this->security->getOrganizationRole($this->security->getCurrentUser(), $organization);
        $isPatron = $actorRole === OrganizationRole::PATRON;
        $this->security->checkOrganizationAccess($organization, $isPatron ? SecurityAction::VIEW_USER : SecurityAction::CREATE_CITY_ADMIN);
        if (!$isPatron && $actorRole !== OrganizationRole::ADMIN_IMMOBILIER) {
            return (new Feedback())->setErrorFlushDescription('Votre rôle ne permet pas de gérer des affectations.')
                ->setStatus(403)->autoInitFlush();
        }
        $assignments = $isPatron
            ? $this->orgUserRepository->findByOrganization($organization)
            : $this->orgUserRepository->findByOrganizationAndRole($organization, OrganizationRole::ADMIN_VILLE);
        $items = array_map(function (OrganizationUser $assignment) use ($organization): array {
            $user = $assignment->getUser();
            $cities = $this->cityRepository->findAssignedToUserInOrganization($user, $organization);
            return [
                'assignmentUuid' => $assignment->getUuid()->toRfc4122(),
                'userUuid' => $user->getUuid()->toRfc4122(),
                'name' => $user->getFullName(),
                'email' => $user->getEmail(),
                'active' => $user->isActive(),
                'role' => $assignment->getRole()->value,
                'cities' => array_map(static fn (City $city): array => ['uuid' => $city->getUuid()->toRfc4122(), 'name' => $city->getName()], $cities),
            ];
        }, $assignments);
        $candidates = [];
        foreach ($this->orgUserRepository->findByOrganization($organization) as $membership) {
            $candidate = $membership->getUser();
            $candidateUuid = $candidate->getUuid()->toRfc4122();
            if (!$candidate->isActive()) continue;
            if (!isset($candidates[$candidateUuid])) {
                $candidates[$candidateUuid] = ['uuid' => $candidateUuid, 'name' => $candidate->getFullName(), 'email' => $candidate->getEmail(), 'roles' => []];
            }
            $candidates[$candidateUuid]['roles'][] = $membership->getRole()->value;
        }
        $candidates = array_values(array_filter($candidates, static fn (array $candidate): bool => $isPatron || !in_array(OrganizationRole::ADMIN_VILLE->value, $candidate['roles'], true)));
        $candidates = array_values($candidates);
        usort($candidates, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);
        return (new Feedback())->setData(['items' => $items, 'candidates' => $candidates])->setStatus(200)->autoInitFlush();
    }

    /** Rattache un utilisateur à un rôle d'organisation sans créer de doublon. */
    public function assignUser(OrganizationUserRequest $request): Feedback
    {
        $feedback = new Feedback();
        $violations = $this->validator->validate($request, groups: ['create']);

        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Données de l\'affectation invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $organization = $this->orgRepository->findOneBy(['uuid' => $request->organizationUuid]);
        if (!$organization) {
            return $feedback
                ->addError('organizationUuid', 'Organisation introuvable.')
                ->setErrorFlushDescription('Affectation impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $user = $this->userRepository->findOneBy(['uuid' => $request->userUuid]);
        if (!$user) {
            return $feedback
                ->addError('userUuid', 'Utilisateur introuvable.')
                ->setErrorFlushDescription('Affectation impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        // Attribuer un rôle revient à accorder des droits : sans ce contrôle,
        // n'importe quel utilisateur authentifié pouvait se nommer lui-même
        // PATRON d'une Organization et hériter de toutes ses données.
        $this->security->checkOrganizationAccess($organization, SecurityAction::MANAGE_USERS);
        $this->security->checkUserAccess($user, SecurityAction::MANAGE_USERS);

        if ($this->orgUserRepository->findByOrganizationAndUser($organization, $user) === []) {
            $isNewMember = true;
        } else {
            $isNewMember = false;
        }

        if ($request->role === OrganizationRole::ADMIN_VILLE) {
            return $feedback->addError('role', 'Attribuez ADMIN_VILLE via l’opération avec périmètre de villes.')
                ->setErrorFlushDescription('Les affectations de ville exigent une sélection explicite des villes.')
                ->setStatus(422)->autoInitFlush();
        }

        $existing = $this->orgUserRepository->findOneByOrganizationUserAndRole($organization, $user, $request->role);
        if ($existing) {
            return $feedback
                ->addError('role', 'Cet utilisateur possède déjà ce rôle dans l’organisation.')
                ->setErrorFlushDescription('Cette affectation existe déjà.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $orgUser = new OrganizationUser();
        $orgUser->setOrganization($organization);
        $orgUser->setUser($user);
        $orgUser->setRole($request->role);

        if ($isNewMember) {
            $attempt = $this->capacityService->executeWithinLimit($organization, 'members', function () use ($orgUser): void {
                $this->em->persist($orgUser);
                $this->em->flush();
            });
            if ($attempt['message'] !== null) {
                return $feedback->addError('capacity', $attempt['message'])->setErrorFlushDescription($attempt['message'])->setStatus($attempt['status'])->autoInitFlush();
            }
        } else {
            $this->em->persist($orgUser);
            $this->em->flush();
        }

        return $feedback
            ->setData($this->mapper->toResponse($orgUser))
            ->setFlushDescription('L\'utilisateur a été affecté à l\'organisation.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    /** Attribue un second espace à un membre existant, sous l'autorité du rôle actif. */
    public function assignExistingRole(AssignExistingRoleRequest $request): Feedback
    {
        $feedback = new Feedback();
        $violations = $this->validator->validate($request);
        if (count($violations) > 0) {
            return $feedback->bind($violations)->setErrorFlushDescription('Données d’affectation invalides.')->setStatus(422)->autoInitFlush();
        }

        $organization = $this->orgRepository->findOneBy(['uuid' => $request->organizationUuid]);
        $target = $this->userRepository->findOneBy(['uuid' => $request->userUuid]);
        if (!$organization || !$target) {
            return $feedback->addError('userUuid', 'Organisation ou utilisateur introuvable.')
                ->setErrorFlushDescription('Affectation impossible.')->setStatus(404)->autoInitFlush();
        }

        $actor = $this->security->getCurrentUser();
        if ($actor->getUuid()->equals($target->getUuid())) {
            return $feedback->addError('userUuid', 'Vous ne pouvez pas vous attribuer un autre rôle.')
                ->setErrorFlushDescription('Auto-attribution interdite.')->autoInitFlush()->setStatus(403);
        }

        $actorRole = $this->security->getOrganizationRole($actor, $organization);
        $allowed = match ($actorRole) {
            OrganizationRole::PATRON => in_array($request->role, [OrganizationRole::ADMIN_IMMOBILIER, OrganizationRole::ADMIN_VILLE], true),
            OrganizationRole::ADMIN_IMMOBILIER => $request->role === OrganizationRole::ADMIN_VILLE,
            default => false,
        };
        if (!$allowed) {
            return $feedback->setErrorFlushDescription('Votre rôle actif ne permet pas cette attribution.')
                ->autoInitFlush()->setStatus(403);
        }
        $action = match ($request->role) {
            OrganizationRole::ADMIN_IMMOBILIER => SecurityAction::CREATE_ORGANIZATION_ADMIN,
            OrganizationRole::ADMIN_VILLE => SecurityAction::CREATE_CITY_ADMIN,
            default => throw new \LogicException('Rôle non délégable dans cette opération.'),
        };
        $this->security->checkOrganizationAccess($organization, $action);

        if ($this->orgUserRepository->findByOrganizationAndUser($organization, $target) === []) {
            return $feedback->addError('userUuid', 'Le compte doit déjà être membre de cette organisation.')
                ->setErrorFlushDescription('Seuls les membres existants peuvent recevoir un rôle supplémentaire.')
                ->setStatus(422)->autoInitFlush();
        }
        if (!$target->isActive() || $target->getDeletedAt() !== null) {
            return $feedback->addError('userUuid', 'Le compte sélectionné est désactivé ou supprimé.')
                ->setErrorFlushDescription('Un compte inactif ne peut pas recevoir un nouvel espace.')
                ->setStatus(409)->autoInitFlush();
        }
        if ($this->orgUserRepository->findOneByOrganizationUserAndRole($organization, $target, $request->role)) {
            return $feedback->addError('role', 'Cet utilisateur possède déjà ce rôle dans l’organisation.')
                ->setErrorFlushDescription('Cette affectation existe déjà.')->setStatus(409)->autoInitFlush();
        }

        $cities = [];
        if ($request->role === OrganizationRole::ADMIN_VILLE) {
            if ($request->cityUuids === []) {
                return $feedback->addError('cityUuids', 'Choisissez au moins une ville pour le rôle ADMIN_VILLE.')
                    ->setErrorFlushDescription('Le périmètre de ville est obligatoire.')->setStatus(422)->autoInitFlush();
            }
            foreach (array_unique($request->cityUuids) as $cityUuid) {
                $city = $this->cityRepository->findOneBy(['uuid' => $cityUuid]);
                if (!$city || $city->getOrganization() !== $organization) {
                    return $feedback->addError('cityUuids', 'Chaque ville doit appartenir à l’organisation active.')
                        ->setErrorFlushDescription('Le périmètre demandé contient une ville non autorisée.')
                        ->setStatus(422)->autoInitFlush();
                }
                $cities[] = $city;
            }
        } elseif ($request->cityUuids !== []) {
            return $feedback->addError('cityUuids', 'Les villes ne peuvent être fournies que pour ADMIN_VILLE.')
                ->setErrorFlushDescription('Périmètre incompatible avec le rôle demandé.')
                ->setStatus(422)->autoInitFlush();
        }

        $assignment = new OrganizationUser();
        $assignment->setOrganization($organization)->setUser($target)->setRole($request->role);
        $this->em->wrapInTransaction(function () use ($assignment, $cities, $target): void {
            $this->em->persist($assignment);
            foreach ($cities as $city) {
                if ($this->userCityRepository->existsForUserAndCity($target, $city)) {
                    continue;
                }
                $link = new UserCity();
                $link->setUser($target)->setCity($city);
                $this->em->persist($link);
            }
            $this->em->flush();
        });
        $this->auditLogService->log(
            action: 'ASSIGN_ORGANIZATION_ROLE',
            entityType: OrganizationUser::class,
            entityId: (int) $assignment->getId(),
            organization: $organization,
            user: $actor,
            oldValues: null,
            newValues: [
                'targetUserUuid' => $target->getUuid()->toRfc4122(),
                'role' => $request->role->value,
                'cityUuids' => array_map(static fn (City $city): string => $city->getUuid()->toRfc4122(), $cities),
            ],
        );

        return $feedback->setData($this->mapper->toResponse($assignment))
            ->setFlushDescription('Le nouvel espace a été attribué au membre.')
            ->autoInitFlush()->setStatus(201);
    }

    /**
     * Modifie le rôle d'un utilisateur au sein d'une organisation.
     * Recherche la relation par son UUID public et applique le nouveau privilège.
     * Nettoie UserCity si le rôle change depuis/vers ADMIN_VILLE.
     * Protège le dernier PATRON de l'organisation.
     */
    public function updateRole(string $uuid, OrganizationUserRequest $request, User $currentUser): Feedback
    {
        $feedback = new Feedback();
        $orgUser = $this->orgUserRepository->findOneBy(['uuid' => $uuid]);

        if (!$orgUser) {
            return $feedback
                ->addError('uuid', 'Affectation utilisateur-organisation introuvable.')
                ->setErrorFlushDescription('Mise à jour du rôle impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $organization = $orgUser->getOrganization();
        $user = $orgUser->getUser();
        $oldRole = $orgUser->getRole();

        $this->security->checkOrganizationAccess(
            $organization,
            SecurityAction::MANAGE_USERS
        );

        $violations = $this->validator->validate($request, groups: ['update']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Données de rôle invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $newRole = $request->role;
        if ($newRole !== null && $newRole !== $oldRole) {
            if ($newRole === OrganizationRole::ADMIN_VILLE) {
                return $feedback->addError('role', 'Ajoutez ADMIN_VILLE via une affectation avec périmètre de villes.')
                    ->setErrorFlushDescription('Le rôle de ville ne peut pas être modifié sans villes attribuées.')
                    ->setStatus(422)->autoInitFlush();
            }
            if ($this->orgUserRepository->findOneByOrganizationUserAndRole($organization, $user, $newRole) !== null) {
                return $feedback->addError('role', 'Cet utilisateur possède déjà le rôle demandé.')
                    ->setErrorFlushDescription('L’affectation existe déjà.')
                    ->setStatus(409)->autoInitFlush();
            }
            // Protection du dernier PATRON : on ne peut pas rétrograder
            // le dernier PATRON de l'organisation
            if ($oldRole === OrganizationRole::PATRON) {
                $patronCount = $this->orgUserRepository->countByOrganizationAndRole($organization, OrganizationRole::PATRON);
                if ($patronCount <= 1) {
                    return $feedback
                        ->addError('role', 'Impossible de changer le rôle du dernier PATRON de l\'organisation.')
                        ->setErrorFlushDescription('Une organisation doit conserver au moins un PATRON.')
                        ->setStatus(409)
                        ->autoInitFlush();
                }
            }

            // Nettoyer UserCity si on change depuis ADMIN_VILLE
            if ($oldRole === OrganizationRole::ADMIN_VILLE) {
                $this->userCityRepository->deleteByUserAndOrganization($orgUser->getUser(), $organization);
                $this->em->flush();
            }

            $orgUser->setRole($newRole);
            $this->em->flush();
            $this->auditLogService->log(
                action: 'UPDATE_ORGANIZATION_ROLE',
                entityType: OrganizationUser::class,
                entityId: (int) $orgUser->getId(),
                organization: $organization,
                user: $currentUser,
                oldValues: ['role' => $oldRole->value],
                newValues: ['role' => $newRole->value],
            );
        }

        return $feedback
            ->setData($this->mapper->toResponse($orgUser))
            ->setFlushDescription('Le rôle de l\'utilisateur a été mis à jour.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Retire l'accès d'un utilisateur à une organisation spécifique.
     * Supprime physiquement l'enregistrement de liaison entre l'utilisateur et le tenant.
     * Nettoie aussi les attributions UserCity si l'utilisateur était ADMIN_VILLE.
     * Protège le dernier PATRON de l'organisation.
     */
    public function revokeUser(string $uuid): Feedback
    {
        $feedback = new Feedback();
        $orgUser = $this->orgUserRepository->findOneBy(['uuid' => $uuid]);

        if (!$orgUser) {
            return $feedback
                ->addError('uuid', 'Affectation introuvable.')
                ->setErrorFlushDescription('Révocation de l\'accès impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $organization = $orgUser->getOrganization();
        $user = $orgUser->getUser();
        $currentRole = $orgUser->getRole();

        $actorRole = $this->security->getOrganizationRole($this->security->getCurrentUser(), $organization);
        $action = $actorRole === OrganizationRole::ADMIN_IMMOBILIER && $currentRole === OrganizationRole::ADMIN_VILLE
            ? SecurityAction::CREATE_CITY_ADMIN
            : SecurityAction::MANAGE_USERS;
        $this->security->checkOrganizationAccess($organization, $action);

        if ($actorRole === OrganizationRole::ADMIN_IMMOBILIER && $currentRole !== OrganizationRole::ADMIN_VILLE) {
            return $feedback->setErrorFlushDescription('Un administrateur immobilier ne peut révoquer que le rôle ADMIN_VILLE.')
                ->setStatus(403)->autoInitFlush();
        }

        // Protection du dernier PATRON : on ne peut pas révoquer/dégrader
        // le dernier PATRON de l'organisation
        if ($currentRole === OrganizationRole::PATRON) {
            $patronCount = $this->orgUserRepository->countByOrganizationAndRole($organization, OrganizationRole::PATRON);
            if ($patronCount <= 1) {
                return $feedback
                    ->addError('uuid', 'Impossible de révoquer le dernier PATRON de l\'organisation.')
                    ->setErrorFlushDescription('Une organisation doit conserver au moins un PATRON.')
                    ->setStatus(409)
                    ->autoInitFlush();
            }
        }

        // Nettoyer seulement les villes de cette organisation : le même compte
        // peut conserver son accès ADMIN_VILLE ailleurs.
        if ($currentRole === OrganizationRole::ADMIN_VILLE) {
            $this->userCityRepository->deleteByUserAndOrganization($user, $organization);
        }

        $this->auditLogService->log(
            action: 'REVOKE_ORGANIZATION_ROLE',
            entityType: OrganizationUser::class,
            entityId: (int) $orgUser->getId(),
            organization: $organization,
            user: $this->security->getCurrentUser(),
            oldValues: ['userUuid' => $user->getUuid()->toRfc4122(), 'role' => $currentRole->value],
            newValues: null,
            flush: false,
        );
        $this->em->remove($orgUser);
        $this->em->flush();

        return $feedback
            ->setFlushDescription('L\'accès de l\'utilisateur à l\'organisation a été révoqué.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Crée un utilisateur ADMIN_IMMOBILIER ou ADMIN_VILLE pour l'organisation du PATRON.
     *
     * - Vérifie que l'appelant est PATRON de l'organisation
     * - Crée l'utilisateur (email, nom, téléphone) sans mot de passe
     * - Crée le lien OrganizationUser avec le rôle demandé
     * - Pour ADMIN_VILLE : attache les villes via UserCity
     * - Déclenche l'envoi d'email de configuration du mot de passe
     *
     * @param string $organizationUuid UUID de l'organisation du PATRON
     * @param OrganizationRole $role ADMIN_IMMOBILIER ou ADMIN_VILLE
     * @param string $email Email du nouvel utilisateur
     * @param string $fullName Nom complet
     * @param string $phone Téléphone
     * @param array<string>|null $cityUuids UUIDs des villes (requis pour ADMIN_VILLE)
     */
    public function createAdmin(
        string $organizationUuid,
        OrganizationRole $role,
        string $email,
        string $fullName,
        string $phone,
        ?array $cityUuids = null
    ): Feedback {
        $feedback = new Feedback();

        // Seuls les rôles ADMIN_IMMOBILIER et ADMIN_VILLE sont autorisés
        if (!in_array($role, [OrganizationRole::ADMIN_IMMOBILIER, OrganizationRole::ADMIN_VILLE], true)) {
            return $feedback
                ->addError('role', 'Seuls les rôles ADMIN_IMMOBILIER et ADMIN_VILLE sont autorisés.')
                ->setErrorFlushDescription('Rôle invalide pour la création.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        // Récupérer l'organisation
        $organization = $this->orgRepository->findOneBy(['uuid' => $organizationUuid]);
        if (!$organization) {
            return $feedback
                ->addError('organizationUuid', 'Organisation introuvable.')
                ->setErrorFlushDescription('Organisation introuvable.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        // L'ADMIN_IMMOBILIER peut uniquement créer un ADMIN_VILLE.
        $this->security->checkOrganizationAccess(
            $organization,
            $role === OrganizationRole::ADMIN_VILLE
                ? SecurityAction::CREATE_CITY_ADMIN
                : SecurityAction::CREATE_ORGANIZATION_ADMIN,
        );
        $currentUser = $this->security->getCurrentUser();
        $currentUserRole = $this->security->getOrganizationRole($currentUser, $organization);

        $canCreateRequestedRole = $currentUserRole === OrganizationRole::PATRON
            || ($currentUserRole === OrganizationRole::ADMIN_IMMOBILIER && $role === OrganizationRole::ADMIN_VILLE);
        if (!$canCreateRequestedRole) {
            return $feedback
                ->setErrorFlushDescription('Vous ne pouvez pas créer cet administrateur pour cette organisation.')
                ->setStatus(403)
                ->autoInitFlush();
        }

        // Vérifier unicité de l'email
        if ($this->userRepository->findOneBy(['email' => $email])) {
            return $feedback
                ->addError('email', 'Cette adresse email est déjà utilisée.')
                ->setErrorFlushDescription('Conflit sur l\'email.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        // Pour ADMIN_VILLE, valider les villes
        $cities = [];
        if ($role === OrganizationRole::ADMIN_VILLE) {
            if (!$cityUuids || empty($cityUuids)) {
                return $feedback
                    ->addError('cityUuids', 'Au moins une ville doit être assignée à un ADMIN_VILLE.')
                    ->setErrorFlushDescription('Villes requises pour ADMIN_VILLE.')
                    ->setStatus(422)
                    ->autoInitFlush();
                }

                foreach ($cityUuids as $cityUuid) {
                    $city = $this->cityRepository->findOneBy(['uuid' => $cityUuid]);
                    if (!$city || $city->getOrganization() !== $organization) {
                        return $feedback
                            ->addError('cityUuids', "La ville {$cityUuid} n'appartient pas à cette organisation.")
                            ->setErrorFlushDescription('Ville invalide pour cette organisation.')
                            ->setStatus(422)
                            ->autoInitFlush();
                    }
                    $cities[] = $city;
                }
            if (empty($cities)) {
                return $feedback
                    ->addError('cityUuids', 'Aucune ville valide trouvée.')
                    ->setStatus(422)
                    ->autoInitFlush();
            }
        }

        // Créer l'utilisateur, son rôle et ses villes dans une seule
        // transaction : avec des flushs intermédiaires, un échec sur
        // l'étape suivante (contrainte d'unicité ville, erreur SQL)
        // laissait un compte actif sans rôle, impossible à corriger
        // depuis l'API.
        $orgUser = null;

        $attempt = $this->capacityService->executeWithinLimit($organization, 'members', function () use ($organization, $role, $email, $fullName, $phone, $cities, &$orgUser): void {
            $this->em->wrapInTransaction(function () use ($organization, $role, $email, $fullName, $phone, $cities, &$orgUser): void {
                $user = new User();
                $user->setEmail($email);
                $user->setFullName($fullName);
                $user->setPhone($phone);
                $user->setIsActive(true);
                $user->setPassword(''); // Sera défini via reset-password
                $this->em->persist($user);

                // Le lien direct suffit : sans flush intermédiaire, l'entité
                // n'a pas d'id mais Doctrine la référence par son objet.
                $orgUser = new OrganizationUser();
                $orgUser->setOrganization($organization);
                $orgUser->setUser($user);
                $orgUser->setRole($role);
                $this->em->persist($orgUser);

                if ($role === OrganizationRole::ADMIN_VILLE) {
                    foreach ($cities as $city) {
                        $userCity = new UserCity();
                        $userCity->setUser($user);
                        $userCity->setCity($city);
                        $this->em->persist($userCity);
                    }
                }

                $this->em->flush();
            });
        });
        if ($attempt['message'] !== null) {
            return $feedback->addError('capacity', $attempt['message'])->setErrorFlushDescription($attempt['message'])->setStatus($attempt['status'])->autoInitFlush();
        }

        // L'envoi se fait APRÈS le commit : le mailer est un service
        // externe, on ne veut pas qu'une panne SMTP annule la création
        // déjà effectuée, ni qu'une transaction reste ouverte pendant
        // l'échange réseau.
        $emailSent = $this->passwordResetService->requestResetForNewUser($email);

        $this->auditLogService->log(
            action: 'CREATE_ADMIN',
            entityType: OrganizationUser::class,
            entityId: (int) $orgUser->getId(),
            organization: $organization,
            user: $currentUser,
            oldValues: null,
            newValues: [
                'email' => $email,
                'role' => $role->value,
                'cityUuids' => array_map(
                    static fn (City $city): string => $city->getUuid()->toRfc4122(),
                    $cities
                ),
                // Trace si l'invitation est réellement partie : sans cet
                // indicateur, le PATRON ne peut pas distinguer un email
                // perdu d'une création complète.
                'emailSent' => $emailSent,
            ]
        );

        if (!$emailSent) {
            return $feedback
                ->setWarningFlushDescription(
                    "L'administrateur {$role->value} a été créé, mais l'email de configuration du mot de passe n'a pas pu être envoyé. "
                    .'Demandez à cet administrateur d\'utiliser « mot de passe oublié » pour définir son accès.'
                )
                ->addWarning('email', 'Email de configuration non envoyé.')
                // `autoInitFlush()` doit précéder `setStatus()` : il écrase
                // le statut à 200/422 selon la présence d'erreurs.
                ->autoInitFlush()
                ->setStatus(201);
        }

        return $feedback
            ->setFlushDescription("L'administrateur {$role->value} a été créé. Un email de configuration du mot de passe a été envoyé.")
            ->autoInitFlush()
            ->setStatus(201);
    }
}
