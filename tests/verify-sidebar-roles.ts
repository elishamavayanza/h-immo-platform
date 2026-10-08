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
 * MENU PLAT (règle de conception, cf. `sidebar.config.ts`) : une relation
 * 1─N du modèle de données n'est PAS un sous-menu — un écran n'a de place
 * au menu que s'il répond à une question posée indépendamment de son
 * parent. Aucune entrée de ce menu ne porte donc de `children`, les
 * anciens identifiants de sous-items (`patrimoine-villes`, `location-baux`,
 * `personnel-affectations`, `administration-villes`, …) ont disparu, et
 * `Loyers` est resté une entrée indépendante pendant que `Baux` et
 * `Paiements` ont été repliés en drill-down. Un URL de drill-down profond
 * reste COUVERT par son entrée de premier niveau (`isPathInMenu`) et servi
 * par le splat de route : le lien reste partageable, seul le menu s'aplatit.
 *
 * Deux régressions couvertes par la seconde partie :
 *   - la correspondance par égalité de chaîne laissait une route imbriquée
 *     sans item actif ; désormais une entrée couvre ses routes filles
 *     (`/app/dashboard` reste actif sur `/app/dashboard/42`) ;
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
 * Quatrième partie — menu plat et drill-down. Une URL profonde
 * (`/app/patrimoine/villes/12/parcelles/4`) doit rester couverte par
 * l'entrée de premier niveau correspondante pendant que `Baux` /
 * `Paiements` ne s'ouvrent plus seuls (drill-down uniquement).
 *
 * Cinquième partie — icônes. Chaque entrée du menu est identifiée en
 * rail (< 768px, libellé masqué) ; en particulier Dépenses et Loyers, les
 * deux destinations financières de premier niveau, ne doivent PAS partager
 * la même icône.
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

const idsOf = (menu: ReturnType<typeof resolveSidebar>): string[] => menu.map(item => item.id);

const PATRON = resolveSidebar(null, 'patron' as OrganizationRole);
const ADMIN_IMMOBILIER = resolveSidebar(null, 'admin_immobilier' as OrganizationRole);
const ADMIN_VILLE = resolveSidebar(null, 'admin_ville' as OrganizationRole);
const PLATFORM = resolveSidebar('super_admin' as PlatformRole, 'admin_ville' as OrganizationRole);

console.log('\n=== Structure du menu par rôle (menu plat) ===\n');

// Menu attendu, ordre compris. « Tableau de bord » est toujours la première
// entrée (écran de synthèse demandé par le cahier des charges), et
// « Vitrine » est une feuille directe comme Dépenses.
const EXPECTED_PATRON = ['dashboard', 'rapports', 'patrimoine', 'location-locataires', 'location-loyers', 'depenses', 'vitrine', 'personnel', 'administration'];
const EXPECTED_ADMIN_IMMOBILIER = ['dashboard', 'rapports', 'patrimoine', 'location-locataires', 'location-loyers', 'depenses', 'vitrine', 'personnel'];
const EXPECTED_ADMIN_VILLE = ['dashboard', 'rapports', 'patrimoine', 'location-locataires', 'location-loyers', 'depenses', 'vitrine'];

check(
    `PATRON : menu exact de ${EXPECTED_PATRON.length} entrées (reçu ${PATRON.length})`,
    JSON.stringify(idsOf(PATRON)) === JSON.stringify(EXPECTED_PATRON),
    idsOf(PATRON).join(', ')
);
check(
    `ADMIN_IMMOBILIER : menu exact de ${EXPECTED_ADMIN_IMMOBILIER.length} entrées, Personnel inclus`,
    JSON.stringify(idsOf(ADMIN_IMMOBILIER)) === JSON.stringify(EXPECTED_ADMIN_IMMOBILIER),
    idsOf(ADMIN_IMMOBILIER).join(', ')
);
check(
    `ADMIN_VILLE : menu exact de ${EXPECTED_ADMIN_VILLE.length} entrées`,
    JSON.stringify(idsOf(ADMIN_VILLE)) === JSON.stringify(EXPECTED_ADMIN_VILLE),
    idsOf(ADMIN_VILLE).join(', ')
);
check(
    `SUPER_ADMIN : menu plateforme ${PLATFORM.length} entrées, aucun rôle métier ne l'alourdit (reçu ${PLATFORM.length})`,
    PLATFORM.length === 5 && idsOf(PLATFORM)[0] === 'plateforme-dashboard',
    idsOf(PLATFORM).join(', ')
);
check('Sans rôle : menu vide', resolveSidebar(null, null).length === 0);

// ── Menu plat : une relation 1─N du modèle ne crée PAS de sous-menu ──
const ALL_MENUS = [PATRON, ADMIN_IMMOBILIER, ADMIN_VILLE, PLATFORM];
const withChildren = ALL_MENUS.filter(menu => menu.some(item => item.children !== undefined));

check(
    'Menu plat : AUCUNE entrée ne porte de children (4 menus, aucun sous-menu)',
    withChildren.length === 0,
    withChildren.map(menu => menu[0]?.id).join(', ')
);

// Identifiants des anciennes entrées de sous-menu : « Villes », « Parcelles »,
// « Bâtiments », « Unités » (Patrimoine), « Baux », « Paiements » (Location), la
// catégorie « Location » et « Ouvriers », « Affectations » (Personnel), « Équipe »,
// « Villes assignées » (Administration) — toutes devenues du drill-down.
const LEGACY_SUB_ITEM_IDS = [
    'patrimoine-villes', 'patrimoine-parcelles', 'patrimoine-batiments', 'patrimoine-unites',
    'location', 'location-baux', 'location-paiements',
    'personnel-ouvriers', 'personnel-affectations',
    'administration-equipe', 'administration-villes',
];
const everyId = ALL_MENUS.flatMap(menu => idsOf(menu));
const strayLegacy = LEGACY_SUB_ITEM_IDS.filter(id => everyId.includes(id));

check(
    `Plus aucun identifiant de sous-item historique (${LEGACY_SUB_ITEM_IDS.length} id)`,
    strayLegacy.length === 0,
    strayLegacy.join(', ')
);
check(
    'Patrimoine reste UNE entrée, Villes/Parcelles/Bâtiments/Unités n\'apparaissent plus',
    hasTopLevelItem(PATRON, 'patrimoine')
        && !everyId.includes('patrimoine-villes')
        && !everyId.includes('patrimoine-unites')
);

console.log('\n=== Personnel (Worker) — matrice checkAdminXxxAction ===\n');

check('ADMIN_IMMOBILIER voit l\'entrée Personnel', hasTopLevelItem(ADMIN_IMMOBILIER, 'personnel'));
check('ADMIN_IMMOBILIER accède à /app/personnel/ouvriers (drill-down couvert)', isPathInMenu(ADMIN_IMMOBILIER, '/app/personnel/ouvriers'));
check('ADMIN_IMMOBILIER accède à /app/personnel/affectations (drill-down couvert)', isPathInMenu(ADMIN_IMMOBILIER, '/app/personnel/affectations'));

check('ADMIN_VILLE ne voit PAS l\'entrée Personnel (VIEW_WORKER seul)', !hasTopLevelItem(ADMIN_VILLE, 'personnel'));
check('ADMIN_VILLE est refusé sur /app/personnel/ouvriers', !isPathInMenu(ADMIN_VILLE, '/app/personnel/ouvriers'));
check('ADMIN_VILLE est refusé sur /app/personnel/affectations', !isPathInMenu(ADMIN_VILLE, '/app/personnel/affectations'));

check('PATRON voit l\'entrée Personnel', hasTopLevelItem(PATRON, 'personnel'));
check('Tous les rôles organisationnels voient Rapports', [PATRON, ADMIN_IMMOBILIER, ADMIN_VILLE].every(menu => hasTopLevelItem(menu, 'rapports')));
check('SUPER_ADMIN voit son entrée Rapports plateforme', hasTopLevelItem(PLATFORM, 'plateforme-rapports'));
check('PATRON accède à /app/personnel/ouvriers', isPathInMenu(PATRON, '/app/personnel/ouvriers'));
check('PATRON accède à /app/personnel', isPathInMenu(PATRON, '/app/personnel'));

console.log('\n=== Administration — réservée au PATRON ===\n');

check('PATRON accède à /app/administration/equipe (drill-down couvert)', isPathInMenu(PATRON, '/app/administration/equipe'));
check('PATRON accède à /app/administration/villes (drill-down couvert)', isPathInMenu(PATRON, '/app/administration/villes'));
check('ADMIN_IMMOBILIER est refusé sur /app/administration/equipe', !isPathInMenu(ADMIN_IMMOBILIER, '/app/administration/equipe'));
check('ADMIN_IMMOBILIER est refusé sur /app/administration/villes', !isPathInMenu(ADMIN_IMMOBILIER, '/app/administration/villes'));
check('ADMIN_VILLE est refusé sur /app/administration/equipe', !isPathInMenu(ADMIN_VILLE, '/app/administration/equipe'));

console.log('\n=== SUPER_ADMIN : aucune entrée métier d\'organisation ===\n');

check('SUPER_ADMIN ne voit ni Patrimoine ni Personnel', !hasTopLevelItem(PLATFORM, 'patrimoine') && !hasTopLevelItem(PLATFORM, 'personnel'));
check('SUPER_ADMIN accède à /app/admin/organisations', isPathInMenu(PLATFORM, '/app/admin/organisations'));
check('SUPER_ADMIN accède à /app/admin/rapports', isPathInMenu(PLATFORM, '/app/admin/rapports'));
check('Les rôles organisationnels accèdent à /app/rapports', [PATRON, ADMIN_IMMOBILIER, ADMIN_VILLE].every(menu => isPathInMenu(menu, '/app/rapports')));
check('SUPER_ADMIN n\'accède pas à /app/personnel/ouvriers', !isPathInMenu(PLATFORM, '/app/personnel/ouvriers'));

console.log('\n=== Atterrissage par défaut (redirection /app) ===\n');

check('ADMIN_VILLE atterrit sur /app/dashboard', defaultPathFor(null, 'admin_ville' as OrganizationRole) === '/app/dashboard', defaultPathFor(null, 'admin_ville' as OrganizationRole));
check('SUPER_ADMIN atterrit sur /app/admin/dashboard', defaultPathFor('super_admin' as PlatformRole, null) === '/app/admin/dashboard', defaultPathFor('super_admin' as PlatformRole, null));
check('Sans rôle : redirection vers la page accès non prévu', defaultPathFor(null, null) === '/app/access-denied');

// ============================================================
// ROUTE ACTIVE — correspondance par segment
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

check(
    'Entrée seule : /app/dashboard active dashboard',
    JSON.stringify(ids('/app/dashboard')) === JSON.stringify(['dashboard']),
    JSON.stringify(ids('/app/dashboard'))
);
check(
    'Patrimoine est une page : /app/patrimoine active patrimoine',
    JSON.stringify(ids('/app/patrimoine')) === JSON.stringify(['patrimoine']),
    JSON.stringify(ids('/app/patrimoine'))
);
check(
    'Drill-down : /app/patrimoine/villes reste dans Patrimoine',
    JSON.stringify(ids('/app/patrimoine/villes')) === JSON.stringify(['patrimoine']),
    JSON.stringify(ids('/app/patrimoine/villes'))
);
check(
    'Drill-down profond : /app/patrimoine/villes/12/parcelles/4 reste dans Patrimoine',
    JSON.stringify(ids('/app/patrimoine/villes/12/parcelles/4')) === JSON.stringify(['patrimoine']),
    JSON.stringify(ids('/app/patrimoine/villes/12/parcelles/4'))
);
check(
    'Détail imbriqué : /app/location/loyers/12 reste dans Loyers',
    JSON.stringify(ids('/app/location/loyers/12')) === JSON.stringify(['location-loyers']),
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
    'Deux entrées ne s\'allument jamais ensemble sur un préfixe ambigu',
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
for (const menu of ALL_MENUS) collectPaths(menu);

const missingRoutes = everyLeaf.filter(path => !routePaths.includes(path));

check(
    `Chaque entrée du menu a une route (${everyLeaf.length} entrées, ${ROUTES.length} routes)`,
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
    'findAppRoute retrouve une entrée connue',
    findAppRoute('/app/patrimoine')?.id === 'patrimoine',
    String(findAppRoute('/app/patrimoine')?.id)
);
check('findAppRoute ignore un chemin inconnu', findAppRoute('/app/inexistant') === undefined);
check(
    'findAppRoute ne connaît PAS une URL de drill-down comme route exacte (couverture assurée par la garde/splat)',
    findAppRoute('/app/patrimoine/villes/12/parcelles/4') === undefined,
    String(findAppRoute('/app/patrimoine/villes/12/parcelles/4')?.id)
);
check(
    'Chemin relatif : /app/patrimoine → patrimoine',
    toRelativeAppPath('/app/patrimoine') === 'patrimoine',
    toRelativeAppPath('/app/patrimoine')
);
check(
    'Chemin de route avec splat : /app/patrimoine → patrimoine/* (drill-down servis)',
    toRelativeAppRoutePath('/app/patrimoine') === 'patrimoine/*',
    toRelativeAppRoutePath('/app/patrimoine')
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
// MENU PLAT — drill-down couvert, entrées repliées
// ============================================================
// La règle du menu plat : une relation 1─N ne crée pas de sous-menu. Ce qui
// est replié reste néanmoins joignable : l'URL profonde du drill-down est
// couverte par l'entrée de premier niveau (lien partageable), pendant
// qu'une URL pour laquelle l'enfant n'a PAS de sens hors de son parent
// (Baux, Paiements) n'est plus au menu.

console.log('=== Menu plat : drill-down couvert par l\'entrée de premier niveau ===\n');

check(
    'Patrimoine : /app/patrimoine/villes/12/parcelles/4 reste couvert',
    isPathInMenu(PATRON, '/app/patrimoine/villes/12/parcelles/4')
);
check(
    'Locataires : /app/location/locataires/7/baux reste couvert',
    isPathInMenu(PATRON, '/app/location/locataires/7/baux')
);
check(
    'Loyers : /app/location/loyers/3/paiements reste couvert',
    isPathInMenu(PATRON, '/app/location/loyers/3/paiements')
);
check(
    'Personnel : /app/personnel/ouvriers/9/affectations reste couvert',
    isPathInMenu(PATRON, '/app/personnel/ouvriers/9/affectations')
);
check(
    'Administration : /app/administration/equipe/2/villes reste couvert',
    isPathInMenu(PATRON, '/app/administration/equipe/2/villes')
);
check(
    'ADMIN_VILLE : le drill-down Patrimoine est aussi couvert (même entrée)',
    isPathInMenu(ADMIN_VILLE, '/app/patrimoine/villes/12/parcelles/4')
);

check('Loyers est une entrée indépendante pour PATRON', hasTopLevelItem(PATRON, 'location-loyers'));
check('Loyers est une entrée indépendante pour ADMIN_IMMOBILIER', hasTopLevelItem(ADMIN_IMMOBILIER, 'location-loyers'));
check('Loyers est une entrée indépendante pour ADMIN_VILLE', hasTopLevelItem(ADMIN_VILLE, 'location-loyers'));

check('Baux ne s\'ouvre plus seul : /app/location/baux hors menu', !isPathInMenu(PATRON, '/app/location/baux'));
check('Paiements ne s\'ouvre plus seul : /app/location/paiements hors menu', !isPathInMenu(PATRON, '/app/location/paiements'));
check('Location n\'existe plus comme catégorie : /app/location hors menu', !isPathInMenu(PATRON, '/app/location'));

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

// `isBranchActive` : arbre de test minimal (2 niveaux) — la propriété reste
// dans le type même si le menu actuel est plat.
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

// ============================================================
// ICÔNES — chaque entrée est identifiable en rail
// ============================================================
// Sous 768px le menu se réduit à un rail de 72px : le libellé disparaît et
// seule l'icône subsiste. Une entrée sans icône y devient un composant sans
// aucun visuel. Le menu étant plat, TOUTE entrée est de premier niveau :
// chacune doit porter une icône. Dépenses et Loyers, les deux destinations
// financières de premier niveau, ne doivent PAS la partager (collision
// visuelle qui referait la confusion entre « ce qui rentre » et « ce qui
// sort »).

console.log('=== Icônes : chaque entrée du menu est identifiable en rail ===\n');

const iconsSrc = readFileSync(
    new URL('../assets/react/app/layout/MainLayout/sidebar/sidebar.icons.tsx', import.meta.url),
    'utf-8'
);
const declaredIcons = new Set(
    [...iconsSrc.slice(iconsSrc.indexOf('SIDEBAR_ICON_MAP')).matchAll(/^\s{4}'?([a-z-]+)'?:\s*Icon/gm)]
        .map(match => match[1])
);

const flatItems: { id: string; icon?: string }[] = [];
for (const menu of ALL_MENUS) {
    for (const item of menu) flatItems.push({ id: item.id, icon: item.icon });
}

const withoutIcon = flatItems.filter(item => item.icon === undefined).map(item => item.id);
const unknownIcon = flatItems
    .filter(item => item.icon !== undefined && !declaredIcons.has(item.icon))
    .map(item => `${item.id} → ${item.icon}`);

check(
    `Chaque entrée du menu porte une icône (${flatItems.length} entrées)`,
    withoutIcon.length === 0,
    withoutIcon.join(', ')
);
check(
    `Chaque nom d'icône du menu existe dans SIDEBAR_ICON_MAP (${declaredIcons.size} icônes)`,
    unknownIcon.length === 0,
    unknownIcon.join(', ')
);

const iconOf = (id: string, menu: typeof PATRON = PATRON): string | undefined =>
    menu.find(item => item.id === id)?.icon;

check(
    'Dépenses et Loyers portent des icônes distinctes (deux destinations financières)',
    iconOf('depenses') !== undefined && iconOf('depenses') !== iconOf('location-loyers'),
    `depenses=${String(iconOf('depenses'))}, loyers=${String(iconOf('location-loyers'))}`
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
