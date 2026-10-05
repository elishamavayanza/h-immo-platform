// ============================================================
// upload/react/app/layout/MainLayout/MainLayout.tsx
// Coquille du back-office : sidebar plein écran + contenu.
// Plus de header — tout passe par le sidebar et son UserMenu.
// ============================================================

import { useEffect, useState } from 'react';
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

const BRAND = (
    <div className="main-layout__brand">
        <span className="main-layout__brand-mark">H</span>
        <span className="main-layout__brand-name">Immo</span>
    </div>
);

/**
 * Traduit le menu de configuration en items du composant <Sidebar />.
 *
 * `activeIds` vient de `resolveActiveMenu()` : il contient la feuille
 * active ET ses sections parentes. On le reporte sur deux champs :
 *   - `active` : état actif visible (point §6 du cahier des charges) ;
 *   - `defaultOpen` : section dépliée d'emblée, pour que le sous-menu
 *     corresponde à la page affichée dès le premier rendu (point §8).
 */
function toSidebarItems(menu: AppMenuItem[], activeIds: ReadonlySet<string>): SidebarProps['items'] {
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

export function MainLayout() {
    const { user, isAuthenticated, isLoading: isAuthLoading, logout } = useAuth();
    const { platformRole, organizationRole, isLoading: isOrgLoading } = useOrganization();
    const [isMobileOpen, setIsMobileOpen] = useState(false);
    const location = useLocation();
    const navigate = useNavigate();

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
    const items = toSidebarItems(menu, activeIds);

    const isPlatform = platformRole === 'super_admin';
    const effectiveRoleLabel = isPlatform
        ? ROLE_LABELS['super_admin']
        : organizationRole
            ? ROLE_LABELS[organizationRole] ?? organizationRole
            : undefined;

    return (
        <div className="main-layout">
            {/* Aucun bouton burger : le sidebar reste visible en rail de 72px
                sous 768px, et c'est son logo qui ouvre le tiroir. Ce qui
                était ici faisait doublon avec le logo et laissait le rail
                mobile sans point d'entrée dès qu'on le retirait. */}

            {/* Backdrop mobile : rend le tiroir modal (cliquer dehors ferme),
                et le `aria-modal` du tiroir est posé plus bas côté Sidebar. */}
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
                    items={items}
                    variant="dark"
                    collapsible
                    defaultCollapsed={false}
                    width="264px"
                    header={BRAND}
                    activeRoute={location.pathname}
                    activeIds={activeIds}
                    mobileOpen={isMobileOpen}
                    onMobileClose={() => setIsMobileOpen(false)}
                    onMobileOpen={() => setIsMobileOpen(true)}
                    onItemClick={(item) => {
                        const target = 'route' in item ? item.route : undefined;
                        if (target) navigate(target);
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
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
