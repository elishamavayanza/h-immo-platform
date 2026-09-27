<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\OrganizationRole;
use App\Enum\PlatformRole;
use App\Entity\Expense\Expense;
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
use App\Entity\Staff\Worker;
use App\Entity\Staff\WorkerAssignment;
use App\Entity\System\AuditLog;

/**
 * Contrat d'autorisation.
 *
 * Les services doivent typer ce contrat plutôt que `SecurityService`
 * concret : l'autorisation devient alors mockable et testable sans
 * conteneur ni base de données.
 *
 * Convention : `check*()` lève une exception si l'accès est refusé,
 * `can*()` renvoie un booléen, `is*()` décrit l'utilisateur courant.
 */
interface SecurityServiceInterface
{
    /*
     * ============================================================
     * UTILISATEUR COURANT
     * ============================================================
     */

    public function getCurrentUser(): User;

    public function isAuthenticated(): bool;

    /**
     * L'utilisateur courant est-il utilisable (authentifié et actif) ?
     */
    public function isCurrentUserUsable(): bool;

    /*
     * ============================================================
     * RÔLES
     * ============================================================
     */

    public function isSuperAdmin(): bool;

    /**
     * Exige un rôle de plateforme et renvoie l'utilisateur courant.
     *
     * @throws AccessDeniedException
     */
    public function requirePlatformRole(PlatformRole $role = PlatformRole::SUPER_ADMIN): User;

    public function isPatron(): bool;

    public function isAdminImmobilier(): bool;

    public function isAdminVille(): bool;

    public function hasRole(string $role): bool;

    /**
     * @param list<string> $roles
     */
    public function hasAnyRole(array $roles): bool;

    /*
     * ============================================================
     * ORGANIZATION
     * ============================================================
     */

    /**
     * Rôle de l'utilisateur dans une organization donnée, `null` s'il
     * n'en fait pas partie.
     */
    public function getOrganizationRole(User $user, Organization $organization): ?OrganizationRole;

    public function hasOrganizationRole(
        User $user,
        Organization $organization,
        OrganizationRole ...$roles
    ): bool;

    public function hasAnyOrganizationRoleOf(
        Organization $organization,
        OrganizationRole ...$roles
    ): bool;

    public function belongsToOrganization(
        User $user,
        Organization $organization
    ): bool;

    /**
     * @return list<Organization>
     */
    public function getCurrentUserOrganizations(): array;

    /**
     * @throws AccessDeniedException
     */
    public function checkOrganizationAccess(
        Organization $organization,
        SecurityAction $action
    ): void;

    public function canAccessOrganization(
        Organization $organization,
        SecurityAction $action = SecurityAction::VIEW
    ): bool;

    /**
     * @throws AccessDeniedException
     */
    public function checkOrganizationActive(Organization $organization): void;

    /**
     * @throws AccessDeniedException
     */
    public function checkCurrentUserOrganizationActive(): void;

    /*
     * ============================================================
     * UTILISATEUR
     * ============================================================
     */

    /**
     * @throws AccessDeniedException
     * @throws UnauthenticatedException
     */
    public function checkUserAccess(
        User $user,
        SecurityAction $action = SecurityAction::VIEW_USER
    ): void;

    public function canAccessUser(
        User $user,
        SecurityAction $action = SecurityAction::VIEW_USER
    ): bool;

    /*
     * ============================================================
     * VILLE
     * ============================================================
     */

    /**
     * @throws AccessDeniedException
     */
    public function checkCityAccess(
        City $city,
        SecurityAction $action
    ): void;

    public function canAccessCity(
        City $city,
        SecurityAction $action = SecurityAction::VIEW
    ): bool;

    public function isCityAllowed(
        User $user,
        City $city
    ): bool;

    /**
     * Villes accessibles à l'utilisateur courant.
     *
     * @return list<City>|null `null` = aucun filtre de ville à appliquer
     */
    public function getAccessibleCities(): ?array;

    /**
     * Villes à utiliser pour BORNER une requête de liste : la valeur n'est
     * jamais `null`, une liste vide signifiant « aucune ville visible ».
     *
     * @return list<City>
     */
    public function getScopedCities(): array;

    /*
     * ============================================================
     * PATRIMOINE (PARCELLE / BÂTIMENT / UNITÉ)
     * ============================================================
     */

    /**
     * @throws AccessDeniedException
     */
    public function checkParcelAccess(Parcel $parcel, SecurityAction $action): void;

    public function canAccessParcel(
        Parcel $parcel,
        SecurityAction $action = SecurityAction::VIEW
    ): bool;

    /**
     * @throws AccessDeniedException
     */
    public function checkBuildingAccess(Building $building, SecurityAction $action): void;

    public function canAccessBuilding(
        Building $building,
        SecurityAction $action = SecurityAction::VIEW
    ): bool;

    /**
     * @throws AccessDeniedException
     */
    public function checkUnitAccess(Unit $unit, SecurityAction $action): void;

    public function canAccessUnit(
        Unit $unit,
        SecurityAction $action = SecurityAction::VIEW
    ): bool;

    /*
     * ============================================================
     * LOCATAIRE
     * ============================================================
     */

    /**
     * @throws AccessDeniedException
     */
    public function checkTenantAccess(Tenant $tenant, SecurityAction $action): void;

    public function canAccessTenant(
        Tenant $tenant,
        SecurityAction $action = SecurityAction::VIEW
    ): bool;

    /*
     * ============================================================
     * BAIL
     * ============================================================
     */

    /**
     * @throws AccessDeniedException
     */
    public function checkLeaseAccess(Lease $lease, SecurityAction $action): void;

    public function canAccessLease(
        Lease $lease,
        SecurityAction $action = SecurityAction::VIEW
    ): bool;

    /*
     * ============================================================
     * LOYER
     * ============================================================
     */

    /**
     * @throws AccessDeniedException
     */
    public function checkRentAccess(Rent $rent, SecurityAction $action): void;

    public function canAccessRent(
        Rent $rent,
        SecurityAction $action = SecurityAction::VIEW
    ): bool;

    /*
     * ============================================================
     * PAIEMENT
     * ============================================================
     */

    /**
     * @throws AccessDeniedException
     */
    public function checkPaymentAccess(Payment $payment, SecurityAction $action): void;

    public function canAccessPayment(
        Payment $payment,
        SecurityAction $action = SecurityAction::VIEW
    ): bool;

    /*
     * ============================================================
     * PERSONNEL
     * ============================================================
     */

    /**
     * @throws AccessDeniedException
     */
    public function checkWorkerAccess(Worker $worker, SecurityAction $action): void;

    public function canAccessWorker(
        Worker $worker,
        SecurityAction $action = SecurityAction::VIEW
    ): bool;

    /**
     * @throws AccessDeniedException
     */
    public function checkWorkerAssignmentAccess(WorkerAssignment $assignment, SecurityAction $action): void;

    public function canAccessWorkerAssignment(
        WorkerAssignment $assignment,
        SecurityAction $action = SecurityAction::VIEW
    ): bool;

    /*
     * ============================================================
     * DEPENSES
     * ============================================================
     */

    /**
     * @throws AccessDeniedException
     */
    public function checkExpenseAccess(Expense $expense, SecurityAction $action): void;

    public function canAccessExpense(
        Expense $expense,
        SecurityAction $action = SecurityAction::VIEW
    ): bool;

    /*
     * ============================================================
     * JOURNAL D'AUDIT
     * ============================================================
     */

    /**
     * @throws AccessDeniedException
     */
    public function checkAuditLogAccess(AuditLog $auditLog, SecurityAction $action): void;

    public function canAccessAuditLog(
        AuditLog $auditLog,
        SecurityAction $action = SecurityAction::VIEW
    ): bool;

    /**
     * Contrôle d'accès au journal d'audit d'une Organization, applicable
     * avant toute requête de liste.
     *
     * @throws AccessDeniedException
     */
    public function checkOrganizationAuditLogAccess(
        Organization $organization,
        SecurityAction $action
    ): void;

    /*
     * ============================================================
     * PERMISSIONS
     * ============================================================
     */

    public function hasPermission(string $permission): bool;

    /**
     * @throws AccessDeniedException
     */
    public function checkPermission(string $permission): void;
}
