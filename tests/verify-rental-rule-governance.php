<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Enum\OrganizationRole;
use App\Security\RentalRulePermissionPolicy;
use App\Security\SecurityAction;

$checks = 0;
$failures = [];
$assert = static function (bool $condition, string $description) use (&$checks, &$failures): void {
    ++$checks;
    if (!$condition) {
        $failures[] = $description;
    }
};

$assert(RentalRulePermissionPolicy::allows(OrganizationRole::PATRON, SecurityAction::PUBLISH_RENTAL_RULE, false, false), 'Patron publication');
$assert(RentalRulePermissionPolicy::allows(OrganizationRole::PATRON, SecurityAction::PREPARE_RENTAL_RULE, false, false), 'Patron draft');
$assert(RentalRulePermissionPolicy::allows(OrganizationRole::PATRON, SecurityAction::DELEGATE_RENTAL_RULE_PUBLICATION, false, false), 'Patron delegation management');
$assert(RentalRulePermissionPolicy::allows(OrganizationRole::ADMIN_IMMOBILIER, SecurityAction::PREPARE_RENTAL_RULE, false, false), 'Admin immobilier draft');
$assert(!RentalRulePermissionPolicy::allows(OrganizationRole::ADMIN_IMMOBILIER, SecurityAction::PUBLISH_RENTAL_RULE, false, false), 'Admin immobilier without delegation is refused');
$assert(RentalRulePermissionPolicy::allows(OrganizationRole::ADMIN_IMMOBILIER, SecurityAction::PUBLISH_RENTAL_RULE, false, true), 'Admin immobilier with delegation may publish');
$assert(!RentalRulePermissionPolicy::allows(OrganizationRole::ADMIN_IMMOBILIER, SecurityAction::DELEGATE_RENTAL_RULE_PUBLICATION, false, true), 'Admin immobilier cannot delegate');
$assert(RentalRulePermissionPolicy::allows(OrganizationRole::ADMIN_VILLE, SecurityAction::VIEW_RENTAL_RULE, false, false), 'Admin ville can read');
foreach ([SecurityAction::PREPARE_RENTAL_RULE, SecurityAction::PUBLISH_RENTAL_RULE, SecurityAction::DELEGATE_RENTAL_RULE_PUBLICATION] as $action) {
    $assert(!RentalRulePermissionPolicy::allows(OrganizationRole::ADMIN_VILLE, $action, false, true), 'Admin ville mutation denied: ' . $action->value);
    $assert(!RentalRulePermissionPolicy::allows(OrganizationRole::PATRON, $action, true, true), 'Platform super admin has no implicit mutation: ' . $action->value);
}
$assert(!RentalRulePermissionPolicy::allows(null, SecurityAction::VIEW_RENTAL_RULE, false, false), 'No organization role has no access');

$governance = file_get_contents(dirname(__DIR__) . '/src/Service/Rental/RentalRuleGovernanceService.php');
$security = file_get_contents(dirname(__DIR__) . '/src/Security/SecurityService.php');
$delegationRepository = file_get_contents(dirname(__DIR__) . '/src/Repository/Identity/RentalRulePublicationDelegationRepository.php');
$migration = file_get_contents(dirname(__DIR__) . '/migrations/Version20261020110000RentalRuleGovernance.php');
$ruleMigration = file_get_contents(dirname(__DIR__) . '/migrations/Version20261020100000RentalRuleModel.php');
$settingsUi = file_get_contents(dirname(__DIR__) . '/assets/react/features/shared/account/settings/components/RentalRulesSettings.tsx');
$assert(str_contains($governance, 'wrapInTransaction'), 'Governance mutations are transaction wrapped');
$assert(str_contains($governance, 'PESSIMISTIC_WRITE'), 'Publication/delegation mutations serialize on organization');
$assert(str_contains($governance, 'RENTAL_RULE_PUBLISHED'), 'Publication audit event');
$assert(str_contains($governance, 'RENTAL_RULE_PUBLICATION_DELEGATED'), 'Grant audit event');
$assert(str_contains($governance, 'RENTAL_RULE_PUBLICATION_REVOKED'), 'Revocation audit event');
$assert(str_contains($governance, 'RENTAL_RULE_PUBLICATION_REFUSED'), 'Denied publication attempt is audited');
$assert(str_contains($security, 'checkRentalRuleAccess'), 'Central security service exposes policy guard');
$assert(str_contains($delegationRepository, 'd.revokedAt IS NULL') && str_contains($delegationRepository, 'd.expiresAt > :now'), 'Delegation is checked against current persisted revocation and expiry');
$assert(str_contains($migration, 'FK_RULE_DELEGATION_RECIPIENT_MEMBERSHIP') && str_contains($migration, 'FK_RULE_DELEGATION_GRANTOR_MEMBERSHIP'), 'Delegation database FKs enforce organization membership roles');
$assert(str_contains($migration, 'FK_RULE_DRAFT_VERSION_ORG'), 'Draft publication reference remains in the same organization');
$assert(str_contains($migration, 'trg_rule_draft_immutable_after_publish') && str_contains($migration, 'trg_rule_draft_no_delete'), 'Published drafts cannot be changed or deleted at database level');
$assert(str_contains($migration, 'trg_rule_delegation_immutable_grant') && str_contains($migration, 'trg_rule_delegation_no_delete'), 'Delegation grants are immutable and retained at database level');
$assert(str_contains($ruleMigration, 'trg_rental_rule_immutable_update') && str_contains($ruleMigration, 'trg_rental_rule_immutable_delete'), 'Published rule versions are database-immutable');
$assert(!preg_match('/\b(?:UPDATE|DELETE)\s+(?:rent|payment)\b/i', $migration), 'Governance migration does not alter rents or payments');
$assert(str_contains($settingsUi, 'capabilities?.canManageDelegations'), 'Delegation controls are displayed only when server capabilities allow them');
$assert(str_contains($settingsUi, 'membership.organizationId === currentOrganization.uuid && membership.role === \'admin_immobilier\''), 'Delegation candidates are limited to ADMIN_IMMOBILIER members of the current organization');
$assert(str_contains($settingsUi, 'member.isActive'), 'Inactive members cannot receive a publication delegation from the UI');
$assert(str_contains($settingsUi, 'type="datetime-local"') && str_contains($settingsUi, 'required fullWidth'), 'The UI requires a bounded delegation expiration');
$assert(str_contains($settingsUi, '/delegations/${pendingRevoke.uuid}/revoke'), 'The UI exposes the server-side delegation revocation flow');

if ($failures !== []) {
    fwrite(STDERR, "Échec — {$checks} contrôles :\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Réussi — {$checks} contrôles (matrice pure + vérifications statiques; aucun accès MariaDB).\n";
