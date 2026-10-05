import { ReactNode } from 'react';

export interface SidebarItemBase {
    id: string;
    label: ReactNode;
    icon?: ReactNode;
    route?: string;                 // route cible
    href?: string;                  // alias pour compat
    active?: boolean;
    disabled?: boolean;
    permission?: string;            // permission requise
    badge?: number | string;        // badge (compteur)
    /**
     * Section ouverte d'emblée (sous-menu déjà déplié au premier rendu).
     * L'appelant le positionne à `true` sur la section qui contient la
     * route active : le sous-menu est alors cohérent avec la page
     * affichée, y compris après un rechargement direct sur une URL
     * profonde. Un clic utilisateur prend le dessus (voir `useSidebar`).
     */
    defaultOpen?: boolean;
}

export interface SidebarSubItem extends SidebarItemBase {}

export interface SidebarItem extends SidebarItemBase {
    children?: SidebarSubItem[];
}

export interface SidebarGroup {
    id: string;
    label: string;
    items: (SidebarItem | SidebarSubItem)[];
}

export type SidebarData = SidebarItem[] | SidebarGroup[];

export type SidebarPermission = string;

export interface SidebarConfigItem extends SidebarItemBase {
    children?: SidebarSubItem[];
}

export interface SidebarConfigGroup {
    id: string;
    label: string;
    items: (SidebarItem | SidebarSubItem)[];
}

export type SidebarConfig = SidebarGroup[];
