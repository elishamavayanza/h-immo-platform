// ============================================================
// upload/react/app/layout/MainLayout/AppHeader.tsx
// Barre supérieure du back-office.
// ============================================================

import { Avatar } from '../../../components/UI/Avatar/Avatar';
import { Dropdown } from '../../../components/UI/Dropdown/Dropdown';
import { useAuth } from '../../providers/AuthProvider';
import { useOrganization } from '../../providers/OrganizationProvider';

// Icône burger — change en croix quand le menu est ouvert
const BurgerIcon = ({ open }: { open: boolean }) =>
    open ? (
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" strokeWidth="2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
    ) : (
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" strokeWidth="2">
            <line x1="4" y1="7" x2="20" y2="7" />
            <line x1="4" y1="12" x2="20" y2="12" />
            <line x1="4" y1="17" x2="20" y2="17" />
        </svg>
    );

const ROLE_LABELS: Record<string, string> = {
    patron: 'Patron',
    admin_immobilier: 'Admin immobilier',
    admin_ville: 'Admin ville',
    super_admin: 'Super admin',
};

interface AppHeaderProps {
    onOpenMenu: () => void;
    isMobileMenuOpen?: boolean;
}

export function AppHeader({ onOpenMenu, isMobileMenuOpen = false }: AppHeaderProps) {
    const { user, logout } = useAuth();
    const {
        currentOrganization,
        organizationRole,
        platformRole,
        switchOrganization,
    } = useOrganization();

    if (!user) return null;

    const isPlatform = platformRole === 'super_admin';
    const roleLabel = isPlatform
        ? (ROLE_LABELS['super_admin'] ?? 'Super admin')
        : organizationRole
            ? (ROLE_LABELS[organizationRole] ?? organizationRole)
            : 'Aucun rôle';

    return (
        <header className="app-header">
            {/* ===================== GAUCHE ===================== */}
            <div className="app-header__left">
                <button
                    type="button"
                    className="app-header__menu-toggle"
                    aria-label={isMobileMenuOpen ? 'Fermer le menu' : 'Ouvrir le menu'}
                    aria-expanded={isMobileMenuOpen}
                    aria-controls="main-sidebar"
                    onClick={onOpenMenu}
                >
                    <BurgerIcon open={isMobileMenuOpen} />
                </button>
                <span className="app-header__brand">H-Immo</span>
            </div>

            {/* ===================== CENTRE ===================== */}
            <div className="app-header__center">
                {isPlatform ? (
                    <span className="app-header__platform-badge">Plateforme</span>
                ) : (
                    <Dropdown
                        className="app-header__org-switcher"
                        options={(user.organizations ?? []).map((org) => ({
                            value: org.uuid,
                            label: `${org.name} — ${ROLE_LABELS[org.role] ?? org.role}`,
                        }))}
                        value={currentOrganization?.uuid}
                        onSelect={(uuid) => {
                            void switchOrganization(uuid);
                        }}
                        placeholder="Choisir une organisation..."
                    />
                )}
                <span className="app-header__role">{roleLabel}</span>
            </div>

            {/* ===================== DROITE ===================== */}
            <div className="app-header__right">
                <Avatar
                    name={user.fullName}
                    src={user.profilePhoto ?? undefined}
                    size="small"
                />
                <span className="app-header__user-name">{user.fullName}</span>
                <button
                    type="button"
                    className="app-header__logout"
                    onClick={() => {
                        void logout();
                    }}
                >
                    Déconnexion
                </button>
            </div>
        </header>
    );
}
