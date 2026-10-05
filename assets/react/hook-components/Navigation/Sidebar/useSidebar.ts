import { useState, useMemo, useEffect, useRef, useCallback, type CSSProperties } from 'react';
import { useIsMobile } from '../../../hooks/useIsMobile';
import { SidebarItem, SidebarSubItem, SidebarGroup, SidebarData } from './types.ts';
import { isFlyoutAvailable, isLabelHidden } from './sidebar.state.ts';
import { FLYOUT_CLOSE_DELAY_MS } from '../../../components/Navigation/Sidebar/flyout.constants.ts';

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
    /**
     * Contrôle externally piloté du tiroir mobile. Laisser `undefined` rend
     * le tiroir autonome (usage autonome du composant) ; le `MainLayout` le
     * passe pour le fermer à chaque navigation.
     */
    mobileOpen?: boolean;
    onMobileClose?: () => void;
    /**
     * Notifie l'ouverture du tiroir. Indispensable en mode contrôlé : sans
     * ce retour, `openMobile()` ne peut rien modifier (`mobileOpen` étant
     * défini, l'état interne est ignoré) et le clic sur un parent en rail ne
     * ferait rien.
     */
    onMobileOpen?: () => void;
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
                               onMobileOpen,
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

    // Id du parent dont le flyout est ouvert. `null` = aucun flyout. L'état
    // est unique (un seul flyout à la fois) : deux flyouts simultanés se
    // concurrenceraient la même colonne de pixels à droite du rail.
    const [flyoutItemId, setFlyoutItemId] = useState<string | null>(null);
    // Ancres DOM des boutons parents, mémorisées pour que le flyout puisse se
    // positionner sans que le hook doive connaître le rendu du composant.
    // Une ref et non un état : les ancres existent avant l'ouverture, et les
    // faire passer en état provoquerait un rendu par item à chaque montage.
    const flyoutAnchorsRef = useRef<Map<string, HTMLElement>>(new Map());
    const flyoutCloseTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const isMobileOpen = mobileOpen !== undefined ? mobileOpen : internalMobileOpen;

    // En mode bureau (`>= 768px`) la sidebar redevient statique dans le flux ;
    // si le drawer mobile était resté ouvert, on le referme pour ne pas le
    // rouvrir intempestivement quand on repasse sous le seuil.
    const isMobile = useIsMobile();

    // Nouveau comportement mobile : PAS de rail permanent.
    // Le sidebar est soit complètement caché (fermé), soit pleinement ouvert (tiroir).
    // `isRail` est toujours false — conservé pour compatibilité si du code l'utilise.
    const isRail = false;

    // `sidebar--rail` n'est plus utilisé (plus de rail mobile).
    const railClass = '';

    // Sur mobile, le sidebar est COMPLÈTEMENT CACHÉ par défaut (pas de rail).
    // Il n'y a plus d'état « replié » sur mobile : soit le tiroir est ouvert
    // (largeur pleine, libellés visibles), soit il est fermé (sidebar hors écran).
    // Le `sidebar--collapsed` ne s'applique donc QUE sur desktop/tablette.
    //
    // Les règles pures vivent dans `sidebar.state.ts`, testables sans DOM.
    const displayState = { isMobile, isRail, isCollapsed, collapsible };
    // Sur mobile : jamais « visually collapsed » (pas de rail).
    // Sur desktop : collapsed = replié par l'utilisateur.
    const isVisuallyCollapsed = isMobile ? false : isLabelHidden(displayState);

    // Le flyout n'a de sens que là où les libellés sont masqués ET où aucun
    // tiroir ne peut les révéler : sur mobile le tiroir affiche tout, donc
    // pas de flyout.
    const isFlyoutEnabled = isFlyoutAvailable(displayState);

    // Le flyout est un état dérivé d'un mode d'affichage : dès que les libellés
    // redeviennent visibles (déploiement du sidebar, bascule en mobile, retour
    // au tiroir) il doit disparaître, sinon son contenu ferait double emploi
    // avec la section inline et resterait orphelin à l'écran.
    useEffect(() => {
        if (!isFlyoutEnabled) closeFlyout();
    }, [isFlyoutEnabled]);

    // Un changement de route referme le flyout : son contenu vient d'être
    // supplanté par la nouvelle page, et le laisser ouvert(floatant au-dessus
    // du contenu) désorienterait.
    useEffect(() => {
        closeFlyout();
    }, [activeId]);

    // Nettoyage du timer au démontage : un `setTimeout` resté en vol après le
    // démontage-appellerait `setFlyoutItemId` sur un composant mort.
    useEffect(() => {
        return () => {
            if (flyoutCloseTimerRef.current) clearTimeout(flyoutCloseTimerRef.current);
        };
    }, []);

    const registerFlyoutAnchor = useCallback((itemId: string, element: HTMLElement | null) => {
        if (element) {
            flyoutAnchorsRef.current.set(itemId, element);
        } else {
            flyoutAnchorsRef.current.delete(itemId);
        }
    }, []);

    const getFlyoutAnchor = useCallback((itemId: string): HTMLElement | null =>
        flyoutAnchorsRef.current.get(itemId) ?? null, []);

    const openFlyout = useCallback((itemId: string) => {
        if (flyoutCloseTimerRef.current) {
            clearTimeout(flyoutCloseTimerRef.current);
            flyoutCloseTimerRef.current = null;
        }
        setFlyoutItemId(itemId);
    }, []);

    /**
     * Fermeture différée : le pointeur doit pouvoir quitter le bouton parent
     * pour rejoindre le panneau portalé sans que celui-ci disparaisse dans le
     * traversal. `cancelFlyoutClose` (appelé à l'entrée du panneau) annule ce
     * délai, ce qui rend le pont souris fiable sans fond click-through.
     */
    const scheduleFlyoutClose = useCallback(() => {
        if (flyoutCloseTimerRef.current) clearTimeout(flyoutCloseTimerRef.current);
        flyoutCloseTimerRef.current = setTimeout(() => {
            flyoutCloseTimerRef.current = null;
            setFlyoutItemId(null);
        }, FLYOUT_CLOSE_DELAY_MS);
    }, []);

    const cancelFlyoutClose = useCallback(() => {
        if (flyoutCloseTimerRef.current) {
            clearTimeout(flyoutCloseTimerRef.current);
            flyoutCloseTimerRef.current = null;
        }
    }, []);

    const closeFlyout = useCallback(() => {
        if (flyoutCloseTimerRef.current) {
            clearTimeout(flyoutCloseTimerRef.current);
            flyoutCloseTimerRef.current = null;
        }
        setFlyoutItemId(null);
    }, []);

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
        onMobileOpen?.();
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

        return [base, variantClass, collapsedClass, mobileOpenClass, railClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, isVisuallyCollapsed, isMobileOpen, railClass, className]);

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
        // --- Flyout de sous-menu (rail desktop / tablette) ---
        /** Vrai quand le flyout est utilisable : rail, hors tiroir mobile. */
        isFlyoutEnabled,
        /** Id du parent dont le flyout est ouvert, `null` si aucun. */
        flyoutItemId,
        isFlyoutOpen: (itemId: string) => flyoutItemId === itemId,
        openFlyout,
        closeFlyout,
        scheduleFlyoutClose,
        cancelFlyoutClose,
        registerFlyoutAnchor,
        getFlyoutAnchor,
        /** Vrai quand les libellés sont masqués : rail desktop ou rail mobile. */
        isVisuallyCollapsed,
        /** Vrai sous 768px tant que le tiroir est fermé : le logo l'ouvre. */
        isRail,
        /** Vrai sous 768px, tiroir ouvert ou non : distinguishes les deux. */
        isMobile,
        closeMobile,
        openMobile,
    };
}
