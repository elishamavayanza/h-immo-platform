// ============================================================
// upload/react/app/layout/MainLayout/MainLayout.tsx
// Coquille du back-office : sidebar plein écran + contenu.
// Plus de header — tout passe par le sidebar et son UserMenu.
// ============================================================

import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { Navigate, Outlet, useLocation, useNavigate } from 'react-router-dom';

import { useAuth } from '../../providers/AuthProvider';
import { useOrganization } from '../../providers/OrganizationProvider';
import { UserMenu, ROLE_LABELS } from './UserMenu';
import type { AppMenuItem } from './sidebar/sidebar.types';
import { resolveSidebar } from './sidebar/sidebar.config';
import { resolveActiveMenu } from './sidebar/sidebar.active';
import { SIDEBAR_ICON_MAP } from './sidebar/sidebar.icons';

import './MainLayout.scss';
import { Sidebar, SidebarProps } from '../../../components/Navigation/Sidebar';
import { Loading } from '../../../components/UI/Loading';
import { Spinner } from '../../../components/UI/Spinner';
import { useIsPortrait } from '../../../hooks/useIsPortrait';
import { useIsMobile } from '../../../hooks/useIsMobile';

/**
 * Marque du sidebar : logo + "IMMO" + nom de l'organisation active sur sa propre ligne.
 * Composant pur pour éviter de le recréer à chaque rendu.
 */
function Brand({ organizationName }: { organizationName?: string }) {
    return (
        <div className="main-layout__brand">
            <div className="main-layout__brand-main">
                <img
                    src="/assets/logo.png"
                    alt="H-Immo"
                    className="main-layout__brand-logo"
                />
                <span className="main-layout__brand-name">Soft-IMMO</span>
            </div>
            {organizationName && (
                <span className="main-layout__brand-org">{organizationName}</span>
            )}
        </div>
    );
}

/**
 * Traduit le menu de configuration en items du composant <Sidebar />.
 *
 * `activeIds` vient de `resolveActiveMenu()` : il contient la feuille
 * active ET ses sections parentes. On le reporte sur deux champs :
 *   - `active` : état actif visible (point §6 du cahier des charges) ;
 *   - `defaultOpen` : section dépliée d'emblée, pour que le sous-menu
 *     corresponde à la page affichée dès le premier rendu (point §8).
 */
function toSidebarItems(menu: AppMenuItem[], activeIds: ReadonlySet<string>): NonNullable<SidebarProps['items']> {
    return menu.map(({ id, label, icon, path, children }) => ({
        id,
        label,
        icon: icon !== undefined ? SIDEBAR_ICON_MAP[icon]?.() : undefined,
        active: activeIds.has(id),
        defaultOpen: children !== undefined && activeIds.has(id),
        ...(path !== undefined ? { route: path } : {}),
        ...(children !== undefined
            ? {
                children: toSidebarItems(children, activeIds),
            }
            : {}),
    }));
}

const SIDEBAR_SECTION_LABELS: Record<NonNullable<AppMenuItem['section']>, string> = {
    overview: 'Vue générale',
    property: 'Gestion immobilière',
    finance: 'Finances',
    operations: 'Opérations',
    administration: 'Administration',
    platform: 'Plateforme',
};

function toSidebarGroups(menu: AppMenuItem[], activeIds: ReadonlySet<string>): NonNullable<SidebarProps['groups']> {
    const sections = new Map<NonNullable<AppMenuItem['section']>, AppMenuItem[]>();

    menu.forEach((item) => {
        const section = item.section ?? 'operations';
        sections.set(section, [...(sections.get(section) ?? []), item]);
    });

    return [...sections.entries()].map(([section, sectionItems]) => ({
        id: section,
        label: SIDEBAR_SECTION_LABELS[section],
        items: toSidebarItems(sectionItems, activeIds),
    }));
}

export function MainLayout() {
    const { user, isAuthenticated, isLoading: isAuthLoading, logout } = useAuth();
    const { currentOrganization, platformRole, organizationRole, isLoading: isOrgLoading } = useOrganization();
    const [isMobileOpen, setIsMobileOpen] = useState(false);
    const isMobile = useIsMobile();
    const isPortrait = useIsPortrait();
    const [isPageTransitionLoading, setPageTransitionLoading] = useState(false);
    const location = useLocation();
    const navigate = useNavigate();
    const previousPath = useRef(location.pathname);

    // Le routeur utilisé ici est BrowserRouter. On affiche le même indicateur
    // sur toutes les pages pendant le changement de chemin, y compris pour
    // les maquettes alimentées par des données locales.
    useLayoutEffect(() => {
        if (previousPath.current === location.pathname) return;
        previousPath.current = location.pathname;
        setPageTransitionLoading(true);
        const timer = window.setTimeout(() => setPageTransitionLoading(false), 220);
        return () => window.clearTimeout(timer);
    }, [location.pathname]);

    // ── Verrou de scroll pendant l'ouverture du tiroir ──────────
    // Le tiroir ne couvre qu'une bande de l'écran : sans ce verrou, la page
    // défile derrière pendant qu'on parcourt le menu au doigt, et le contenu
    // bouge sous le doigt — sensation de menu cassé. `useSidebar` pose déjà
    // ce verrou pour lui-même ; celui-ci couvre le délai d'ouverture, pendant
    // lequel le composant enfant n'est pas encore monté.
    //
    // Le corps ne défile plus, mais `overscroll-behavior` évite qu'un geste
    // au bord ne fasse rebondir la page sous le panneau.
    useEffect(() => {
        if (!isMobileOpen) return;

        const { body } = document;
        const previousOverflow = body.style.overflow;

        body.style.overflow = 'hidden';

        return () => {
            body.style.overflow = previousOverflow;
        };
    }, [isMobileOpen]);

    // Ferme le drawer à chaque navigation. `setTimeout(0)` : fermer dans le
    // même cycle que la navigation ferait disparaître le tiroir ET son voile
    // d'un seul coup, sans transition — le glissement de fermeture ne serait
    // jamais vu. En différant d'un tick, la classe `sidebar--mobile-open`
    // disparaît d'abord (le tiroir glisse hors écran), puis le composant
    // est démonté à la fin de la transition.
    useEffect(() => {
        const timer = setTimeout(() => setIsMobileOpen(false), 0);

        return () => clearTimeout(timer);
    }, [location.pathname]);

    // Ferme le drawer à Échap
    useEffect(() => {
        if (!isMobileOpen) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setIsMobileOpen(false);
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [isMobileOpen]);

    // ⚠️ Le blocage du scroll sous le drawer mobile est géré par `useSidebar`
    // (dans <Sidebar />) : c'est le composant qui connaît le drawer. Le
    // dupliquer ici faisait courir deux propriétaires sur
    // `document.body.style.overflow`, dont le `finally` de l'un écrasait
    // l'état posé par l'autre (page parfois figée, parfois scrollable).

    if (isAuthLoading || isOrgLoading) {
        return (
            <div className="main-layout main-layout--centered">
                <Loading text="Chargement de votre espace..." />
            </div>
        );
    }

    if (!isAuthenticated || !user) {
        return <Navigate to="/login" replace />;
    }

    const menu = resolveSidebar(platformRole, organizationRole);
    const activeIds = resolveActiveMenu(menu, location.pathname);
    const groups = toSidebarGroups(menu, activeIds);

    const isPlatform = platformRole === 'super_admin';
    const effectiveRoleLabel = isPlatform
        ? ROLE_LABELS['super_admin']
        : organizationRole
            ? ROLE_LABELS[organizationRole] ?? organizationRole
            : undefined;

return (
        <div className="main-layout">
            {/* Bouton hamburger flottant — réservé au mode mobile
                (largeur < 768px). Fixed,
                z-index sous le drawer. */}
            <button
                type="button"
                className="main-layout__mobile-toggle"
                onClick={() => setIsMobileOpen(true)}
                aria-expanded={isMobileOpen}
                aria-controls="main-sidebar"
                aria-label={isMobileOpen ? 'Fermer le menu' : 'Ouvrir le menu'}
            >
                <span className="main-layout__mobile-toggle-box" aria-hidden="true">
                    <span className="main-layout__mobile-toggle-line" />
                    <span className="main-layout__mobile-toggle-line" />
                    <span className="main-layout__mobile-toggle-line" />
                </span>
            </button>

            {/* Backdrop mobile : rend le tiroir modal (clic dehors ferme). */}
            {isMobileOpen && (
                <button
                    type="button"
                    className="main-layout__backdrop"
                    aria-label="Fermer le menu"
                    onClick={() => setIsMobileOpen(false)}
                />
            )}

            <div className="main-layout__body">
                <Sidebar
                    id="main-sidebar"
                    groups={groups}
                    variant="dark"
                    collapsible
                    defaultCollapsed={isPortrait && !isMobile}
                    width="264px"
                    header={<Brand organizationName={currentOrganization?.name} />}
                    activeRoute={location.pathname}
                    activeIds={activeIds}
                    mobileOpen={isMobileOpen}
                    onMobileClose={() => setIsMobileOpen(false)}
                    onMobileOpen={() => setIsMobileOpen(true)}
                    onItemClick={(item) => {
                        const target = 'route' in item ? item.route : undefined;
                        if (target && target !== location.pathname) {
                            setPageTransitionLoading(true);
                            navigate(target);
                        }
                    }}
                    footer={
                        <UserMenu
                            fullName={user.fullName}
                            email={user.email}
                            profilePhoto={user.profilePhoto}
                            roleLabel={effectiveRoleLabel}
                            onOpenSettings={() => navigate('/settings')}
                            onOpenProfile={() => navigate('/profile')}
                            onLogout={() => {
                                void logout();
                            }}
                        />
                    }
                />

                <main className="main-layout__content">
                    {/* Pas de garde de rôle ici : c'est `AppRouteGuard`, posé sur
                        les routes `/app` dans `AppRoutes.tsx`, qui décide. Un
                        `RequireRole` enveloppait autrefois ce `<Outlet />`, mais
                        avec ses props par défaut il n'autorisait que
                        `organizationRole` — donc `null` pour un SUPER_ADMIN, qui
                        s'est vu refuser tout le back-office. Il ne gérait pas
                        non plus le cas « aucun rôle » (redirigé par
                        `/app/access-denied`) ni le 403 d'une page réservée à un
                        autre rôle. Le chargement des rôles est déjà traité
                        *avant* ce point par le `Loading` ci-dessus. */}
                    {isPageTransitionLoading ? (
                        <div className="main-layout__page-loading" aria-busy="true">
                            <Spinner size="large" className="spinner--page" />
                            <span>Chargement de la page…</span>
                        </div>
                    ) : <Outlet />}
                </main>
            </div>
        </div>
    );
}
