import React, { useMemo } from 'react';
import { useSidebar, UseSidebarProps, SidebarItem, SidebarSubItem, SidebarGroup } from '../../../hook-components/Navigation/Sidebar';
import { SidebarFlyout } from './SidebarFlyout.tsx';
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
        onItemClick?.(item);

        const hasChildren = 'children' in item && item.children !== undefined && item.children.length > 0;

        if (hasChildren) {
            const sectionId = item.id;

            // Rail mobile : le tiroir est fermé, un parent n'a nulle part où
            // montrer son sous-menu. Déplier « le rail » n'aurait aucun
            // effet visible ici (il EST déjà le rail), et cela reviendrait à
            // faire ce que fait le logo. On ouvre donc le tiroir — c'est le
            // seul geste qui mène à la section.
            if (isRail) {
                openSection(sectionId);
                openMobile();

                return;
            }

            // Rail desktop / tablette : les libellés sont masqués, un clic sur
            // le parent n'a donc rien à déplier sur place. On affiche son
            // sous-menu dans un panneau ancré (flyout) : déplier le rail à la
            // place laisserait le sous-menu inaccessible tant que le panneau
            // reste en 72px.
            if (isFlyoutEnabled) {
                isFlyoutOpen(sectionId) ? closeFlyout() : openFlyout(sectionId);

                return;
            }

            // Repli sans flyout possible (composant autonome sans rail) : on
            // déplie le menu pour rendre les libellés visibles.
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
        if (isMobileOpen) onMobileClose?.();
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

    // Parent ou enfant à la volée selon l'état courant du rail : le clic sur un
    // parent ouvre/ferme le flyout, un clic sur un parent en tiroir déplie la
    // section. Résolu ici pour que la branche mobile reste le comportement par
    // défaut et non une réflexion à chaque rendu.
    const handleBranchClick = (item: SidebarItem) => {
        if (isRail) {
            openSection(item.id);
            openMobile();

            return;
        }

        handleItemClick(item);
    };

    // Clic sur le logo — un seul contrôle pour les trois états :
    //   - tiroir mobile ouvert  → ferme le tiroir ;
    //   - rail mobile (fermé)    → ouvre le tiroir (c'est la seule entrée) ;
    //   - desktop               → replie ou déplie le menu.
    const handleBrandClick = () => {
        if (isMobileOpen) {
            onMobileClose?.();

            return;
        }

        if (isRail) {
            openMobile();

            return;
        }

        if (collapsible) {
            toggleCollapse();
        }
    };

    const isBrandInteractive = collapsible || isRail || !!onMobileClose;
    const brandLabel = isMobileOpen
        ? 'Fermer le menu'
        : isRail
            ? 'Ouvrir le menu'
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
        // Replié, le libellé est masqué (display: none) : `title` et
        // `aria-label` le rendent à nouveau disponible au survol et aux
        // lecteurs d'écran, qui n'auraient sinon qu'une icône sans nom.
        const accessibleName = collapsed ? textLabel : undefined;
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
                    onMouseEnter={() => {
                        if (!hasChildren || !isFlyoutEnabled || item.disabled) return;

                        openFlyout(item.id);
                    }}
                    onMouseLeave={() => {
                        if (!hasChildren || !isFlyoutEnabled) return;

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
                    title={accessibleName}
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
                {/* Le repli n'a pas de sens dans un tiroir plein écran, ni sur le rail
                    mobile où le logo tient déjà ce rôle : le bouton n'est pas
                    rendu du tout (pas seulement masqué par le CSS). */}
                {collapsible && !isRail && (
                    <button
                        type="button"
                        className="sidebar__collapse"
                        onClick={toggleCollapse}
                        aria-expanded={!displayCollapsed}
                        aria-controls={id}
                        aria-label={displayCollapsed ? 'Déplier le menu' : 'Replier le menu'}
                        title={displayCollapsed ? 'Déplier le menu' : 'Replier le menu'}
                    >
                        {displayCollapsed ? <ExpandIcon /> : <CollapseIcon />}
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
        </aside>
    );
}
