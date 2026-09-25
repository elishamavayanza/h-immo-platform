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

interface SecurityServiceInterface
{
    /*
     * ============================================================
     * CURRENT USER
     * ============================================================
     */

    public function getCurrentUser(): User;

    public function isAuthenticated(): bool;

    /*
     * ============================================================
     * ROLES
     * ============================================================
     */

    public function isSuperAdmin(): bool;

    public function isPatron(): bool;

    public function isAdminImmobilier(): bool;

    public function isAdminVille(): bool;

    public function hasRole(string $role): bool;

    public function hasAnyRole(array $roles): bool;

    /*
     * ============================================================
     * ORGANIZATION
     * ============================================================
     */

    public function checkOrganizationAccess(
        Organization $organization,
        SecurityAction $action
    ): void;

    public function checkOrganizationActive(
        Organization $organization
    ): void;

    public function checkCurrentUserOrganizationActive(): void;

    public function belongsToOrganization(
        User $user,
        Organization $organization
    ): bool;

    /*
     * ============================================================
     * CITY
     * ============================================================
     */

    public function checkCityAccess(
        City $city,
        SecurityAction $action
    ): void;

    public function isCityAllowed(
        User $user,
        City $city
    ): bool;

    /*
     * ============================================================
     * PROPERTY (PARCEL / BUILDING / UNIT)
     * ============================================================
     */

    public function checkParcelAccess(
        Parcel $parcel,
        SecurityAction $action
    ): void;

    public function checkBuildingAccess(
        Building $building,
        SecurityAction $action
    ): void;

    public function checkUnitAccess(
        Unit $unit,
        SecurityAction $action
    ): void;

    /*
     * ============================================================
     * TENANT
     * ============================================================
     */

    public function checkTenantAccess(
        Tenant $tenant,
        SecurityAction $action
    ): void;

    /*
     * ============================================================
     * LEASE
     * ============================================================
     */

    public function checkLeaseAccess(
        Lease $lease,
        SecurityAction $action
    ): void;

    /*
     * ============================================================
     * RENT
     * ============================================================
     */

    public function checkRentAccess(
        Rent $rent,
        SecurityAction $action
    ): void;

    /*
     * ============================================================
     * PAYMENT
     * ============================================================
     */

    public function checkPaymentAccess(
        Payment $payment,
        SecurityAction $action
    ): void;

    /*
     * ============================================================
     * AUDIT
     * ============================================================
     */

    public function checkAuditLogAccess(
        AuditLog $auditLog,
        SecurityAction $action
    ): void;

    /*
     * ============================================================
     * PERMISSION
     * ============================================================
     */

    public function hasPermission(string $permission): bool;

    public function checkPermission(string $permission): void;
}
