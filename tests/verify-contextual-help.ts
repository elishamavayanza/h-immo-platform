import { buildAppRoutes } from '../assets/react/app/routes/appRoutes.config.ts';
import { resolveSidebar } from '../assets/react/app/layout/MainLayout/sidebar/sidebar.config.ts';
import { buildContextualHelpInventory, filterHelpQuestions, resolveContextualHelp } from '../assets/react/features/shared/help/services/contextualHelpRegistry.ts';
import { helpButtonPositionToPixels, normalizeHelpButtonPosition, parseHelpButtonPosition } from '../assets/react/features/shared/help/utils/helpButtonPosition.ts';

const roles = [
    { platformRole: 'super_admin' as const, organizationRole: null },
    { platformRole: null, organizationRole: 'patron' as const },
    { platformRole: null, organizationRole: 'admin_immobilier' as const },
    { platformRole: null, organizationRole: 'admin_ville' as const },
];
const inventory = buildContextualHelpInventory();
let checks = 0;
function assert(condition: unknown, message: string): asserts condition {
    checks++;
    if (!condition) throw new Error(`Échec contrôle ${checks}: ${message}`);
}

for (const { platformRole, organizationRole } of roles) {
    for (const route of buildAppRoutes()) {
        const hasAccess = resolveSidebar(platformRole, organizationRole, true).some((item) => route.path === item.path || Boolean(item.path && route.path.startsWith(`${item.path}/`)));
        if (!hasAccess) continue;
        const help = resolveContextualHelp(route.path, platformRole, organizationRole);
        assert(help !== null, `aide absente pour ${route.path} (${platformRole ?? organizationRole})`);
        assert(help.questions.length > 0 && help.questions.length <= 20, `nombre de questions invalide pour ${route.path}`);
    }
}

for (const page of inventory) {
    assert(page.roles.length > 0, `aucun rôle associé à ${page.path}`);
    assert(page.questions.length <= (page.path === '/app/patrimoine' ? 24 : 20), `trop de questions dans le référentiel ${page.path}`);
    assert(new Set(page.questions.map(({ id }) => id)).size === page.questions.length, `identifiants FAQ dupliqués sur ${page.path}`);
    assert(page.questions.every(({ roles: questionRoles }) => !questionRoles || questionRoles.every((role) => page.roles.includes(role))), `une question dépasse les rôles autorisés sur ${page.path}`);
}

assert(resolveContextualHelp('/app/patrimoine/villes/abc/parcelles/xyz', null, 'patron')?.path === '/app/patrimoine', 'un drill-down patrimoine conserve son aide');
const patrimoineAdminOverview = resolveContextualHelp('/app/patrimoine', null, 'admin_immobilier');
assert(patrimoineAdminOverview?.title === 'Patrimoine · Vue d’ensemble' && patrimoineAdminOverview.questions.some((question) => question.id === 'property-adminimmo-actions'), 'ADMIN_IMMOBILIER voit l’aide générale de son rôle sur la vue d’ensemble');
assert((patrimoineAdminOverview?.questions.length ?? 99) < 10, 'la FAQ de la vue d’ensemble ne déroule pas toutes les questions Patrimoine');
assert(!patrimoineAdminOverview?.questions.some((question) => question.id === 'property-nested-spaces'), 'la vue d’ensemble n’affiche pas les questions spécifiques aux unités');
const patrimoineAdminCities = resolveContextualHelp('/app/patrimoine/villes', null, 'admin_immobilier');
assert(patrimoineAdminCities?.title === 'Patrimoine · Villes' && patrimoineAdminCities.questions.some((question) => question.id === 'property-city-admin-assignment'), 'la FAQ des villes explique la création et l’affectation ADMIN_VILLE');
const patrimoineAdminParcels = resolveContextualHelp('/app/patrimoine/villes/city-uuid/parcelles', null, 'admin_immobilier');
assert(patrimoineAdminParcels?.title === 'Patrimoine · Parcelles' && patrimoineAdminParcels.questions.some((question) => question.id === 'property-map'), 'la FAQ suit la navigation vers les parcelles');
assert(!patrimoineAdminParcels?.questions.some((question) => question.id === 'property-household'), 'la FAQ des parcelles masque les questions locatives hors contexte');
const patrimoineAdminBuildings = resolveContextualHelp('/app/patrimoine/villes/city-uuid/parcelles/parcel-uuid/batiments', null, 'admin_immobilier');
assert(patrimoineAdminBuildings?.title === 'Patrimoine · Bâtiments' && patrimoineAdminBuildings.questions.some((question) => question.id === 'property-access'), 'la FAQ suit la navigation vers les bâtiments');
const patrimoineAdminUnits = resolveContextualHelp('/app/patrimoine/villes/city-uuid/parcelles/parcel-uuid/batiments/building-uuid/unites/unit-uuid', null, 'admin_immobilier');
assert(patrimoineAdminUnits?.title === 'Patrimoine · Unités' && patrimoineAdminUnits.questions.some((question) => question.id === 'property-nested-spaces'), 'la FAQ suit la navigation vers une unité');
const patrimoinePatron = resolveContextualHelp('/app/patrimoine', null, 'patron');
assert(patrimoinePatron?.questions.some((question) => question.id === 'property-patron-scope') && !patrimoinePatron.questions.some((question) => question.id === 'property-adminimmo-actions'), 'le PATRON reçoit une FAQ en lecture seule, adaptée à son rôle');
const patrimoineAdminVille = resolveContextualHelp('/app/patrimoine', null, 'admin_ville');
assert(patrimoineAdminVille?.title === 'Patrimoine · Parcelles' && patrimoineAdminVille.questions.some((question) => question.id === 'property-adminville-scope') && !patrimoineAdminVille.questions.some((question) => question.id === 'property-adminimmo-actions'), 'l’ADMIN_VILLE reçoit l’aide de son périmètre affecté');
assert(resolveContextualHelp('/app/depenses/gestion', null, 'admin_ville')?.title === 'Gestion des dépenses', 'la gestion des dépenses a une aide distincte de la trésorerie');
assert(resolveContextualHelp('/app/personnel', null, 'admin_ville') === null, 'une page interdite n’expose pas une aide d’action');
assert(resolveContextualHelp('/login', null, null)?.title === 'Connexion', 'la connexion possède une aide publique');
assert(resolveContextualHelp('/app/access-denied', null, null)?.title === 'Accès refusé', 'la page accès refusé possède une aide');
assert(resolveContextualHelp('/app/organisation', null, 'admin_ville') === null, 'la fiche organisation n’expose pas son aide au rôle ville');
assert(resolveContextualHelp('/app/publications/123e4567-e89b-42d3-a456-426614174000', null, 'admin_ville')?.title === 'Détail d’une publication', 'le détail des publications possède une aide dédiée');
assert(resolveContextualHelp('/app/publications/123e4567-e89b-42d3-a456-426614174000', 'super_admin', null) === null, 'SUPER_ADMIN ne reçoit pas les publications destinées aux organisations');
assert(resolveContextualHelp('/settings/manuel', 'super_admin', null)?.title === 'Manuel d’utilisation', 'le manuel possède une aide dédiée pour les rôles plateforme');
const searched = filterHelpQuestions(inventory.find((page) => page.path === '/app/patrimoine')!.questions, 'GPS');
assert(searched.length === 1 && searched[0].id === 'property-map', 'la recherche porte sur le contenu des réponses');
const unaccentedSearch = filterHelpQuestions(inventory.find((page) => page.path === '/app/location/loyers')!.questions, 'echeance');
assert(unaccentedSearch.some((question) => question.id === 'rents-payment'), 'la recherche FAQ ignore les accents');
const settingsHelp = resolveContextualHelp('/settings', null, 'patron');
assert(settingsHelp?.questions.some((question) => question.id === 'settings-compact-tables'), 'les préférences de tableaux ont une explication dédiée');
assert(settingsHelp?.questions.some((question) => question.id === 'settings-notifications'), 'les notifications toast et e-mail sont expliquées séparément');
const profilePhotoHelp = resolveContextualHelp('/profile', null, 'patron')?.questions.find((question) => question.id === 'profile-photo');
assert(profilePhotoHelp?.answer.includes('sans actualiser la page'), 'le FAQ explique la mise à jour immédiate de la photo dans le menu latéral');
assert(filterHelpQuestions(inventory[0].questions, 'terme qui ne figure pas').length === 0, 'la recherche peut retourner un état vide');
const savedPosition = normalizeHelpButtonPosition(640, 400, 1280, 800);
assert(Math.abs(savedPosition.x - 0.5) < 0.001 && Math.abs(savedPosition.y - 0.5) < 0.001, 'le glissement enregistre une position normalisée');
assert(helpButtonPositionToPixels(savedPosition, 390, 844).x === 195, 'la position mémorisée s’adapte au redimensionnement');
const clamped = normalizeHelpButtonPosition(-100, 2000, 390, 844);
assert(clamped.x * 390 >= 36 && clamped.y * 844 <= 808, 'le bouton reste dans les limites de l’écran');
const leftTop = normalizeHelpButtonPosition(-10000, -10000, 320, 568);
assert(leftTop.x * 320 >= 36 && leftTop.y * 568 >= 36, 'un glissement hors écran vers le coin supérieur gauche reste visible');
const rightBottom = normalizeHelpButtonPosition(10000, 10000, 320, 568);
assert(rightBottom.x * 320 <= 284 && rightBottom.y * 568 <= 532, 'un glissement hors écran vers le coin inférieur droit reste visible');
assert(parseHelpButtonPosition('{"x":0.25,"y":0.75}')?.y === 0.75, 'la position mémorisée est rechargée');
assert(parseHelpButtonPosition('{malformed') === null && parseHelpButtonPosition('{"x":"left","y":2}') === null, 'une position invalide est ignorée');

console.log(`OK — ${checks} contrôles d’inventaire, permissions, routes profondes et recherche.`);
