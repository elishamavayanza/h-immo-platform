<?php

declare(strict_types=1);

namespace App\Service\Identity;

use App\Dto\Feedback;
use App\Dto\Request\Identity\OrganizationUserRequest;
use App\Dto\Request\PaginationQuery;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Entity\Identity\UserCity;
use App\Enum\OrganizationRole;
use App\Mapper\Identity\OrganizationUserMapper;
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
 * Permet au PATRON de créer des ADMIN_IMMOBILIER et ADMIN_VILLE :
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
        private AuditLogService $auditLogService
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

    /**
     * Rattache un utilisateur à une organisation en lui assignant un rôle spécifique.
     * Contrôle l'unicité de la relation (un seul rôle par couple utilisateur/organisation).
     */
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

        $existing = $this->orgUserRepository->findOneBy(['organization' => $organization, 'user' => $user]);
        if ($existing) {
            return $feedback
                ->addError('userUuid', 'L\'utilisateur appartient déjà à cette organisation.')
                ->setErrorFlushDescription('Relation utilisateur-organisation déjà existante.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $orgUser = new OrganizationUser();
        $orgUser->setOrganization($organization);
        $orgUser->setUser($user);
        $orgUser->setRole($request->role);

        $this->em->persist($orgUser);
        $this->em->flush();

        return $feedback
            ->setData($this->mapper->toResponse($orgUser))
            ->setFlushDescription('L\'utilisateur a été affecté à l\'organisation.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    /**
     * Modifie le rôle d'un utilisateur au sein d'une organisation.
     * Recherche la relation par son UUID public et applique le nouveau privilège.
     */
    public function updateRole(string $uuid, OrganizationUserRequest $request): Feedback
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

        $this->security->checkOrganizationAccess(
            $orgUser->getOrganization(),
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

        if ($request->role !== null) {
            $orgUser->setRole($request->role);
            $this->em->flush();
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

        $this->security->checkOrganizationAccess(
            $orgUser->getOrganization(),
            SecurityAction::MANAGE_USERS
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

        // Vérifier que l'appelant est PATRON de cette organisation
        $this->security->checkOrganizationAccess($organization, SecurityAction::MANAGE_USERS);
        $currentUser = $this->security->getCurrentUser();
        $currentUserRole = $this->security->getOrganizationRole($currentUser, $organization);

        if ($currentUserRole !== OrganizationRole::PATRON) {
            return $feedback
                ->setErrorFlushDescription('Seul le PATRON peut créer des administrateurs.')
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

        // Créer l'utilisateur (sans mot de passe)
        $user = new User();
        $user->setEmail($email);
        $user->setFullName($fullName);
        $user->setPhone($phone);
        $user->setIsActive(true);
        $user->setPassword(''); // Sera défini via reset-password
        $this->em->persist($user);
        $this->em->flush();

        // Créer le lien OrganizationUser
        $orgUser = new OrganizationUser();
        $orgUser->setOrganization($this->em->getReference(Organization::class, $organization->getId()));
        $orgUser->setUser($this->em->getReference(User::class, $user->getId()));
        $orgUser->setRole($role);
        $this->em->persist($orgUser);
        $this->em->flush();

        // Pour ADMIN_VILLE, créer les UserCity
        if ($role === OrganizationRole::ADMIN_VILLE) {
            foreach ($cities as $city) {
                $userCity = new UserCity();
                $userCity->setUser($this->em->getReference(User::class, $user->getId()));
                $userCity->setCity($this->em->getReference(City::class, $city->getId()));
                $this->em->persist($userCity);
            }
            $this->em->flush();
        }

        // Déclencher l'envoi de l'email de réinitialisation de mot de passe
        $this->passwordResetService->requestReset($email);

        return $feedback
            ->setFlushDescription("L'administrateur {$role->value} a été créé. Un email de configuration du mot de passe a été envoyé.")
            ->setStatus(201)
            ->autoInitFlush();
    }
}
