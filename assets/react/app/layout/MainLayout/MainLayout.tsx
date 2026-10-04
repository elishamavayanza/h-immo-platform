// ============================================================
// assets/react/app/layout/MainLayout/MainLayout.tsx
// Coquille du back-office authentifié.
//
// Compose :
//   - AppHeader (burger, sélecteur d'organization, identité)
//   - le sidebar générique (`components/Navigation/Sidebar`) alimenté par
//     `resolveSidebar()` — jamais par un test de rôle local
//   - la garde de route UX (`RequireRole`)
//   - `<Outlet/>` pour la page courante
//
// Le SUPER_ADMIN partage la même coquille mais son layout diffère :
// pas de sélecteur d'organization dans l'en-tête (le contexte met sa
// `currentOrganization` à null) et menu plateforme uniquement.
// ============================================================

import { useEffect, useState } from 'react';
import { Navigate, Outlet, useLocation, useNavigate } from 'react-router-dom';

import { Sidebar, type SidebarProps } from '../../../components/Navigation/Sidebar';
import { Loading } from '../../../components/UI/Loading';
import { useAuth } from '../../providers/AuthProvider';
import { useOrganization } from '../../providers/OrganizationProvider';
import { AppHeader } from './AppHeader';
import { RequireRole } from './RequireRole';
import type { AppMenuItem } from './sidebar/sidebar.types';
import { resolveSidebar } from './sidebar/sidebar.config';
import { SIDEBAR_ICON_MAP } from './sidebar/sidebar.icons';

import './MainLayout.scss';

const BRAND = (
    <div className="main-layout__brand">
        <span className="main-layout__brand-mark">H</span>
        <span className="main-layout__brand-name">Immo</span>
    </div>
);

/** Le sidebar générique comprend `route` ; on aligne `path` → `route` et on
 * résout le NOM d'icône de la config (module pur) en composant SVG. */
function toSidebarItems(menu: AppMenuItem[]): SidebarProps['items'] {
    return menu.map(({ id, label, icon, path, children }) => ({
        id,
        label,
        icon: icon !== undefined ? SIDEBAR_ICON_MAP[icon]?.() : undefined,
        ...(path !== undefined ? { route: path } : {}),
        ...(children !== undefined ? { children: toSidebarItems(children) } : {}),
    }));
}

export function MainLayout() {
    const { user, isAuthenticated, isLoading: isAuthLoading } = useAuth();
    const { platformRole, organizationRole, isLoading: isOrgLoading } = useOrganization();
    const [isMobileOpen, setIsMobileOpen] = useState(false);
    const location = useLocation();
    const navigate = useNavigate();

    // Toute navigation referme le drawer mobile : on ne quitte jamais le
    // panneau ouvert sur une autre page.
    useEffect(() => {
        setIsMobileOpen(false);
    }, [location.pathname]);

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
    const items = toSidebarItems(menu);

    return (
        <div className="main-layout">
            <AppHeader onOpenMenu={() => setIsMobileOpen(true)} />

            <div className="main-layout__body">
                <Sidebar
                    items={items}
                    variant="dark"
                    collapsible
                    defaultCollapsed={false}
                    width="264px"
                    header={BRAND}
                    activeRoute={location.pathname}
                    mobileOpen={isMobileOpen}
                    onMobileClose={() => setIsMobileOpen(false)}
                    onItemClick={(item) => {
                        const target = 'route' in item ? item.route : undefined;
                        if (target) navigate(target);
                    }}
                    footer={
                        user ? (
                            <div className="main-layout__footer-user">
                                <span className="main-layout__footer-name">{user.fullName}</span>
                                <span className="main-layout__footer-email">{user.email}</span>
                            </div>
                        ) : undefined
                    }
                />

                <main className="main-layout__content">
                    <RequireRole>
                        <Outlet />
                    </RequireRole>
                </main>
            </div>
        </div>
    );
}
