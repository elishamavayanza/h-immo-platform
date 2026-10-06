import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useSidebar, UseSidebarProps, SidebarItem, SidebarSubItem, SidebarGroup } from '../../../hook-components/Navigation/Sidebar';
import { SidebarFlyout } from './SidebarFlyout.tsx';
import { SidebarItemTooltip } from './SidebarItemTooltip.tsx';
import { isBranchActive } from '../../../hook-components/Navigation/Sidebar/sidebar.state.ts';

const CollapseIcon = () => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2">
        <polyline points="15 18 9 12 15 6" />
    </svg>
);

const ExpandIcon = () => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2">
        <polyline points="9 18 15 12 9 6" />
    </svg>
);

/**
 * Libellé textuel d'un item.
 *
 * `label` est typé `ReactNode` (le design system autorise un nœud), mais le
 * nom accessible doit rester une chaîne : on ne descend pas dans l'arbre
 * React pour le produire. Un item sans libellé exploitable est traité comme
 * anonyme par les lecteurs d'écran, ce qui est pourquoi le menu latéral
 * en fournit toujours un.
 */
const toTextLabel = (label: SidebarItem['label']): string =>
    typeof label === 'string' ? label : '';

export interface SidebarProps extends UseSidebarProps {
    header?: React.ReactNode;
    footer?: React.ReactNode;
    activeRoute?: string;
    /** Ensemble des ids actifs (feuille + parents) : `resolveActiveMenu()`. */
    activeIds?: ReadonlySet<string>;
    /** Identifiant du `<aside>`, cible des `aria-controls` externes. */
    id?: string;
}

export function Sidebar({
                            id,
                            items,
                            groups,
                            variant,
                            collapsible,
                            defaultCollapsed,
                            width,
                            activeId,
                            activeIds,
                            activeRoute,
                            onItemClick,
                            className,
                            header,
                            footer,
                            userPermissions,
                            mobileOpen = false,
                            onMobileClose,
                            onMobileOpen,
                        }: SidebarProps) {
    const {
        classes: hookClasses,
        style,
        isMobile,
        toggleCollapse,
        isSectionOpen,
        toggleSection,
        openSection,
        filteredItems,
        isMobileOpen,
        isVisuallyCollapsed,
        isRail,
        openMobile,
        closeMobile,
        isFlyoutEnabled,
        flyoutItemId,
        isFlyoutOpen,
        openFlyout,
        closeFlyout,
        scheduleFlyoutClose,
        cancelFlyoutClose,
        registerFlyoutAnchor,
        getFlyoutAnchor,
    } = useSidebar({
        items,
        groups,
        variant,
        collapsible,
        defaultCollapsed,
        width,
        activeId,
        onItemClick,
        className,
        userPermissions,
        mobileOpen,
        onMobileClose,
        onMobileOpen,
    });

    // L'état visuel vient du hook : il vaut `isCollapsed` sur desktop, et
    // « tiroir fermé » sur mobile (où le rail remplace le menu replié). Un
    // simple `collapsible && isCollapsed` afficherait les libellés dans le
    // rail mobile, et le tiroir ouvert resterait figé en 72px.
    const displayCollapsed = isVisuallyCollapsed;
    const classes = hookClasses;

    // ─────────────────────────────────────────
    // Bulle du nom de menu au survol du rail
    // ─────────────────────────────────────────
    // Repliés, les libellés sont masqués : une infobulle stylée (remplaçant
    // l'attribut natif `title`) redonne le nom au survol. Un seul tooltip
    // peut exister à la fois ; il est monté après un court délai pour ne
    // pas surgir au simple passage du curseur.
    const [tooltip, setTooltip] = useState<{ anchor: HTMLElement; label: string } | null>(null);
    const tooltipTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const cancelTooltip = () => {
        if (tooltipTimer.current !== null) {
            clearTimeout(tooltipTimer.current);
            tooltipTimer.current = null;
        }
        setTooltip(null);
    };

    const scheduleTooltip = (anchor: HTMLElement, label: string) => {
        // `cancelTooltip` fait office de reset : un survol feuille → parent
        // ne laisse pas traîner l'ancienne bulle jusqu'à la nouvelle entrée.
        cancelTooltip();
        if (!displayCollapsed || label === '') return;

        tooltipTimer.current = setTimeout(() => setTooltip({ anchor, label }), 250);
    };

    // Nettoyage du timer au démontage, et disparition de la bulle dès que
    // le sidebar se déplie (le libellé redevient visible dans le flux).
    useEffect(() => cancelTooltip, []);
    useEffect(() => {
        if (!displayCollapsed) cancelTooltip();
    }, [displayCollapsed]);

    /**
     * Correspondance par segment de chemin : `/app/patrimoine/villes` doit
     * rester actif sur une route fille (`/app/patrimoine/villes/42`) sans
     * qu'une comparaison par préfixe simple n'allume `/app/location` pour
     * `/app/location-paiements`.
     */
    const isRouteActive = (route: string | undefined): boolean => {
        if (route === undefined || activeRoute === undefined) return false;
        if (route === activeRoute) return true;

        const base = route.endsWith('/') ? route.slice(0, -1) : route;

        return activeRoute === base || activeRoute.startsWith(`${base}/`);
    };

    const isItemActive = (item: SidebarItem | SidebarSubItem): boolean => {
        if (item.active) return true;
        if (activeId && item.id === activeId) return true;
        if ('route' in item && isRouteActive(item.route)) return true;
        return false;
    };

    /**
     * Un parent est « dans la route courante » s'il est lui-même actif ou si
     * l'un de ses descendants l'est. C'est ce qui garde allumé le bon groupe
     * quand une feuille est sélectionnée : le rail ne montre que les icônes,
     * et le parent de la page courante doit rester identifiable.
     */
    // `isBranchActive` est pure et testée à part : l'appeler ici évite
    // de dupliquer la récursivité et garde le composant focalisé sur le
    // rendu.
    const branchActive = (item: SidebarItem): boolean =>
        isBranchActive(item, activeIds ?? new Set());

    const handleItemClick = (item: SidebarItem | SidebarSubItem) => {
        // Le clic est une décision : la bulle doit disparaître avant que la
        // navigation n'emmène l'utilisateur loin de son ancre.
        cancelTooltip();
        onItemClick?.(item);

        const hasChildren = 'children' in item && item.children !== undefined && item.children.length > 0;

        if (hasChildren) {
            const sectionId = item.id;

            // Mobile drawer : un parent n'a pas de sous-menu inline (le tiroir
            // est déjà ouvert en pleine largeur). Le flyout gère les sous-menus
            // sur desktop/tablette replié.
            if (isMobileOpen) {
                openSection(sectionId);
                return;
            }

            // Desktop/tablette replié : flyout pour les sous-menus.
            if (isFlyoutEnabled) {
                isFlyoutOpen(sectionId) ? closeFlyout() : openFlyout(sectionId);
                return;
            }

            // Desktop déplié : toggle section inline.
            if (displayCollapsed) {
                toggleCollapse();
                openSection(sectionId);
                return;
            }

            toggleSection(sectionId);
            return;
        }

        // Navigation vers une feuille : le drawer mobile se referme et le
        // flyout se replie, pour ne pas laisser un panneau flottant au-dessus
        // de la nouvelle page.
        if (isMobileOpen) closeMobile();
        closeFlyout();
    };

    /**
     * Sélection dans le flyout : le panneau disparaît AVANT que le handler
     * ne referme le drawer mobile, sinon le sous-menu resterait affiché le
     * temps de la navigation.
     */
    const handleFlyoutSelect = (item: SidebarItem | SidebarSubItem) => {
        closeFlyout();
        handleItemClick(item);
    };

    // Sur mobile, le parent bascule la section dans le tiroir (toggle).
    // Sur desktop, handleItemClick gère flyout / toggle section.
    const handleBranchClick = (item: SidebarItem) => {
        if (isMobileOpen) {
            toggleSection(item.id);
            return;
        }
        handleItemClick(item);
    };

    // Clic sur le logo (dans le sidebar) :
    //   - tiroir mobile ouvert  → ferme le tiroir (via onMobileClose) ;
    //   - desktop               → replie ou déplie le menu.
    const handleBrandClick = () => {
        if (isMobileOpen) {
            closeMobile();
            return;
        }
        if (collapsible) {
            toggleCollapse();
        }
    };

    // Le brand est interactif : sur mobile il ferme le tiroir, sur desktop il replie/déplie.
    const isBrandInteractive = collapsible || !!onMobileClose;
    const brandLabel = isMobileOpen
        ? 'Fermer le menu'
        : displayCollapsed
            ? 'Déplier le menu'
            : 'Replier le menu';

    const renderItem = (item: SidebarItem, collapsed: boolean) => {
        const textLabel = toTextLabel(item.label);
        const hasChildren = item.children !== undefined && item.children.length > 0;
        const sectionOpen = isSectionOpen(item.id);
        const flyoutOpen = hasChildren && isFlyoutOpen(item.id);
        // `aria-controls` et `id` pointent sur l'élément RÉELLEMENT affiché :
        // la section en ligne quand les libellés sont visibles, le panneau
        // flottant quand ils sont masqués. Les deux ne coexistent jamais, donc
        // une seule cible à la fois — un `aria-controls` orphelin est ignoré
        // des lecteurs d'écran.
        const subitemsId = flyoutOpen
            ? `sidebar-flyout-${item.id}`
            : `sidebar-subitems-${item.id}`;
        // Replié, le libellé est masqué (display: none) : `aria-label` le rend
        // à nouveau disponible aux lecteurs d'écran, qui n'auraient sinon
        // qu'une icône sans nom.
        const accessibleName = collapsed ? textLabel : undefined;
        // Bulle visuelle au survol : seulement quand le nom ne s'affiche nulle
        // part ailleurs. Une feuille ou un parent sans flyout → bulle ; un
        // parent avec flyout → c'est le panneau lui-même qui montre le nom.
        const tooltipLabel = collapsed && (!hasChildren || !isFlyoutEnabled) ? textLabel : undefined;
        // Un parent sans feuille propre n'est pas une destination : allumer son
        // état actif quand un de ses enfants est sélectionné donnerait deux
        // entrées actives pour une seule page. `isBranchActive` couvre les deux
        // cas, et le second l'emporte.

        return (
            <div key={item.id} className="sidebar__group">
                <button
                    type="button"
                    // Le parent en flyout sert d'ancre au panneau : la ref
                    // permet à `useFloatingPosition` de le mesurer sans que le
                    // hook connaisse le rendu.
                    ref={(element: HTMLButtonElement | null) => registerFlyoutAnchor(item.id, element)}
                    className={`sidebar__item ${branchActive(item) ? 'sidebar__item--active' : ''} ${item.disabled ? 'sidebar__item--disabled' : ''}`}
                    onClick={() => handleBranchClick(item)}
                    onMouseEnter={(event) => {
                        // Parent avec flyout : le panneau affiche déjà le nom,
                        // pas de bulle par-dessus → ouvre le flyout seulement.
                        if (hasChildren) {
                            if (!isFlyoutEnabled || item.disabled || isFlyoutOpen(item.id)) return;

                            openFlyout(item.id);
                            return;
                        }

                        // Feuille (ou parent sans flyout) : la bulle redonne
                        // le nom masqué au rail, comme l'attribut `title`
                        // d'avant — le passage par `currentTarget` évite de
                        // stocker l'ancre dans un état par item.
                        if (tooltipLabel !== undefined) {
                            scheduleTooltip(event.currentTarget, tooltipLabel);
                        }
                    }}
                    onMouseLeave={() => {
                        cancelTooltip();

                        if (!hasChildren || !isFlyoutEnabled || isFlyoutOpen(item.id)) return;

                        scheduleFlyoutClose();
                    }}
                    disabled={item.disabled}
                    // `aria-expanded` décrit ce que le bouton contrôle : le
                    // sous-menu, peu importe qu'il soit en ligne ou flottant.
                    aria-expanded={hasChildren ? (collapsed ? flyoutOpen : sectionOpen) : undefined}
                    aria-controls={hasChildren ? subitemsId : undefined}
                    // `aria-haspopup` annonce qu'un clic ouvre un panneau
                    // détaché, information absente d'un simple repli en ligne.
                    aria-haspopup={hasChildren && isFlyoutEnabled ? 'menu' : undefined}
                    aria-label={accessibleName}
                >
                    {item.icon && <span className="sidebar__icon">{item.icon}</span>}
                    {!collapsed && <span className="sidebar__label">{item.label}</span>}
                    {!collapsed && hasChildren && (
                        <span className="sidebar__arrow" aria-hidden="true">
                            {sectionOpen ? '▾' : '▸'}
                        </span>
                    )}
                </button>
                {!collapsed && hasChildren && sectionOpen && (
                    <div className="sidebar__subitems" id={subitemsId}>
                        {item.children!.map((child) => {
                            const childTextLabel = toTextLabel(child.label);

                            return (
                                <button
                                    key={child.id}
                                    type="button"
                                    className={`sidebar__subitem ${isItemActive(child) ? 'sidebar__subitem--active' : ''}`}
                                    onClick={() => handleItemClick(child)}
                                    aria-current={isItemActive(child) ? 'page' : undefined}
                                    aria-label={childTextLabel}
                                >
                                    {child.icon && <span className="sidebar__icon">{child.icon}</span>}
                                    <span className="sidebar__label">{child.label}</span>
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>
        );
    };

    const renderContent = () => {
        if (groups) {
            return (filteredItems as SidebarGroup[]).map((group) => (
                <div key={group.id} className="sidebar__group-section">
                    {!displayCollapsed && <div className="sidebar__group-label">{group.label}</div>}
                    {group.items.map((item) => renderItem(item as SidebarItem, displayCollapsed))}
                </div>
            ));
        }
        return (filteredItems as SidebarItem[]).map((item) =>
            renderItem(item, displayCollapsed)
        );
    };

    /**
     * Parent du flyout courant, résolu depuis les items DÉJA filtrés par
     * permission : le panneau ne doit jamais exposer une entrée que
     * l'appelant n'a pas le droit de voir.
     */
    const flyoutItem = useMemo(() => {
        if (flyoutItemId === null) return null;

        const allItems = groups !== undefined
            ? (filteredItems as SidebarGroup[]).flatMap((group) => group.items as SidebarItem[])
            : (filteredItems as SidebarItem[]);

        return allItems.find((item) => item.id === flyoutItemId) ?? null;
    }, [flyoutItemId, groups, filteredItems]);

    return (
        // Le tiroir mobile est modal : `aria-modal` le déclare aux
        // technologies d'assistance. `inert` sur le contenu ne peut pas être
        // posé ici (le `<aside>` est le frère du contenu, pas son ancêtre) :
        // c'est le voile qui rend la page inaccessible au pointeur.
        <aside
            className={classes}
            style={style}
            id={id}
            aria-modal={isMobileOpen && isMobile ? true : undefined}
            aria-label="Menu principal"
        >
            <div className="sidebar__header">
                {isBrandInteractive ? (
                    <button
                        type="button"
                        className="sidebar__header-brand"
                        onClick={handleBrandClick}
                        aria-label={brandLabel}
                        title={brandLabel}
                    >
                        {header}
                    </button>
                ) : (
                    <div className="sidebar__header-brand">{header}</div>
                )}
                {/* Le repli n'a pas de sens dans un tiroir plein écran (mobile).
                    Sur desktop/tablette, le bouton replie/déploie le menu.
                    Masqué quand le sidebar est replié : seul le logo reste visible. */}
                {!isMobile && collapsible && !displayCollapsed && (
                    <button
                        type="button"
                        className="sidebar__collapse"
                        onClick={toggleCollapse}
                        aria-expanded={!displayCollapsed}
                        aria-controls={id}
                        aria-label={displayCollapsed ? 'Déplier le menu' : 'Replier le menu'}
                        title={displayCollapsed ? 'Déplier le menu' : 'Replier le menu'}
                    >
                        <CollapseIcon />
                    </button>
                )}
            </div>

            <nav className="sidebar__nav" aria-label="Navigation latérale">
                {renderContent()}
            </nav>

            {footer && <div className="sidebar__footer">{footer}</div>}

            {/* Panneau flottant hors du `<aside>` : il est portalé sur
                `document.body`, donc sa place dans l'arbre React ne décide
                pas de son emplacement à l'écran. Il reste ici pour être
                rendu dans le même cycle que le rail qui l'a ouvert. */}
            {isFlyoutEnabled && flyoutItem !== null && (
                <SidebarFlyout
                    parent={flyoutItem}
                    anchorEl={getFlyoutAnchor(flyoutItem.id)}
                    activeId={activeId}
                    onSelect={handleFlyoutSelect}
                    onClose={closeFlyout}
                    onPointerEnter={cancelFlyoutClose}
                    onPointerLeave={scheduleFlyoutClose}
                />
            )}

            {/* Bulle du nom au survol du rail : comme le flyout, elle est
                portalée sur `document.body` car le `<aside>` est en
                `overflow: hidden`. Elle n'existe qu'au survol (montée après
                délai), donc rien à démonter à la fermeture. */}
            {tooltip !== null && <SidebarItemTooltip anchorEl={tooltip.anchor} label={tooltip.label} />}
        </aside>
    );
}
