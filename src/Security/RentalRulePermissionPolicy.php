<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\OrganizationRole;

/** Matrice pure et testable des permissions de politique locative. */
final class RentalRulePermissionPolicy
{
    public static function allows(?OrganizationRole $role, SecurityAction $action, bool $isSuperAdmin, bool $hasDelegation): bool
    {
        if ($isSuperAdmin && in_array($action, [
            SecurityAction::PREPARE_RENTAL_RULE,
            SecurityAction::PUBLISH_RENTAL_RULE,
            SecurityAction::DELEGATE_RENTAL_RULE_PUBLICATION,
        ], true)) {
            return false;
        }

        return match ($action) {
            SecurityAction::VIEW_RENTAL_RULE => in_array($role, [OrganizationRole::PATRON, OrganizationRole::ADMIN_IMMOBILIER, OrganizationRole::ADMIN_VILLE], true),
            SecurityAction::PREPARE_RENTAL_RULE => in_array($role, [OrganizationRole::PATRON, OrganizationRole::ADMIN_IMMOBILIER], true),
            SecurityAction::PUBLISH_RENTAL_RULE => $role === OrganizationRole::PATRON
                || ($role === OrganizationRole::ADMIN_IMMOBILIER && $hasDelegation),
            SecurityAction::DELEGATE_RENTAL_RULE_PUBLICATION => $role === OrganizationRole::PATRON,
            default => false,
        };
    }
}
