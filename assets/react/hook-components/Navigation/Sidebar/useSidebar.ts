import { useState, useMemo, useEffect } from 'react';
import { useIsMobile } from '../../../hooks/useIsMobile';
import { SidebarItem, SidebarSubItem, SidebarGroup, SidebarData } from './types.ts';

export interface UseSidebarProps {
    /** Ancienne façon : liste plate d'items */
    items?: SidebarItem[];
    /** Nouvelle façon : groupes de menu */
    groups?: SidebarGroup[];
    variant?: 'default' | 'dark' | 'light';
    collapsible?: boolean;
    defaultCollapsed?: boolean;
    width?: string;
    activeId?: string;
    onItemClick?: (item: SidebarItem | SidebarSubItem) => void;
    className?: string;
    userPermissions?: string[];
    mobileOpen?: boolean;
    onMobileClose?: () => void;
}

export function useSidebar({
                               items = [],
                               groups,
                               variant = 'default',
                               collapsible = false,
                               defaultCollapsed = false,
                               width = '280px',
                               activeId,
                               onItemClick,
                               className = '',
                               userPermissions = [],
                               mobileOpen = false,
                               onMobileClose,
                           }: UseSidebarProps) {
    const [isCollapsed, setIsCollapsed] = useState(defaultCollapsed);
    const [openSections, setOpenSections] = useState<Record<string, boolean>>({});
    const [internalMobileOpen, setInternalMobileOpen] = useState(mobileOpen);

    const isMobileOpen = mobileOpen !== undefined ? mobileOpen : internalMobileOpen;

    // En mode bureau (`>= 768px`) la sidebar redevient statique dans le flux ;
    // si le drawer mobile était resté ouvert, on le referme pour ne pas le
    // rouvrir intempestivement quand on repasse sous le seuil.
    const isMobile = useIsMobile();

    useEffect(() => {
        if (!isMobile && isMobileOpen) closeMobile();
    }, [isMobile, isMobileOpen]);

    // Le drawer mobile couvre l'écran : tant qu'il est ouvert, la page ne
    // doit pas défiler derrière le panneau.
    useEffect(() => {
        if (!isMobileOpen || !isMobile) return;

        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = '';
        };
    }, [isMobileOpen, isMobile]);

    const toggleCollapse = () => setIsCollapsed((prev) => !prev);

    const toggleSection = (sectionId: string) => {
        setOpenSections((prev) => ({ ...prev, [sectionId]: !prev[sectionId] }));
    };

    const closeMobile = () => {
        if (mobileOpen === undefined) setInternalMobileOpen(false);
        onMobileClose?.();
    };

    const openMobile = () => {
        if (mobileOpen === undefined) setInternalMobileOpen(true);
    };

    // Filtrage selon permissions
    const hasPermission = (permission?: string) =>
        !permission || userPermissions.length === 0 || userPermissions.includes(permission);

    const filterItems = (list: SidebarItem[]): SidebarItem[] => {
        return list
            .map((item) => {
                if (!hasPermission(item.permission)) return null;
                if (item.children) {
                    const filteredChildren = item.children.filter((child) => hasPermission(child.permission));
                    return { ...item, children: filteredChildren };
                }
                return item;
            })
            .filter((item): item is SidebarItem => item !== null)
            .filter((item) => !item.children || item.children.length > 0);
    };

    // Si des groupes sont fournis, on filtre chaque groupe ; sinon on filtre les items plats.
    const filteredItems = useMemo(() => {
        if (groups) {
            return groups
                .map((group) => ({
                    ...group,
                    items: filterItems(group.items as SidebarItem[]),
                }))
                .filter((group) => group.items.length > 0);
        }
        return filterItems(items);
    }, [groups, items, userPermissions]);

    const classes = useMemo(() => {
        const base = 'sidebar';
        const variantClass = `sidebar--${variant}`;
        const collapsedClass = collapsible && isCollapsed ? 'sidebar--collapsed' : '';
        const mobileOpenClass = isMobileOpen ? 'sidebar--mobile-open' : '';
        return [base, variantClass, collapsedClass, mobileOpenClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, collapsible, isCollapsed, isMobileOpen, className]);

    return {
        classes,
        isCollapsed,
        toggleCollapse,
        openSections,
        toggleSection,
        activeId,
        filteredItems,
        isMobileOpen,
        closeMobile,
        openMobile,
    };
}
