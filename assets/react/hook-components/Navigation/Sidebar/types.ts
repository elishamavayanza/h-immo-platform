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
