// ============================================================
// upload/react/app/layout/MainLayout/UserMenu.tsx
// Menu utilisateur affiché en bas du sidebar.
//
// Contient : identité (avatar + nom + rôle), paramètres,
// déconnexion. Utilise <PopoverMenu /> du Design System.
// ============================================================

import { Avatar } from '../../../components/UI/Avatar';
import {PopoverMenuItem} from "../../../hook-components/UI/PopoverMenu";
import {PopoverMenu} from "../../../components/UI/PopoverMenu";

// ─────────────────────────────────────────
// Icônes
// ─────────────────────────────────────────

const UserIcon = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
        <circle cx="12" cy="7" r="4" />
    </svg>
);

const SettingsIcon = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2">
        <circle cx="12" cy="12" r="3" />
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
    </svg>
);

const LogoutIcon = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
        <polyline points="16 17 21 12 16 7" />
        <line x1="21" y1="12" x2="9" y2="12" />
    </svg>
);

const ChevronUpIcon = () => (
    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2">
        <polyline points="18 15 12 9 6 15" />
    </svg>
);

// ─────────────────────────────────────────
// Types
// ─────────────────────────────────────────

const ROLE_LABELS: Record<string, string> = {
    patron: 'Patron',
    admin_immobilier: 'Admin immobilier',
    admin_ville: 'Admin ville',
    super_admin: 'Super admin',
};

export interface UserMenuProps {
    fullName: string;
    email: string;
    profilePhoto?: string | null;
    /** Rôle effectif à afficher sous le nom. */
    roleLabel?: string;
    /** Ouvre la page Paramètres. */
    onOpenSettings: () => void;
    /** Ouvre la page Profil (optionnel). */
    onOpenProfile?: () => void;
    /** Déclenche la déconnexion. */
    onLogout: () => void;
}

// ─────────────────────────────────────────
// Composant
// ─────────────────────────────────────────

export function UserMenu({
                             fullName,
                             email,
                             profilePhoto,
                             roleLabel,
                             onOpenSettings,
                             onOpenProfile,
                             onLogout,
                         }: UserMenuProps) {
    const items: PopoverMenuItem[] = [
        ...(onOpenProfile
            ? [
                {
                    id: 'profile',
                    label: 'Mon profil',
                    icon: <UserIcon />,
                    onClick: onOpenProfile,
                },
            ]
            : []),
        {
            id: 'settings',
            label: 'Paramètres',
            icon: <SettingsIcon />,
            onClick: onOpenSettings,
        },
        {
            id: 'logout',
            label: 'Déconnexion',
            icon: <LogoutIcon />,
            danger: true,
            onClick: onLogout,
        },
    ];

    return (
        <div className="user-menu">
            <PopoverMenu
                placement="top"
                offset={8}
                items={items}
                trigger={
                    // Pas de `role`/`tabIndex` ici : <PopoverMenu /> pose
                    // déjà les siens sur son propre conteneur et gère
                    // Entrée/Espace. Les dupliquer créait deux `role="button"`
                    // imbriqués (invalid) et deux arrêts de tabulation.
                    <div className="user-menu__wrapper">
                        <div className="user-menu__trigger">
                            <Avatar
                                name={fullName}
                                src={profilePhoto ?? undefined}
                                size="small"
                            />
                            <div className="user-menu__info">
                                <span className="user-menu__name">{fullName}</span>
                                <span className="user-menu__role">
                                    {roleLabel ?? email}
                                </span>
                            </div>
                            <span className="user-menu__chevron" aria-hidden="true">
                                <ChevronUpIcon />
                            </span>
                        </div>
                    </div>
                }
            />
        </div>
    );
}

export { ROLE_LABELS };
