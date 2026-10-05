// ============================================================
// upload/react/app/layout/MainLayout/MainLayout.tsx
// Coquille du back-office authentifié.
// ============================================================

import { useEffect, useState } from 'react';
import { Navigate, Outlet, useLocation, useNavigate } from 'react-router-dom';

import { useAuth } from '../../providers/AuthProvider';
import { useOrganization } from '../../providers/OrganizationProvider';
import { AppHeader } from './AppHeader';
import { RequireRole } from './RequireRole';
import type { AppMenuItem } from './sidebar/sidebar.types';
import { resolveSidebar } from './sidebar/sidebar.config';
import { SIDEBAR_ICON_MAP } from './sidebar/sidebar.icons';

import './MainLayout.scss';
import {Sidebar, SidebarProps} from "../../../components/Navigation/Sidebar";
import {Loading} from "../../../components/UI/Loading";

const BRAND = (
    <div className="main-layout__brand">
        <span className="main-layout__brand-mark">H</span>
        <span className="main-layout__brand-name">Immo</span>
    </div>
);

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

    // Ferme le drawer à chaque navigation
    useEffect(() => {
        setIsMobileOpen(false);
    }, [location.pathname]);

    // Ferme le drawer à la touche Échap
    useEffect(() => {
        if (!isMobileOpen) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setIsMobileOpen(false);
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [isMobileOpen]);

    // Bloque le scroll du body quand le drawer est ouvert (mobile)
    useEffect(() => {
        document.body.style.overflow = isMobileOpen ? 'hidden' : '';
        return () => {
            document.body.style.overflow = '';
        };
    }, [isMobileOpen]);

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
            <AppHeader
                onOpenMenu={() => setIsMobileOpen(true)}
                isMobileMenuOpen={isMobileOpen}
            />

            <div className="main-layout__body">
                {/* Backdrop mobile : clic à l'extérieur ferme le drawer */}
                {isMobileOpen && (
                    <div
                        className="main-layout__backdrop"
                        aria-hidden="true"
                        onClick={() => setIsMobileOpen(false)}
                    />
                )}

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
                        <div className="main-layout__footer-user">
                            <span className="main-layout__footer-name">{user.fullName}</span>
                            <span className="main-layout__footer-email">{user.email}</span>
                        </div>
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
