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
        const hasAccess = resolveSidebar(platformRole, organizationRole).some((item) => route.path === item.path || Boolean(item.path && route.path.startsWith(`${item.path}/`)));
        if (!hasAccess) continue;
        const help = resolveContextualHelp(route.path, platformRole, organizationRole);
        assert(help !== null, `aide absente pour ${route.path} (${platformRole ?? organizationRole})`);
        assert(help.questions.length > 0 && help.questions.length <= 10, `nombre de questions invalide pour ${route.path}`);
    }
}

for (const page of inventory) {
    assert(page.roles.length > 0, `aucun rôle associé à ${page.path}`);
    assert(page.questions.length <= 10, `plus de dix questions sur ${page.path}`);
}

assert(resolveContextualHelp('/app/patrimoine/villes/abc/parcelles/xyz', null, 'patron')?.path === '/app/patrimoine', 'un drill-down patrimoine conserve son aide');
assert(resolveContextualHelp('/app/depenses/gestion', null, 'admin_ville')?.title === 'Gestion des dépenses', 'la gestion des dépenses a une aide distincte de la trésorerie');
assert(resolveContextualHelp('/app/personnel', null, 'admin_ville') === null, 'une page interdite n’expose pas une aide d’action');
assert(resolveContextualHelp('/login', null, null)?.title === 'Connexion', 'la connexion possède une aide publique');
assert(resolveContextualHelp('/app/access-denied', null, null)?.title === 'Accès refusé', 'la page accès refusé possède une aide');
assert(resolveContextualHelp('/app/organisation', null, 'admin_ville') === null, 'la fiche organisation n’expose pas son aide au rôle ville');
const searched = filterHelpQuestions(inventory.find((page) => page.path === '/app/patrimoine')!.questions, 'GPS');
assert(searched.length === 1 && searched[0].id === 'property-map', 'la recherche porte sur le contenu des réponses');
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
