<?php

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
use App\Repository\Identity\OrganizationUserRepository;
use App\Repository\Identity\UserCityRepository;
use Symfony\Bundle\SecurityBundle\Security;

final class SecurityService implements SecurityServiceInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly OrganizationUserRepository $organizationUserRepository,
        private readonly UserCityRepository $userCityRepository,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | CURRENT USER
    |--------------------------------------------------------------------------
    */

    public function getCurrentUser(): User
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Utilisateur non authentifié.');
        }

        return $user;
    }

    public function isAuthenticated(): bool
    {
        return $this->security->getUser() instanceof User;
    }

    /*
    |--------------------------------------------------------------------------
    | ROLES PLATFORME & ORGANISATION
    |--------------------------------------------------------------------------
    */

    public function isSuperAdmin(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User
            && $user->getPlatformRole() === PlatformRole::SUPER_ADMIN;
    }

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

    private function hasAnyOrganizationRole(array $roles): bool
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return false;
        }

        foreach ($this->organizationUserRepository->findBy(['user' => $user]) as $membership) {
            if (in_array($membership->getRole(), $roles, true)) {
                return true;
            }
        }

        return false;
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

        $user = $this->getCurrentUser();

        $this->checkOrganizationActive($organization);

        if (!$this->belongsToOrganization($user, $organization)) {
            throw new AccessDeniedException(
                'Accès refusé : vous n’appartenez pas à cette organisation.'
            );
        }

        $this->applyRoleRuleOnOrganization($organization, $action);
    }

    public function checkOrganizationActive(Organization $organization): void
    {
        if (
            $organization->getStatus() !== OrganizationStatus::ACTIVE
            && !$this->isSuperAdmin()
        ) {
            throw new AccessDeniedException(
                sprintf('L’organisation "%s" est désactivée.', $organization->getName())
            );
        }
    }

    public function checkCurrentUserOrganizationActive(): void
    {
        $user = $this->getCurrentUser();

        foreach ($this->organizationUserRepository->findBy(['user' => $user]) as $membership) {
            $organization = $membership->getOrganization();
            if ($organization !== null) {
                $this->checkOrganizationActive($organization);
            }
        }
    }

    public function belongsToOrganization(
        User $user,
        Organization $organization
    ): bool {
        return $this->organizationUserRepository->findOneBy([
                'user' => $user,
                'organization' => $organization,
            ]) !== null;
    }

    private function getUserRoleInOrganization(
        User $user,
        Organization $organization
    ): ?OrganizationRole {
        $membership = $this->organizationUserRepository->findOneBy([
            'user' => $user,
            'organization' => $organization,
        ]);

        return $membership?->getRole();
    }

    /*
    |--------------------------------------------------------------------------
    | CITY
    |--------------------------------------------------------------------------
    */

    public function checkCityAccess(
        City $city,
        SecurityAction $action
    ): void {
        if ($this->isSuperAdmin()) {
            return;
        }

        $user = $this->getCurrentUser();

        $this->checkOrganizationAccess(
            $city->getOrganization(),
            $action
        );

        if ($this->isAdminVille() && !$this->isCityAllowed($user, $city)) {
            throw new AccessDeniedException(
                sprintf('Vous n’êtes pas autorisé sur la ville "%s".', $city->getName())
            );
        }
    }

    public function isCityAllowed(
        User $user,
        City $city
    ): bool {
        return $this->userCityRepository->findOneBy([
                'user' => $user,
                'city' => $city,
            ]) !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | PROPERTY
    |--------------------------------------------------------------------------
    */

    public function checkParcelAccess(
        Parcel $parcel,
        SecurityAction $action
    ): void {
        $this->checkCityAccess($parcel->getCity(), $action);
    }

    public function checkBuildingAccess(
        Building $building,
        SecurityAction $action
    ): void {
        $this->checkCityAccess(
            $building->getParcel()->getCity(),
            $action
        );
    }

    public function checkUnitAccess(
        Unit $unit,
        SecurityAction $action
    ): void {
        $this->checkCityAccess(
            $unit->getBuilding()->getParcel()->getCity(),
            $action
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TENANT
    |--------------------------------------------------------------------------
    */

    public function checkTenantAccess(
        Tenant $tenant,
        SecurityAction $action
    ): void {
        $this->checkOrganizationAccess($tenant->getOrganization(), $action);
    }

    /*
    |--------------------------------------------------------------------------
    | LEASE
    |--------------------------------------------------------------------------
    */

    public function checkLeaseAccess(
        Lease $lease,
        SecurityAction $action
    ): void {
        $this->checkOrganizationAccess($lease->getOrganization(), $action);

        if ($this->isAdminVille()) {
            $city = $lease->getUnit()->getBuilding()->getParcel()->getCity();
            if (!$this->isCityAllowed($this->getCurrentUser(), $city)) {
                throw new AccessDeniedException('Vous n’êtes pas autorisé sur cette ville.');
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RENT & PAYMENT
    |--------------------------------------------------------------------------
    */

    public function checkRentAccess(
        Rent $rent,
        SecurityAction $action
    ): void {
        $this->checkLeaseAccess($rent->getLease(), $action);
    }

    public function checkPaymentAccess(
        Payment $payment,
        SecurityAction $action
    ): void {
        $this->checkLeaseAccess($payment->getRent()->getLease(), $action);
    }

    /*
    |--------------------------------------------------------------------------
    | AUDIT
    |--------------------------------------------------------------------------
    */

    public function checkAuditLogAccess(
        AuditLog $auditLog,
        SecurityAction $action
    ): void {
        if ($this->isSuperAdmin()) {
            return;
        }

        if ($auditLog->getOrganization() === null) {
            throw new AccessDeniedException('Cet audit log est strictement réservé à la plateforme.');
        }

        $this->checkOrganizationAccess($auditLog->getOrganization(), $action);

        $role = $this->getUserRoleInOrganization($this->getCurrentUser(), $auditLog->getOrganization());

        if (!in_array($role, [OrganizationRole::PATRON, OrganizationRole::ADMIN_IMMOBILIER], true)) {
            throw new AccessDeniedException('Vous n’êtes pas autorisé à consulter les audit logs.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PERMISSIONS
    |--------------------------------------------------------------------------
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

        try {
            $user = $this->getCurrentUser();
            $memberships = $this->organizationUserRepository->findBy(['user' => $user]);
            foreach ($memberships as $membership) {
                if ($membership->getOrganization()) {
                    $this->applyRoleRuleOnOrganization($membership->getOrganization(), $action);
                    return true;
                }
            }
        } catch (AccessDeniedException) {
            return false;
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

    private function applyRoleRuleOnOrganization(
        Organization $organization,
        SecurityAction $action
    ): void {
        if ($this->isSuperAdmin()) {
            return;
        }

        $role = $this->getUserRoleInOrganization(
            $this->getCurrentUser(),
            $organization
        );

        match ($role) {
            OrganizationRole::PATRON => $this->checkPatronAction($action),
            OrganizationRole::ADMIN_IMMOBILIER => $this->checkAdminImmobilierAction($action),
            OrganizationRole::ADMIN_VILLE => $this->checkAdminVilleAction($action),
            default => throw new AccessDeniedException(
                sprintf('Accès refusé pour l’action "%s".', $action->value)
            ),
        };
    }

    private function checkPatronAction(SecurityAction $action): void
    {
        return;
    }

    private function checkAdminImmobilierAction(SecurityAction $action): void
    {
        $allowed = [
            SecurityAction::VIEW, SecurityAction::VIEW_ORGANIZATION,
            SecurityAction::VIEW_CITY, SecurityAction::CREATE_CITY, SecurityAction::UPDATE_CITY, SecurityAction::DELETE_CITY, SecurityAction::ACTIVATE_CITY, SecurityAction::DEACTIVATE_CITY,
            SecurityAction::VIEW_PARCEL, SecurityAction::CREATE_PARCEL, SecurityAction::UPDATE_PARCEL, SecurityAction::DELETE_PARCEL,
            SecurityAction::VIEW_BUILDING, SecurityAction::CREATE_BUILDING, SecurityAction::UPDATE_BUILDING, SecurityAction::DELETE_BUILDING,
            SecurityAction::VIEW_UNIT, SecurityAction::CREATE_UNIT, SecurityAction::UPDATE_UNIT, SecurityAction::DELETE_UNIT,
            SecurityAction::VIEW_TENANT, SecurityAction::CREATE_TENANT, SecurityAction::UPDATE_TENANT, SecurityAction::DELETE_TENANT, SecurityAction::ARCHIVE_TENANT,
            SecurityAction::VIEW_LEASE, SecurityAction::CREATE_LEASE, SecurityAction::UPDATE_LEASE, SecurityAction::DELETE_LEASE, SecurityAction::ACTIVATE_LEASE, SecurityAction::TERMINATE_LEASE, SecurityAction::CANCEL_LEASE,
            SecurityAction::VIEW_RENT, SecurityAction::CREATE_RENT, SecurityAction::UPDATE_RENT, SecurityAction::DELETE_RENT, SecurityAction::MARK_RENT_OVERDUE,
            SecurityAction::VIEW_PAYMENT, SecurityAction::CREATE_PAYMENT, SecurityAction::UPDATE_PAYMENT, SecurityAction::DELETE_PAYMENT, SecurityAction::CANCEL_PAYMENT,
            SecurityAction::VIEW_AUDIT_LOG, SecurityAction::EXPORT_AUDIT_LOG,
        ];

        $this->denyIfNotAllowed($action, $allowed, 'Administrateur immobilier');
    }

    private function checkAdminVilleAction(SecurityAction $action): void
    {
        $allowed = [
            SecurityAction::VIEW, SecurityAction::VIEW_ORGANIZATION, SecurityAction::VIEW_CITY,
            SecurityAction::VIEW_PARCEL, SecurityAction::CREATE_PARCEL, SecurityAction::UPDATE_PARCEL, SecurityAction::DELETE_PARCEL,
            SecurityAction::VIEW_BUILDING, SecurityAction::CREATE_BUILDING, SecurityAction::UPDATE_BUILDING, SecurityAction::DELETE_BUILDING,
            SecurityAction::VIEW_UNIT, SecurityAction::CREATE_UNIT, SecurityAction::UPDATE_UNIT, SecurityAction::DELETE_UNIT,
            SecurityAction::VIEW_TENANT, SecurityAction::CREATE_TENANT, SecurityAction::UPDATE_TENANT, SecurityAction::ARCHIVE_TENANT,
            SecurityAction::VIEW_LEASE, SecurityAction::CREATE_LEASE, SecurityAction::UPDATE_LEASE, SecurityAction::ACTIVATE_LEASE, SecurityAction::TERMINATE_LEASE, SecurityAction::CANCEL_LEASE,
            SecurityAction::VIEW_RENT, SecurityAction::CREATE_RENT, SecurityAction::UPDATE_RENT, SecurityAction::MARK_RENT_OVERDUE,
            SecurityAction::VIEW_PAYMENT, SecurityAction::CREATE_PAYMENT, SecurityAction::UPDATE_PAYMENT, SecurityAction::CANCEL_PAYMENT,
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
                sprintf('%s n’est pas autorisé à effectuer l’action "%s".', $roleName, $action->value)
            );
        }
    }
}
