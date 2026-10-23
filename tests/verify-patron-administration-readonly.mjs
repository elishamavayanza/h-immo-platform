import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const read = (path) => readFile(new URL(path, import.meta.url), 'utf8');
const [security, profile, page, table, hook] = await Promise.all([
    read('../src/Security/SecurityService.php'),
    read('../assets/react/features/patron/administration/components/OrganizationProfileCard.tsx'),
    read('../assets/react/features/patron/administration/pages/AdministrationPage.tsx'),
    read('../assets/react/features/patron/administration/components/TeamTable.tsx'),
    read('../assets/react/features/patron/administration/hooks/useAdministration.ts'),
]);

const patronPolicy = security.match(/private function checkPatronAction\(SecurityAction \$action\): void([\s\S]*?)\n    }/);
assert.ok(patronPolicy, 'La politique de permissions PATRON doit être trouvée.');
for (const allowed of ['VIEW_ORGANIZATION', 'MANAGE_SHOWCASE', 'VIEW_USER', 'CREATE_CITY_ADMIN', 'CREATE_ORGANIZATION_ADMIN']) {
    assert.ok(patronPolicy[1].includes(`SecurityAction::${allowed}`), `Le PATRON doit avoir ${allowed}.`);
}
assert.ok(patronPolicy[1].includes('SecurityAction::UPDATE,'), 'Le PATRON conserve la mise à jour organisationnelle déjà prévue.');
for (const forbidden of ['MANAGE_ORGANIZATION,', 'MANAGE_USERS,', 'ASSIGN_USER_CITY,']) {
    assert.ok(!patronPolicy[1].includes(forbidden), `La politique PATRON ne doit pas accorder ${forbidden}.`);
}

assert.match(profile, /Consultation seule pour votre rôle/);
assert.match(profile, /readOnly/);
assert.doesNotMatch(profile, /onSubmit=|\bsave\(|>Enregistrer</);

assert.match(page, /Inviter un membre/);
for (const forbiddenControl of ['RoleAssignmentsPanel', 'CityAssignmentModal', 'submitEdit', 'submitSuspend', 'handleReactivate', 'onEdit=', 'onSuspend=']) {
    assert.ok(!page.includes(forbiddenControl), `La page Administration ne doit pas exposer ${forbiddenControl}.`);
}

assert.doesNotMatch(table, /PopoverMenu|key: 'actions'|onEdit|onSuspend|onReactivate|onManageCities/);
assert.match(table, /Villes attribuées/);
assert.match(hook, /listRoleAssignments\(organizationUuid\)/);
assert.match(hook, /administrationService\.createAdmin\(/);
for (const forbiddenMutation of ['administrationService.updateMember(', 'administrationService.suspendMember(', 'administrationService.reactivateMember(', 'administrationService.revokeRole(']) {
    assert.ok(!hook.includes(forbiddenMutation), `Le hook Administration ne doit pas appeler ${forbiddenMutation}.`);
}

console.log('Administration PATRON : actions visibles alignées sur la matrice serveur.');
