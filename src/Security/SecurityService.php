<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Property\Building;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Entity\Property\Unit;
use App\Entity\Rental\Lease;
use App\Entity\Rental\Payment;
use App\Entity\Rental\Rent;
use App\Entity\Rental\Tenant;
use App\Entity\System\AuditLog;
use App\Enum\OrganizationRole;
use App\Enum\OrganizationStatus;
use App\Enum\PlatformRole;
use App\Exception\AccessDeniedException;
use App\Exception\UnauthenticatedException;
use App\Repository\Identity\OrganizationUserRepository;
use App\Repository\Identity\UserCityRepository;
use App\Repository\Property\CityRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * SecurityService
 *
 * Point d'entrée UNIQUE de toutes les décisions d'autorisation de
 * l'API. Aucun service métier ne doit réimplémenter un contrôle
 * d'appartenance à une Organization ou à une City : il délègue ici.
 *
 * Chaîne de responsabilité d'un accès à une ressource métier :
 *
 *   User
 *     -> OrganizationUser   (appartenance + rôle dans l'Organization)
 *       -> Organization
 *         -> UserCity       (périmètre ADMIN_VILLE)
 *           -> City -> Parcel -> Building -> Unit -> Lease -> Rent -> Payment
 *
 * Trois notions sont volontairement distinctes et ne doivent pas être
 * confondues :
 *   - le rôle de PLATEFORME (`PlatformRole::SUPER_ADMIN`) : global, hors
 *     tenancy, réservé à l'administration de la plateforme ;
 *   - le rôle d'ORGANIZATION (`OrganizationRole`) : PATRON,
 *     ADMIN_IMMOBILIER ou ADMIN_VILLE, valable pour UNE Organization
 *     identifiée ;
 *   - le périmètre de VILLE (`UserCity`) : sub-ensemble des villes d'une
 *     Organization auquel un ADMIN_VILLE est affecté.
 *
 * Le SUPER_ADMIN n'hérite PAS des permissions métier des administrateurs
 * immobiliers : il administre les Organizations et les comptes de
 * plateforme. L'accès de lecture aux données métier reste possible (il
 * doit pouvoir diagnostiquer la plateforme) mais il est explicite et
 * tracé via `requirePlatformRole()`.
 */
final class SecurityService implements SecurityServiceInterface
{
    /**
     * Rôle métier autorisé à consulter le journal d'audit de son
     * Organization. Un ADMIN_VILLE en est exclu : son périmètre est
     * limité aux ressources de ses villes, pas à la journalisation.
     */
    private const AUDIT_LOG_ORGANIZATION_ROLES = [
        OrganizationRole::PATRON,
        OrganizationRole::ADMIN_IMMOBILIER,
    ];

    public function __construct(
        private readonly Security $security,
        private readonly OrganizationUserRepository $organizationUserRepository,
        private readonly UserCityRepository $userCityRepository,
        private readonly CityRepository $cityRepository,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | CURRENT USER
    |--------------------------------------------------------------------------
    */

    /**
     * Retourne l'utilisateur authentifié, ou lève une 401.
     * Seul point du code autorisé à vérifier l'authentification.
     */
    public function getCurrentUser(): User
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            // 401 et non 403 : le client n'est pas encore authentifié, il
            // doit s'authentifier plutôt que de demander plus de droits.
            throw UnauthenticatedException::create('Authentification requise.');
        }

        if (!$this->isCurrentUserUsable()) {
            // Authentifié mais compte désactivé ou supprimé logiquement.
            throw new AccessDeniedException(
                'Ce compte est désactivé ou supprimé.'
            );
        }

        return $user;
    }

    public function isAuthenticated(): bool
    {
        return $this->security->getUser() instanceof User;
    }

    /**
     * Un compte authentifié mais désactivé ou supprimé logiquement
     * perd immédiatement ses droits.
     */
    public function isCurrentUserUsable(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User
            && $user->isActive()
            && $user->getDeletedAt() === null;
    }

    /*
    |--------------------------------------------------------------------------
    | ROLES PLATEFORME
    |--------------------------------------------------------------------------
    */

    public function isSuperAdmin(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User
            && $user->getPlatformRole() === PlatformRole::SUPER_ADMIN;
    }

    /**
     * Lève une 403 si l'utilisateur courant n'est pas SUPER_ADMIN.
     * Utilisé pour l'administration de la plateforme (Organizations,
     * comptes de plateforme) — jamais pour les opérations métier.
     */
    public function requirePlatformRole(PlatformRole $role = PlatformRole::SUPER_ADMIN): User
    {
        $user = $this->getCurrentUser();

        if ($user->getPlatformRole() !== $role) {
            throw new AccessDeniedException(
                'Accès refusé : cette opération relève de l\'administration de la plateforme.'
            );
        }

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | ROLES ORGANIZATION
    |--------------------------------------------------------------------------
    */

    public function isPatron(): bool
    {
        return $this->hasAnyOrganizationRole([OrganizationRole::PATRON]);
    }

    public function isAdminImmobilier(): bool
    {
        return $this->hasAnyOrganizationRole([OrganizationRole::ADMIN_IMMOBILIER]);
    }

    public function isAdminVille(): bool
    {
        return $this->hasAnyOrganizationRole([OrganizationRole::ADMIN_VILLE]);
    }

    public function hasRole(string $role): bool
    {
        return $this->security->isGranted($role);
    }

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Le rôle d'un utilisateur est toujours résolu POUR UNE Organization
     * donnée. Un utilisateur multi-Organization n'hérite jamais du rôle
     * qu'il détient dans une autre Organization.
     */
    public function getOrganizationRole(User $user, Organization $organization): ?OrganizationRole
    {
        return $this->organizationUserRepository
            ->findOneByOrganizationAndUser($organization, $user)
            ?->getRole();
    }

    public function hasOrganizationRole(
        User $user,
        Organization $organization,
        OrganizationRole ...$roles
    ): bool {
        $role = $this->getOrganizationRole($user, $organization);

        return $role !== null && in_array($role, $roles, true);
    }

    /**
     * Vrai si l'utilisateur détient au moins un des rôles fournis dans
     * l'Organization considérée. Raccourci utilisé par les services
     * lorsqu'une action est réservée à une liste de rôles.
     */
    public function hasAnyOrganizationRoleOf(
        Organization $organization,
        OrganizationRole ...$roles
    ): bool {
        return $this->hasOrganizationRole($this->getCurrentUser(), $organization, ...$roles);
    }

    public function belongsToOrganization(User $user, Organization $organization): bool
    {
        return $this->organizationUserRepository
            ->findOneByOrganizationAndUser($organization, $user) !== null;
    }

    /**
     * Le rôle est cherché dans TOUTES les Organizations de
     * l'utilisateur. Cette variante répond à la question « ce compte
     * porte-t-il ce rôle quelque part ? » et sert uniquement aux
     * prédicats globaux (`isPatron()`, `isAdminVille()`, ...).
     *
     * Elle ne doit JAMAIS être utilisée pour autoriser une action sur
     * une ressource précise : pour cela, le rôle doit être résolu dans
     * l'Organization de la ressource via `getOrganizationRole()`.
     */
    private function hasAnyOrganizationRole(array $roles): bool
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return false;
        }

        foreach ($this->organizationUserRepository->findByUser($user) as $membership) {
            if (in_array($membership->getRole(), $roles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Liste des Organizations dont l'utilisateur courant est membre.
     * Portée par défaut de toute liste de données métier : un
     * utilisateur ne voit jamais une Organization à laquelle il
     * n'appartient pas.
     *
     * @return list<Organization>
     */
    public function getCurrentUserOrganizations(): array
    {
        $organizations = [];

        foreach ($this->organizationUserRepository->findByUser($this->getCurrentUser()) as $membership) {
            $organizations[] = $membership->getOrganization();
        }

        return $organizations;
    }

    /*
    |--------------------------------------------------------------------------
    | UTILISATEUR
    |--------------------------------------------------------------------------
    */

    /**
     * Un compte n'est consultable que dans le périmètre de l'appelant.
     *
     * Trois cas legitimement autorisés : le SUPER_ADMIN (il administre la
     * plateforme), l'appelant lui-même (son propre profil), et un compte
     * avec lequel l'appelant partage au moins une Organization. Ce dernier
     * cas est ce qui permet à un PATRON de gérer les utilisateurs de son
     * propre société sans jamais voir ceux d'un autre tenant.
     *
     * À l'inverse, l'absence d'appartenance commune rend le compte
     * invisible : le renvoyer en 403 et non 404 évite de laisser deviner
     * l'existence de comptes d'autres tenants.
     */
    public function checkUserAccess(User $user, SecurityAction $action = SecurityAction::VIEW_USER): void
    {
        if (!$this->isAuthenticated()) {
            throw UnauthenticatedException::create();
        }

        if ($this->isSuperAdmin()) {
            return;
        }

        $currentUser = $this->getCurrentUser();

        if ($currentUser->getId() === $user->getId()) {
            return;
        }

        foreach ($this->getCurrentUserOrganizations() as $organization) {
            if ($this->belongsToOrganization($user, $organization)) {
                return;
            }
        }

        throw new AccessDeniedException(
            'Accès refusé : cet utilisateur n\'appartient à aucune de vos organizations.'
        );
    }

    /**
     * Variante booléenne de `checkUserAccess()`, pour filtrer des listes
     * plutôt que pour lever une exception.
     */
    public function canAccessUser(User $user, SecurityAction $action = SecurityAction::VIEW_USER): bool
    {
        try {
            $this->checkUserAccess($user, $action);

            return true;
        } catch (AccessDeniedException|UnauthenticatedException) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ORGANIZATION
    |--------------------------------------------------------------------------
    */

    public function checkOrganizationAccess(
        Organization $organization,
        SecurityAction $action
    ): void {
        if ($this->isSuperAdmin()) {
            return;
        }

        $this->checkOrganizationActive($organization);

        if (!$this->belongsToOrganization($this->getCurrentUser(), $organization)) {
            throw new AccessDeniedException(
                'Accès refusé : vous n\'appartenez pas à cette organisation.'
            );
        }

        $this->applyRoleRuleOnOrganization($organization, $action);
    }

    /**
     * Variante booléenne de `checkOrganizationAccess()`, pour les
     * filtres de listes et les jointures de repositories.
     */
    public function canAccessOrganization(
        Organization $organization,
        SecurityAction $action = SecurityAction::VIEW
    ): bool {
        try {
            $this->checkOrganizationAccess($organization, $action);

            return true;
        } catch (AccessDeniedException) {
            return false;
        }
    }

    public function checkOrganizationActive(Organization $organization): void
    {
        if (
            $organization->getStatus() !== OrganizationStatus::ACTIVE
            && !$this->isSuperAdmin()
        ) {
            throw new AccessDeniedException(
                sprintf('L\'organisation "%s" est désactivée.', $organization->getName())
            );
        }
    }

    public function checkCurrentUserOrganizationActive(): void
    {
        foreach ($this->organizationUserRepository->findByUser($this->getCurrentUser()) as $membership) {
            $this->checkOrganizationActive($membership->getOrganization());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CITY
    |--------------------------------------------------------------------------
    */

    /**
     * Contrôle d'accès à une ville, en deux temps :
     *   1. la ville doit appartenir à une Organization dont l'utilisateur
     *      est membre, avec un rôle permettant l'action demandée ;
     *   2. si l'utilisateur est ADMIN_VILLE, la ville doit lui avoir été
     *      explicitement attribuée via UserCity.
     *
     * Le point 2 est la condition qui empêche un administrateur de ville
     * d'atteindre une autre ville de sa propre Organization.
     */
    public function checkCityAccess(City $city, SecurityAction $action): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }

        $this->checkOrganizationAccess($city->getOrganization(), $action);

        if ($this->isAdminVille() && !$this->isCityAllowed($this->getCurrentUser(), $city)) {
            throw new AccessDeniedException(
                sprintf('Vous n\'êtes pas autorisé sur la ville "%s".', $city->getName())
            );
        }
    }

    public function canAccessCity(City $city, SecurityAction $action = SecurityAction::VIEW): bool
    {
        try {
            $this->checkCityAccess($city, $action);

            return true;
        } catch (AccessDeniedException) {
            return false;
        }
    }

    public function isCityAllowed(User $user, City $city): bool
    {
        return $this->userCityRepository->existsForUserAndCity($user, $city);
    }

    /**
     * Villes effectivement accessibles à l'utilisateur courant.
     *
     * - SUPER_ADMIN : aucune restriction (retourne `null` pour signaler
     *   « pas de filtre » aux repositories) ;
     * - ADMIN_VILLE : uniquement ses villes attribuées ;
     * - PATRON / ADMIN_IMMOBILIER : toutes les villes de ses Organizations.
     *
     * @return list<City>|null `null` = aucun filtre de ville à appliquer
     */
    public function getAccessibleCities(): ?array
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        $user = $this->getCurrentUser();

        if ($this->isAdminVille()) {
            return $this->cityRepository->findAssignedToUser($user);
        }

        $cities = [];

        foreach ($this->getCurrentUserOrganizations() as $organization) {
            foreach ($this->cityRepository->findInOrganization($organization) as $city) {
                $cities[] = $city;
            }
        }

        return $cities;
    }

    /**
     * Villes à utiliser pour BORNER une requête de liste.
     *
     * Différence avec `getAccessibleCities()` : cette méthode ne renvoie
     * jamais `null`. Les repositories de patrimoine (parcelles, bâtiments,
     * unités) attendent une liste concrète de villes, or `null` y signifie
     * « aucun filtre » et une liste vide y signifie « aucune ville
     * visible ». Confondre les deux revient soit à exposer toutes les
     * villes de la plateforme, soit à n'en afficher aucune.
     *
     * @return list<City>
     */
    public function getScopedCities(): array
    {
        $cities = $this->getAccessibleCities();

        if ($cities !== null) {
            return $cities;
        }

        // SUPER_ADMIN : périmètre plateforme, toutes les villes actives.
        return $this->cityRepository->findAllActive();
    }

    /*
    |--------------------------------------------------------------------------
    | PROPERTY (PARCEL / BUILDING / UNIT)
    |--------------------------------------------------------------------------
    */

    public function checkParcelAccess(Parcel $parcel, SecurityAction $action): void
    {
        $this->checkCityAccess($parcel->getCity(), $action);
    }

    public function canAccessParcel(Parcel $parcel, SecurityAction $action = SecurityAction::VIEW): bool
    {
        try {
            $this->checkParcelAccess($parcel, $action);

            return true;
        } catch (AccessDeniedException) {
            return false;
        }
    }

    public function checkBuildingAccess(Building $building, SecurityAction $action): void
    {
        $this->checkCityAccess($building->getParcel()->getCity(), $action);
    }

    public function canAccessBuilding(Building $building, SecurityAction $action = SecurityAction::VIEW): bool
    {
        try {
            $this->checkBuildingAccess($building, $action);

            return true;
        } catch (AccessDeniedException) {
            return false;
        }
    }

    public function checkUnitAccess(Unit $unit, SecurityAction $action): void
    {
        $this->checkCityAccess($unit->getBuilding()->getParcel()->getCity(), $action);
    }

    public function canAccessUnit(Unit $unit, SecurityAction $action = SecurityAction::VIEW): bool
    {
        try {
            $this->checkUnitAccess($unit, $action);

            return true;
        } catch (AccessDeniedException) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TENANT
    |--------------------------------------------------------------------------
    */

    /**
     * Un Tenant appartient directement à une Organization. Un
     * ADMIN_VILLE n'est donc PAS restreint par UserCity sur un Tenant :
     * sa limitation par ville ne s'exerce que sur le patrimoine
     * (City -> Parcel -> Building -> Unit) et sur les leases rattachés à
     * ce patrimoine. Voir `checkLeaseAccess()` pour ce cas.
     */
    public function checkTenantAccess(Tenant $tenant, SecurityAction $action): void
    {
        $this->checkOrganizationAccess($tenant->getOrganization(), $action);
    }

    public function canAccessTenant(Tenant $tenant, SecurityAction $action = SecurityAction::VIEW): bool
    {
        try {
            $this->checkTenantAccess($tenant, $action);

            return true;
        } catch (AccessDeniedException) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LEASE / RENT / PAYMENT
    |--------------------------------------------------------------------------
    */

    /**
     * Un Lease cumule deux portées : l'Organization (colonne dédiée) et,
     * transitivement, la ville de l'unité louée. Un ADMIN_VILLE ne peut
     * donc voir que les baux dont l'unité est dans une de SES villes.
     */
    public function checkLeaseAccess(Lease $lease, SecurityAction $action): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }

        $this->checkOrganizationAccess($lease->getOrganization(), $action);

        if ($this->isAdminVille()) {
            $city = $lease->getUnit()->getBuilding()->getParcel()->getCity();

            if (!$this->isCityAllowed($this->getCurrentUser(), $city)) {
                throw new AccessDeniedException(
                    'Accès refusé : l\'unité de ce bail ne se situe pas dans une de vos villes.'
                );
            }
        }
    }

    public function canAccessLease(Lease $lease, SecurityAction $action = SecurityAction::VIEW): bool
    {
        try {
            $this->checkLeaseAccess($lease, $action);

            return true;
        } catch (AccessDeniedException) {
            return false;
        }
    }

    public function checkRentAccess(Rent $rent, SecurityAction $action): void
    {
        $this->checkLeaseAccess($rent->getLease(), $action);
    }

    public function canAccessRent(Rent $rent, SecurityAction $action = SecurityAction::VIEW): bool
    {
        try {
            $this->checkRentAccess($rent, $action);

            return true;
        } catch (AccessDeniedException) {
            return false;
        }
    }

    public function checkPaymentAccess(Payment $payment, SecurityAction $action): void
    {
        $this->checkLeaseAccess($payment->getRent()->getLease(), $action);
    }

    public function canAccessPayment(Payment $payment, SecurityAction $action = SecurityAction::VIEW): bool
    {
        try {
            $this->checkPaymentAccess($payment, $action);

            return true;
        } catch (AccessDeniedException) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AUDIT
    |--------------------------------------------------------------------------
    */

    /**
     * Les entrées d'audit sans Organization (actions techniques de la
     * plateforme) sont strictement réservées au SUPER_ADMIN : un rôle
     * d'Organization ne doit pas y avoir accès.
     */
    public function checkAuditLogAccess(AuditLog $auditLog, SecurityAction $action): void
    {
        $organization = $auditLog->getOrganization();

        if ($organization === null) {
            if ($this->isSuperAdmin()) {
                return;
            }

            throw new AccessDeniedException('Cet audit log est strictement réservé à la plateforme.');
        }

        $this->checkOrganizationAuditLogAccess($organization, $action);
    }

    /**
     * Contrôle d'accès au journal d'audit d'une Organization.
     *
     * Extrait de `checkAuditLogAccess()` parce que la contrainte porte sur
     * l'Organization, pas sur l'événement : une liste d'événements se
     * contrôle avant d'être exécutée, alors qu'aucun événement n'existe
     * encore. Sans cette variante, il faudrait fabriquer une entité
     * d'audit factice uniquement pour passer le contrôle.
     */
    public function checkOrganizationAuditLogAccess(Organization $organization, SecurityAction $action): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }

        $this->checkOrganizationAccess($organization, $action);

        $role = $this->getOrganizationRole($this->getCurrentUser(), $organization);

        if (!in_array($role, self::AUDIT_LOG_ORGANIZATION_ROLES, true)) {
            throw new AccessDeniedException('Vous n\'êtes pas autorisé à consulter les audit logs.');
        }
    }

    public function canAccessAuditLog(AuditLog $auditLog, SecurityAction $action = SecurityAction::VIEW): bool
    {
        try {
            $this->checkAuditLogAccess($auditLog, $action);

            return true;
        } catch (AccessDeniedException) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PERMISSIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Variante booléenne de `checkPermission()`, destinée aux
     * annotations `#[IsGranted]` et aux filtres de listes.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $action = SecurityAction::tryFrom($permission);

        if (!$action) {
            return false;
        }

        if (!$this->isAuthenticated()) {
            return false;
        }

        foreach ($this->getCurrentUserOrganizations() as $organization) {
            try {
                $this->checkOrganizationAccess($organization, $action);

                return true;
            } catch (AccessDeniedException) {
                continue;
            }
        }

        return false;
    }

    public function checkPermission(string $permission): void
    {
        if (!$this->hasPermission($permission)) {
            throw new AccessDeniedException(
                sprintf('Permission refusée : "%s".', $permission)
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ROLE RULES
    |--------------------------------------------------------------------------
    */

    /**
     * Applique la matrice rôle x action au sein d'une Organization.
     * La résolution du rôle est TOUJOURS effectuée dans l'Organization
     * concernée : c'est ce qui empêche qu'un PATRON de l'Organization A
     * soit traité comme PATRON de l'Organization B.
     */
    private function applyRoleRuleOnOrganization(
        Organization $organization,
        SecurityAction $action
    ): void {
        $role = $this->getOrganizationRole($this->getCurrentUser(), $organization);

        match ($role) {
            OrganizationRole::PATRON => $this->checkPatronAction($action),
            OrganizationRole::ADMIN_IMMOBILIER => $this->checkAdminImmobilierAction($action),
            OrganizationRole::ADMIN_VILLE => $this->checkAdminVilleAction($action),
            default => throw new AccessDeniedException(
                sprintf('Accès refusé pour l\'action "%s".', $action->value)
            ),
        };
    }

    /**
     * Le PATRON administre l'intégralité de son Organization. Son
     * périmètre est déjà borné à cette Organization par
     * `checkOrganizationAccess()` : aucune restriction d'action
     * supplémentaire n'est nécessaire.
     */
    private function checkPatronAction(SecurityAction $action): void
    {
    }

    /**
     * L'ADMIN_IMMOBILIER gère le patrimoine et la location, et
     * consulte l'Organization de rattachement en lecture seule.
     * Il ne peut ni administrer la plateforme, ni suspendre une
     * Organization, ni attribuer des rôles.
     */
    private function checkAdminImmobilierAction(SecurityAction $action): void
    {
        $allowed = [
            SecurityAction::VIEW,
            SecurityAction::VIEW_ORGANIZATION,

            SecurityAction::VIEW_CITY, SecurityAction::CREATE_CITY, SecurityAction::UPDATE_CITY,
            SecurityAction::DELETE_CITY, SecurityAction::ACTIVATE_CITY, SecurityAction::DEACTIVATE_CITY,

            SecurityAction::VIEW_PARCEL, SecurityAction::CREATE_PARCEL, SecurityAction::UPDATE_PARCEL, SecurityAction::DELETE_PARCEL,

            SecurityAction::VIEW_BUILDING, SecurityAction::CREATE_BUILDING, SecurityAction::UPDATE_BUILDING, SecurityAction::DELETE_BUILDING,

            SecurityAction::VIEW_UNIT, SecurityAction::CREATE_UNIT, SecurityAction::UPDATE_UNIT, SecurityAction::DELETE_UNIT,

            SecurityAction::VIEW_TENANT, SecurityAction::CREATE_TENANT, SecurityAction::UPDATE_TENANT, SecurityAction::DELETE_TENANT, SecurityAction::ARCHIVE_TENANT,

            SecurityAction::VIEW_LEASE, SecurityAction::CREATE_LEASE, SecurityAction::UPDATE_LEASE, SecurityAction::DELETE_LEASE,
            SecurityAction::ACTIVATE_LEASE, SecurityAction::TERMINATE_LEASE, SecurityAction::CANCEL_LEASE,

            SecurityAction::VIEW_RENT, SecurityAction::CREATE_RENT, SecurityAction::UPDATE_RENT, SecurityAction::DELETE_RENT, SecurityAction::MARK_RENT_OVERDUE,

            SecurityAction::VIEW_PAYMENT, SecurityAction::CREATE_PAYMENT, SecurityAction::UPDATE_PAYMENT, SecurityAction::DELETE_PAYMENT, SecurityAction::CANCEL_PAYMENT,

            SecurityAction::VIEW_AUDIT_LOG, SecurityAction::EXPORT_AUDIT_LOG,
        ];

        $this->denyIfNotAllowed($action, $allowed, 'Administrateur immobilier');
    }

    /**
     * L'ADMIN_VILLE est borné à SES villes (UserCity). Il ne gère que
     * le patrimoine et la location : ni suppression, ni gestion des
     * rôles, ni journal d'audit, ni activation/désactivation de villes.
     */
    private function checkAdminVilleAction(SecurityAction $action): void
    {
        $allowed = [
            SecurityAction::VIEW,
            SecurityAction::VIEW_ORGANIZATION,
            SecurityAction::VIEW_CITY,

            SecurityAction::VIEW_PARCEL, SecurityAction::CREATE_PARCEL, SecurityAction::UPDATE_PARCEL, SecurityAction::DELETE_PARCEL,

            SecurityAction::VIEW_BUILDING, SecurityAction::CREATE_BUILDING, SecurityAction::UPDATE_BUILDING, SecurityAction::DELETE_BUILDING,

            SecurityAction::VIEW_UNIT, SecurityAction::CREATE_UNIT, SecurityAction::UPDATE_UNIT, SecurityAction::DELETE_UNIT,

            SecurityAction::VIEW_TENANT, SecurityAction::CREATE_TENANT, SecurityAction::UPDATE_TENANT, SecurityAction::ARCHIVE_TENANT,

            SecurityAction::VIEW_LEASE, SecurityAction::CREATE_LEASE, SecurityAction::UPDATE_LEASE,
            SecurityAction::ACTIVATE_LEASE, SecurityAction::TERMINATE_LEASE, SecurityAction::CANCEL_LEASE,

            SecurityAction::VIEW_RENT, SecurityAction::CREATE_RENT, SecurityAction::UPDATE_RENT, SecurityAction::MARK_RENT_OVERDUE,

            SecurityAction::VIEW_PAYMENT, SecurityAction::CREATE_PAYMENT, SecurityAction::CANCEL_PAYMENT,
        ];

        $this->denyIfNotAllowed($action, $allowed, 'Administrateur de ville');
    }

    private function denyIfNotAllowed(
        SecurityAction $action,
        array $allowed,
        string $roleName
    ): void {
        if (!in_array($action, $allowed, true)) {
            throw new AccessDeniedException(
                sprintf('%s n\'est pas autorisé à effectuer l\'action "%s".', $roleName, $action->value)
            );
        }
    }
}
