/**
 * Vérifie que le menu latéral (`sidebar.config.ts`) reflète la matrice de
 * rôles du backend `SecurityService::checkAdminImmobilierAction()` /
 * `checkAdminVilleAction()`.
 *
 * Régression connue : `admin_immobilier` recevait le même menu 4 entrées
 * que `admin_ville`, sans Personnel, alors que le backend lui accorde
 * VIEW/CREATE/UPDATE/DELETE sur Worker. Le test confronte explicitement le
 * menu attendu à la matrice backend plutôt qu'à une relecture manuelle.
 *
 * Module pur (ni React ni JSX), exécutable par Node 24 (type stripping) :
 *
 *   node tests/verify-sidebar-roles.ts
 */

import {
    defaultPathFor,
    isPathInMenu,
    resolveSidebar,
} from '../assets/react/app/layout/MainLayout/sidebar/sidebar.config.ts';
import type { OrganizationRole, PlatformRole } from '../assets/services/api/api.types.ts';

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

check(`PATRON : 6 entrées (reçu ${PATRON.length})`, PATRON.length === 6, String(PATRON.length));
check(`ADMIN_IMMOBILIER : 5 entrées, Personnel inclus (reçu ${ADMIN_IMMOBILIER.length})`, ADMIN_IMMOBILIER.length === 5, String(ADMIN_IMMOBILIER.length));
check(`ADMIN_VILLE : 4 entrées (reçu ${ADMIN_VILLE.length})`, ADMIN_VILLE.length === 4, String(ADMIN_VILLE.length));
check('SUPER_ADMIN : menu plateforme 4 entrées, aucun rôle métier ne l\'alourdit', PLATFORM.length === 4, String(PLATFORM.length));
check('Sans rôle : menu vide', resolveSidebar(null, null).length === 0);

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

check('ADMIN_VILLE atterrit sur /app/patrimoine/villes', defaultPathFor(null, 'admin_ville' as OrganizationRole) === '/app/patrimoine/villes');
check('SUPER_ADMIN atterrit sur /app/admin/organisations', defaultPathFor('super_admin' as PlatformRole, null) === '/app/admin/organisations');
check('Sans rôle : redirection vers la page accès non prévu', defaultPathFor(null, null) === '/app/access-denied');

console.log('\n' + '-'.repeat(60) + '\n');

if (failures.length > 0) {
    console.log(`ECHEC : ${failures.length} / ${checks} contrôles en échec`);

    for (const failure of failures) {
        console.log(`  - ${failure}`);
    }

    process.exit(1);
}

console.log(`SUCCES : ${checks} contrôles passés`);