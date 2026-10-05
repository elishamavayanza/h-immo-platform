/**
 * Vérifie que le menu latéral (`sidebar.config.ts`) reflète la matrice de
 * rôles du backend `SecurityService::checkAdminImmobilierAction()` /
 * `checkAdminVilleAction()`, ET que la route active est résolue
 * correctement (`sidebar.active.ts`).
 *
 * Régression connue : `admin_immobilier` recevait le même menu 4 entrées
 * que `admin_ville`, sans Personnel, alors que le backend lui accorde
 * VIEW/CREATE/UPDATE/DELETE sur Worker. Le test confronte explicitement le
 * menu attendu à la matrice backend plutôt qu'à une relecture manuelle.
 *
 * Deux régressions couvertes par la seconde partie :
 *   - la correspondance par égalité de chaîne laissait une route imbriquée
 *     sans item actif, et un parent sans `path` ne remontait jamais l'état
 *     actif de ses enfants (section « éteinte » alors qu'on est dedans) ;
 *   - `/app/location` ne doit pas activer `/app/location-paiements`
 *     (faux positif du préfixe simple).
 *
 * Troisième partie — routage. Régression plus grave encore : aucune feuille
 * du menu n'était enregistrée dans `AppRoutes.tsx`, donc `/app` redirigeait
 * vers une destination inexistante, qui retombait sur `path="*"` vers `/app` :
 * boucle de redirection, back-office entièrement mort. Ces contrôles
 * exigent que la table des routes couvre le menu et que chaque destination
 * d'atterrissage soit routée (invariant anti-boucle).
 *
 * Quatrième partie — icônes. Sous 768px le menu devient un rail de 72px où le
 * libellé est masqué : une entrée sans icône y est un composant sans aucun
 * visuel. Seules les entrées de premier niveau étaient iconifiées, le rail
 * n'offrait donc que trois destinations.
 *
 * Modules purs (ni React ni JSX), exécutables par Node 24 (type
 * stripping) :
 *
 *   node tests/verify-sidebar-roles.ts
 */

import {
    defaultPathFor,
    isPathInMenu,
    resolveSidebar,
} from '../assets/react/app/layout/MainLayout/sidebar/sidebar.config.ts';
import {
    isRouteMatch,
    resolveActiveMenu,
} from '../assets/react/app/layout/MainLayout/sidebar/sidebar.active.ts';
import {
    isFlyoutAvailable,
    isLabelHidden,
    isBranchActive,
} from '../assets/react/hook-components/Navigation/Sidebar/sidebar.state.ts';
import {
    ACCESS_DENIED_PATH,
    APP_ROOT,
    buildAppRoutes,
    buildLandingPaths,
    findAppRoute,
    toRelativeAppPath,
    toRelativeAppRoutePath,
} from '../assets/react/app/routes/appRoutes.config.ts';
import type { OrganizationRole, PlatformRole } from '../assets/services/api/api.types.ts';
import { readFileSync } from 'node:fs';

let checks = 0;
const failures: string[] = [];

function check(label: string, ok: boolean, detail = ''): void {
    checks += 1;

    if (ok) {
        console.log(`  [OK]   ${label}`);

        return;
    }

    failures.push(label + (detail !== '' ? ` (${detail})` : ''));
    console.log(`  [FAIL] ${label}${detail !== '' ? ` -> ${detail}` : ''}`);
}

const hasTopLevelItem = (menu: ReturnType<typeof resolveSidebar>, id: string): boolean =>
    menu.some(item => item.id === id);

const PATRON = resolveSidebar(null, 'patron' as OrganizationRole);
const ADMIN_IMMOBILIER = resolveSidebar(null, 'admin_immobilier' as OrganizationRole);
const ADMIN_VILLE = resolveSidebar(null, 'admin_ville' as OrganizationRole);
const PLATFORM = resolveSidebar('super_admin' as PlatformRole, 'admin_ville' as OrganizationRole);

console.log('\n=== Structure du menu par rôle ===\n');

// Volontaires documentés dans `sidebar.config.ts` : « Tableau de bord »
// est toujours la première entrée (écran de synthèse demandé par le cahier
// des charges), et « Vitrine » est une feuille directe comme Dépenses.
check(`PATRON : 7 entrées (reçu ${PATRON.length})`, PATRON.length === 7, String(PATRON.length));
check(`ADMIN_IMMOBILIER : 6 entrées, Personnel inclus (reçu ${ADMIN_IMMOBILIER.length})`, ADMIN_IMMOBILIER.length === 6, String(ADMIN_IMMOBILIER.length));
check(`ADMIN_VILLE : 5 entrées (reçu ${ADMIN_VILLE.length})`, ADMIN_VILLE.length === 5, String(ADMIN_VILLE.length));
check(`SUPER_ADMIN : menu plateforme 5 entrées, aucun rôle métier ne l'alourdit (reçu ${PLATFORM.length})`, PLATFORM.length === 5, String(PLATFORM.length));
check('Sans rôle : menu vide', resolveSidebar(null, null).length === 0);

check('PATRON : Tableau de bord en première position', PATRON[0]?.id === 'dashboard', PATRON[0]?.id);
check('ADMIN_IMMOBILIER : Tableau de bord en première position', ADMIN_IMMOBILIER[0]?.id === 'dashboard', ADMIN_IMMOBILIER[0]?.id);
check('ADMIN_VILLE : Tableau de bord en première position', ADMIN_VILLE[0]?.id === 'dashboard', ADMIN_VILLE[0]?.id);

console.log('\n=== Personnel (Worker) — matrice checkAdminXxxAction ===\n');

check('ADMIN_IMMOBILIER voit l\'entrée Personnel', hasTopLevelItem(ADMIN_IMMOBILIER, 'personnel'));
check('ADMIN_IMMOBILIER accède à /app/personnel/ouvriers', isPathInMenu(ADMIN_IMMOBILIER, '/app/personnel/ouvriers'));
check('ADMIN_IMMOBILIER accède à /app/personnel/affectations', isPathInMenu(ADMIN_IMMOBILIER, '/app/personnel/affectations'));

check('ADMIN_VILLE ne voit PAS l\'entrée Personnel (VIEW_WORKER seul)', !hasTopLevelItem(ADMIN_VILLE, 'personnel'));
check('ADMIN_VILLE est refusé sur /app/personnel/ouvriers', !isPathInMenu(ADMIN_VILLE, '/app/personnel/ouvriers'));
check('ADMIN_VILLE est refusé sur /app/personnel/affectations', !isPathInMenu(ADMIN_VILLE, '/app/personnel/affectations'));

check('PATRON voit l\'entrée Personnel', hasTopLevelItem(PATRON, 'personnel'));
check('PATRON accède à /app/personnel/ouvriers', isPathInMenu(PATRON, '/app/personnel/ouvriers'));

console.log('\n=== Administration — réservée au PATRON ===\n');

check('PATRON accède à /app/administration/equipe', isPathInMenu(PATRON, '/app/administration/equipe'));
check('ADMIN_IMMOBILIER est refusé sur /app/administration/equipe', !isPathInMenu(ADMIN_IMMOBILIER, '/app/administration/equipe'));
check('ADMIN_IMMOBILIER est refusé sur /app/administration/villes', !isPathInMenu(ADMIN_IMMOBILIER, '/app/administration/villes'));
check('ADMIN_VILLE est refusé sur /app/administration/equipe', !isPathInMenu(ADMIN_VILLE, '/app/administration/equipe'));

console.log('\n=== SUPER_ADMIN : aucune entrée métier d\'organisation ===\n');

check('SUPER_ADMIN ne voit ni Patrimoine ni Personnel', !hasTopLevelItem(PLATFORM, 'patrimoine') && !hasTopLevelItem(PLATFORM, 'personnel'));
check('SUPER_ADMIN accède à /app/admin/organisations', isPathInMenu(PLATFORM, '/app/admin/organisations'));
check('SUPER_ADMIN n\'accède pas à /app/personnel/ouvriers', !isPathInMenu(PLATFORM, '/app/personnel/ouvriers'));

console.log('\n=== Atterrissage par défaut (redirection /app) ===\n');

check('ADMIN_VILLE atterrit sur /app/dashboard', defaultPathFor(null, 'admin_ville' as OrganizationRole) === '/app/dashboard', defaultPathFor(null, 'admin_ville' as OrganizationRole));
check('SUPER_ADMIN atterrit sur /app/admin/dashboard', defaultPathFor('super_admin' as PlatformRole, null) === '/app/admin/dashboard', defaultPathFor('super_admin' as PlatformRole, null));
check('Sans rôle : redirection vers la page accès non prévu', defaultPathFor(null, null) === '/app/access-denied');

// ============================================================
// ROUTE ACTIVE — correspondance par segment + remontée de section
// ============================================================

console.log('\n=== Route active : correspondance (sidebar.active.ts) ===\n');

check('Égalité simple : /app/dashboard ↔ /app/dashboard', isRouteMatch('/app/dashboard', '/app/dashboard'));
check('Route fille : /app/dashboard ↔ /app/dashboard/42', isRouteMatch('/app/dashboard', '/app/dashboard/42'));
check('Route fille profonde : /app/dashboard ↔ /app/dashboard/42/detail', isRouteMatch('/app/dashboard', '/app/dashboard/42/detail'));
check('Barre finale tolérée : /app/dashboard/ ↔ /app/dashboard', isRouteMatch('/app/dashboard/', '/app/dashboard'));
check('Préfixe simple refusé : /app/location ne matche PAS /app/location-paiements', !isRouteMatch('/app/location', '/app/location-paiements'));
check('Un parent sans route ne matche rien', !isRouteMatch(undefined, '/app/dashboard'));
check('Route non vide requise', !isRouteMatch('', '/app/dashboard'));
check('Chaîne vide sans route active', !isRouteMatch('/app/dashboard', '/app'));

const ids = (pathname: string, menu: typeof PATRON = PATRON): string[] =>
    [...resolveActiveMenu(menu, pathname)].sort();

// Menu réduit à la section Patrimoine, pour tester l'état actif d'un parent
// indépendamment des autres entrées du rôle.
const PATRIMONE_MENU = PATRON.filter(item => item.id === 'patrimoine');

check(
    'Feuille seule : /app/dashboard active dashboard',
    JSON.stringify(ids('/app/dashboard')) === JSON.stringify(['dashboard']),
    JSON.stringify(ids('/app/dashboard'))
);
check(
    'Sous-item : /app/patrimoine/villes active la feuille ET sa section',
    JSON.stringify(ids('/app/patrimoine/villes')) === JSON.stringify(['patrimoine', 'patrimoine-villes']),
    JSON.stringify(ids('/app/patrimoine/villes'))
);
check(
    'Parent sans path : /app/patrimoine seul n\'active rien (ce n\'est pas une page)',
    JSON.stringify(ids('/app/patrimoine')) === JSON.stringify([]),
    JSON.stringify(ids('/app/patrimoine'))
);
check(
    'Parent sans path : il s\'active bien via un de ses enfants',
    resolveActiveMenu(PATRIMONE_MENU, '/app/patrimoine/villes').has('patrimoine')
);
check(
    'Détail imbriqué : /app/location/loyers/12 reste dans Location > Loyers',
    JSON.stringify(ids('/app/location/loyers/12')) === JSON.stringify(['location', 'location-loyers']),
    JSON.stringify(ids('/app/location/loyers/12'))
);
check(
    'Plateforme : /app/admin/audit active la bonne entrée',
    JSON.stringify(ids('/app/admin/audit', PLATFORM)) === JSON.stringify(['plateforme-audit']),
    JSON.stringify(ids('/app/admin/audit', PLATFORM))
);
check(
    'Route hors menu : aucun item actif',
    JSON.stringify(ids('/app/inexistant')) === JSON.stringify([]),
    JSON.stringify(ids('/app/inexistant'))
);
check(
    'Section hors rôle : /app/personnel/ouvriers n\'active rien pour ADMIN_VILLE',
    JSON.stringify(ids('/app/personnel/ouvriers', ADMIN_VILLE)) === JSON.stringify([]),
    JSON.stringify(ids('/app/personnel/ouvriers', ADMIN_VILLE))
);
check(
    'Deux sections ne s\'allument jamais ensemble sur un préfixe ambigu',
    ids('/app/location/loyers').filter(id => id === 'patrimoine').length === 0
);

console.log('\n' + '-'.repeat(60) + '\n');

// ============================================================
// ROUTES DU BACK-OFFICE — aucun lien mort, aucune boucle
// ============================================================
// Régression : aucune feuille du menu n'était enregistrée dans
// `AppRoutes.tsx`. `/app` redirigeait donc vers une destination sans route,
// qui retombait sur `path="*"` → `/app` → … : boucle de redirection, et un
// menu entièrement mort. La table des routes est désormais dérivée du menu
// (`buildAppRoutes`), et ces contrôles prouvent que les deux restent
// synchronisés.

console.log('=== Routes : couverture du menu (appRoutes.config.ts) ===\n');

const ROUTES = buildAppRoutes();
const routePaths = ROUTES.map(route => route.path);
const everyLeaf: string[] = [];
const collectPaths = (menu: typeof PATRON): void => {
    for (const item of menu) {
        if (item.path !== undefined) everyLeaf.push(item.path);
        if (item.children !== undefined) collectPaths(item.children);
    }
};
for (const menu of [PATRON, ADMIN_IMMOBILIER, ADMIN_VILLE, PLATFORM]) collectPaths(menu);

const missingRoutes = everyLeaf.filter(path => !routePaths.includes(path));

check(
    `Chaque feuille du menu a une route (${everyLeaf.length} feuilles, ${ROUTES.length} routes)`,
    missingRoutes.length === 0,
    missingRoutes.join(', ')
);
check('Aucun doublon de route', new Set(routePaths).size === routePaths.length, `${routePaths.length} routes`);
check(
    'Toutes les routes sont sous /app',
    routePaths.every(path => path.startsWith(`${APP_ROOT}/`)),
    routePaths.filter(path => !path.startsWith(`${APP_ROOT}/`)).join(', ')
);
check(
    'findAppRoute retrouve une feuille connue',
    findAppRoute('/app/patrimoine/villes')?.id === 'patrimoine-villes',
    String(findAppRoute('/app/patrimoine/villes')?.id)
);
check('findAppRoute ignore un chemin inconnu', findAppRoute('/app/inexistant') === undefined);
check(
    'Chemin relatif : /app/patrimoine/villes → patrimoine/villes',
    toRelativeAppPath('/app/patrimoine/villes') === 'patrimoine/villes',
    toRelativeAppPath('/app/patrimoine/villes')
);
check(
    'Chemin de route avec splat : /app/patrimoine/villes → patrimoine/villes/*',
    toRelativeAppRoutePath('/app/patrimoine/villes') === 'patrimoine/villes/*',
    toRelativeAppRoutePath('/app/patrimoine/villes')
);
check(
    'La page d\'accès refusé est une destination connue',
    ACCESS_DENIED_PATH === '/app/access-denied' && toRelativeAppPath(ACCESS_DENIED_PATH) === 'access-denied',
    ACCESS_DENIED_PATH
);

// L'invariant anti-boucle : chaque destination d'atterrissage doit être
// routée, sinon `/app` redirige vers le `*` qui redirige vers `/app`.
const landings = buildLandingPaths();
const unroutableLandings = landings.filter(
    path => path !== ACCESS_DENIED_PATH && !routePaths.includes(path)
);

check(
    `Chaque destination d'atterrissage est routée (${landings.length} combinaisons de rôles)`,
    unroutableLandings.length === 0,
    unroutableLandings.join(', ')
);
check(
    'SUPER_ADMIN atterrit sur une route de la plateforme',
    routePaths.includes('/app/admin/dashboard'),
    String(landings[0])
);
check(
    'Un rôle d\'organisation atterrit sur une route du back-office',
    landings.slice(1, 4).every(path => routePaths.includes(path)),
    landings.slice(1, 4).join(', ')
);

console.log('\n' + '-'.repeat(60) + '\n');


// ============================================================
// FLYOUT — règles pures d'affichage (sans DOM)
// ============================================================
// Ces contrôles testent `sidebar.state.ts` : la logique de décision
// (flyout disponible, libellés masqués, parent actif) est pure et
// exécutable par Node, ce qui garantit qu'un refacto ne casse pas
// l'invariant « flyout uniquement en rail desktop/tablette » et
// « parent actif quand une feuille l'est » sans navigateur.

console.log('=== Flyout : règles pures d\'affichage ===\n');

const desktopCollapsed = { isMobile: false, isRail: false, isCollapsed: true, collapsible: true };
const desktopExpanded = { isMobile: false, isRail: false, isCollapsed: false, collapsible: true };
const desktopNotCollapsible = { isMobile: false, isRail: false, isCollapsed: true, collapsible: false };
const mobileRail = { isMobile: true, isRail: true, isCollapsed: false, collapsible: true };
const mobileDrawer = { isMobile: true, isRail: false, isCollapsed: false, collapsible: true };

check('Desktop replié : libellés masqués', isLabelHidden(desktopCollapsed) === true);
check('Desktop déplié : libellés visibles', isLabelHidden(desktopExpanded) === false);
check('Desktop non repliable : libellés visibles', isLabelHidden(desktopNotCollapsible) === false);
check('Mobile rail : libellés masqués', isLabelHidden(mobileRail) === true);
check('Mobile tiroir : libellés visibles', isLabelHidden(mobileDrawer) === false);

check('Desktop replié : flyout disponible', isFlyoutAvailable(desktopCollapsed) === true);
check('Desktop déplié : flyout indisponible', isFlyoutAvailable(desktopExpanded) === false);
check('Desktop non repliable : flyout indisponible', isFlyoutAvailable(desktopNotCollapsible) === false);
check('Mobile rail : flyout indisponible (tiroir disponible)', isFlyoutAvailable(mobileRail) === false);
check('Mobile tiroir : flyout indisponible', isFlyoutAvailable(mobileDrawer) === false);

// `isBranchActive` : arbre de test minimal (2 niveaux)
const testItem: typeof PATRON[0] = {
    id: 'parent',
    label: 'Parent',
    children: [
        { id: 'child1', label: 'Enfant 1' },
        { id: 'child2', label: 'Enfant 2', children: [{ id: 'grandchild', label: 'Petit-enfant' }] },
    ],
} as typeof PATRON[0];

check('Parent actif : isBranchActive(true)', isBranchActive(testItem, new Set(['parent'])));
check('Enfant direct actif : isBranchActive(true)', isBranchActive(testItem, new Set(['child1'])));
check('Petit-enfant actif : isBranchActive(true)', isBranchActive(testItem, new Set(['grandchild'])));
check('Aucun actif : isBranchActive(false)', isBranchActive(testItem, new Set(['inconnu'])) === false);
check('Feuille sans enfants : isBranchActive(false)', isBranchActive({ id: 'leaf', label: 'Feuille' } as typeof PATRON[0], new Set(['inconnu'])) === false);

console.log('\n' + '-'.repeat(60) + '\n');
// Sous 768px le menu se réduit à un rail de 72px : le libellé disparaît et
// seule l'icône subsiste. Une feuille sans icône y devient un composant sans
// aucun visuel, impossible à identifier au doigt ni à distinguer d'une autre.
// Régression : seules les entrées de premier niveau étaient iconifiées, le
// rail n'affichait donc que « Patrimoine / Location / Dépenses » et les
// feuilles，// rail n'affichait donc que « Patrimoine / Location / Dépenses », et les
// feuilles exigeaient d'ouvrir le tiroir pour être atteintes.

console.log('=== Icônes : chaque entrée du menu est identifiable en rail ===\n');

const iconsSrc = readFileSync(
    new URL('../assets/react/app/layout/MainLayout/sidebar/sidebar.icons.tsx', import.meta.url),
    'utf-8'
);
const declaredIcons = new Set(
    [...iconsSrc.slice(iconsSrc.indexOf('SIDEBAR_ICON_MAP')).matchAll(/^\s{4}'?([a-z-]+)'?:\s*Icon/gm)]
        .map(match => match[1])
);

const allItems: { id: string; icon?: string; hasChildren: boolean }[] = [];
const collectItems = (menu: typeof PATRON): void => {
    for (const item of menu) {
        allItems.push({ id: item.id, icon: item.icon, hasChildren: item.children !== undefined });
        if (item.children !== undefined) collectItems(item.children);
    }
};
for (const menu of [PATRON, ADMIN_IMMOBILIER, ADMIN_VILLE, PLATFORM]) collectItems(menu);

const withoutIcon = allItems.filter(item => item.icon === undefined).map(item => item.id);
const unknownIcon = allItems
    .filter(item => item.icon !== undefined && !declaredIcons.has(item.icon))
    .map(item => `${item.id} → ${item.icon}`);
const leavesWithoutIcon = allItems
    .filter(item => !item.hasChildren && item.icon === undefined)
    .map(item => item.id);

check(
    `Chaque entrée du menu porte une icône (${allItems.length} entrées)`,
    withoutIcon.length === 0,
    withoutIcon.join(', ')
);
check(
    'Les sous-menus (feuilles) sont iconifiés, pas seulement les parents',
    leavesWithoutIcon.length === 0,
    leavesWithoutIcon.join(', ')
);
check(
    `Chaque nom d'icône du menu existe dans SIDEBAR_ICON_MAP (${declaredIcons.size} icônes)`,
    unknownIcon.length === 0,
    unknownIcon.join(', ')
);

console.log('\n' + '-'.repeat(60) + '\n');

if (failures.length > 0) {
    console.log(`ECHEC : ${failures.length} / ${checks} contrôles en échec`);

    for (const failure of failures) {
        console.log(`  - ${failure}`);
    }

    process.exit(1);
}

console.log(`SUCCES : ${checks} contrôles passés`);