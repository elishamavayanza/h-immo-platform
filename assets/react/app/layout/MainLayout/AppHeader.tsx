// ============================================================
// upload/react/app/layout/MainLayout/AppHeader.tsx
// Barre supérieure du back-office.
//
// - Bouton « burger » : ouvre le drawer mobile du sidebar (< 768px,
//   masqué en bureau par CSS).
// - Sélecteur d'organization (comptes métier uniquement) : propose les
//   appartenances de `/auth/me`, mais la bascule est confirmée par
//   `switchOrganization()` qui re-résout le rôle côté API. Un SUPER_ADMIN
//   n'a pas de sélecteur : le contexte met sa `currentOrganization` à null.
// - Pastille du rôle résolu + identité de l'utilisateur + déconnexion.
// ============================================================

import { Avatar } from '../../../../../public/components/UI/Avatar/Avatar';
import { Dropdown } from '../../../../../public/components/UI/Dropdown/Dropdown';
import { useAuth } from '../../providers/AuthProvider';
import { useOrganization } from '../../providers/OrganizationProvider';

const BURGER_ICON = (
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
}

export function AppHeader({ onOpenMenu }: AppHeaderProps) {
    const { user, logout } = useAuth();
    const { currentOrganization, organizationRole, platformRole, switchOrganization } = useOrganization();

    if (!user) return null;

    const isPlatform = platformRole === 'super_admin';
    const roleLabel = isPlatform
        ? (ROLE_LABELS['super_admin'] ?? 'Super admin')
        : (organizationRole ? (ROLE_LABELS[organizationRole] ?? organizationRole) : 'Aucun rôle');

    return (
        <header className="app-header">
            <div className="app-header__left">
                <button
                    type="button"
                    className="app-header__menu-toggle"
                    aria-label="Ouvrir le menu"
                    onClick={onOpenMenu}
                >
                    {BURGER_ICON}
                </button>
                <span className="app-header__brand">H-Immo</span>
            </div>

            <div className="app-header__center">
                {isPlatform ? (
                    <span className="app-header__platform-badge">Plateforme</span>
                ) : (
                    <Dropdown
                        className="app-header__org-switcher"
                        options={(user.organizations ?? []).map(org => ({
                            value: org.uuid,
                            label: `${org.name} — ${ROLE_LABELS[org.role] ?? org.role}`,
                        }))}
                        value={currentOrganization?.uuid}
                        onSelect={(uuid) => { void switchOrganization(uuid); }}
                        placeholder="Choisir une organisation..."
                    />
                )}
                <span className="app-header__role">{roleLabel}</span>
            </div>

            <div className="app-header__right">
                <Avatar name={user.fullName} src={user.profilePhoto ?? undefined} size="small" />
                <span className="app-header__user-name">{user.fullName}</span>
                <button
                    type="button"
                    className="app-header__logout"
                    onClick={() => { void logout(); }}
                >
                    Déconnexion
                </button>
            </div>
        </header>
    );
}
