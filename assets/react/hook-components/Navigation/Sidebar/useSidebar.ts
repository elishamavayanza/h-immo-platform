import { useState, useMemo, useEffect, type CSSProperties } from 'react';
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
    /** Largeur du menu (CSS) : surchargée par la variable CSS `--sidebar-width`. */
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
    // Sections repliées/dépliées à la main. Volontairement séparé de
    // `defaultOpen` (porté par les items) : l'état ouvert d'une section
    // est l'override manuel s'il existe, sinon la valeur par défaut
    // dérivée de la route courante. Conséquence : un changement de route
    // ouvre la bonne section sans qu'un effet doive refermer celle que
    // l'utilisateur venait d'ouvrir, et replier puis redeployer le
    // sidebar ne perd rien.
    const [sectionToggles, setSectionToggles] = useState<Record<string, boolean>>({});
    const [internalMobileOpen, setInternalMobileOpen] = useState(mobileOpen);

    const isMobileOpen = mobileOpen !== undefined ? mobileOpen : internalMobileOpen;

    // En mode bureau (`>= 768px`) la sidebar redevient statique dans le flux ;
    // si le drawer mobile était resté ouvert, on le referme pour ne pas le
    // rouvrir intempestivement quand on repasse sous le seuil.
    const isMobile = useIsMobile();

    // Sous 768px le panneau n'est plus « sorti de l'écran » : il reste
    // visible sous forme de rail de 72px, et c'est le logo qui ouvre le
    // tiroir. `isRail` dit qu'on est précisément dans cet état, ce qui
    // permet au composant de faire du logo le bouton d'ouverture.
    const isRail = isMobile && !isMobileOpen;

    // Sur mobile, `sidebar--collapsed` décrit le rail et le tiroir ouvert
    // son contraire : l'état visuel est donc piloté par l'état du tiroir,
    // et non par le choix de repli fait sur desktop (sans quoi un menu
    // replié sur desktop resterait en rail dans le tiroir ouvert, avec
    // deux séries de règles qui se contredisent).
    const isVisuallyCollapsed = isMobile ? isRail : collapsible && isCollapsed;

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

    /**
     * Sections à ouvrir d'emblée, déduites des items (`defaultOpen`).
     * Indexé par id pour éviter de reparcourir la liste à chaque rendu.
     */
    const defaultOpenSections = useMemo(() => {
        const map: Record<string, boolean> = {};

        const collect = (list: SidebarItem[]): void => {
            for (const item of list) {
                if (item.children === undefined) continue;

                if (item.defaultOpen === true) map[item.id] = true;

                collect(item.children as SidebarItem[]);
            }
        };

        if (groups !== undefined) {
            for (const group of groups) collect(group.items as SidebarItem[]);
        } else {
            collect(items);
        }

        return map;
    }, [groups, items]);

    /**
     * Une section est ouverte si l'utilisateur l'a explicitement basculée,
     * sinon si elle porte `defaultOpen` (typiquement : elle contient la
     * route active). `toggleSection` fige alors la valeur au premier clic.
     */
    const isSectionOpen = (sectionId: string): boolean =>
        sectionToggles[sectionId] ?? defaultOpenSections[sectionId] === true;

    const toggleSection = (sectionId: string) => {
        setSectionToggles((prev) => ({
            ...prev,
            [sectionId]: !(prev[sectionId] ?? defaultOpenSections[sectionId] === true),
        }));
    };

    /** Ouvre une section sans risquer de la refermer (clic en mode replié). */
    const openSection = (sectionId: string) => {
        setSectionToggles((prev) => ({ ...prev, [sectionId]: true }));
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
        const collapsedClass = isVisuallyCollapsed ? 'sidebar--collapsed' : '';
        const mobileOpenClass = isMobileOpen ? 'sidebar--mobile-open' : '';

        return [base, variantClass, collapsedClass, mobileOpenClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, isVisuallyCollapsed, isMobileOpen, className]);

    /**
     * La largeur vient de la SCSS (`$sidebar-width`) ; la prop `width` la
     * surcharger par variable CSS pour éviter une largeur codée en dur dans
     * deux fichiers. Les types React n'acceptent pas les propriétés
     * personnalisées : d'où le cast, limité à cette clé.
     */
    const style = {
        '--sidebar-width': width,
    } as CSSProperties;

    return {
        classes,
        style,
        isCollapsed,
        toggleCollapse,
        isSectionOpen,
        toggleSection,
        openSection,
        activeId,
        filteredItems,
        isMobileOpen,
        /** Vrai quand les libellés sont masqués : rail desktop ou rail mobile. */
        isVisuallyCollapsed,
        /** Vrai sous 768px tant que le tiroir est fermé : le logo l'ouvre. */
        isRail,
        closeMobile,
        openMobile,
    };
}
