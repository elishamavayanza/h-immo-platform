import React from 'react';
import { useSidebar, UseSidebarProps, SidebarItem, SidebarSubItem, SidebarGroup } from '../../../hook-components/Navigation/Sidebar';

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
                            activeRoute,
                            onItemClick,
                            className,
                            header,
                            footer,
                            userPermissions,
                            mobileOpen = false,
                            onMobileClose,
                        }: SidebarProps) {
    const {
        classes: hookClasses,
        style,
        toggleCollapse,
        isSectionOpen,
        toggleSection,
        openSection,
        filteredItems,
        isMobileOpen,
        isVisuallyCollapsed,
        isRail,
        openMobile,
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

    const handleItemClick = (item: SidebarItem | SidebarSubItem) => {
        onItemClick?.(item);

        const hasChildren = 'children' in item && item.children !== undefined && item.children.length > 0;

        if (hasChildren) {
            const sectionId = item.id;

            // Replié, un parent n'a nowhere où afficher son sous-menu :
            // le clic déplie donc le rail ET ouvre la section. C'est le
            // comportement le plus prévisible sans introduire de panneau
            // flottant, et le clic n'est jamais perdu.
            if (displayCollapsed) {
                toggleCollapse();
                openSection(sectionId);

                return;
            }

            toggleSection(sectionId);

            return;
        }

        // Navigation vers une feuille : le drawer mobile se referme.
        if (isMobileOpen) onMobileClose?.();
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
        const subitemsId = `sidebar-subitems-${item.id}`;
        // Replié, le libellé est masqué (display: none) : `title` et
        // `aria-label` le rendent à nouveau disponible au survol et aux
        // lecteurs d'écran, qui n'auraient sinon qu'une icône sans nom.
        const accessibleName = collapsed ? textLabel : undefined;

        return (
            <div key={item.id} className="sidebar__group">
                <button
                    type="button"
                    className={`sidebar__item ${isItemActive(item) ? 'sidebar__item--active' : ''} ${item.disabled ? 'sidebar__item--disabled' : ''}`}
                    onClick={() => handleItemClick(item)}
                    disabled={item.disabled}
                    aria-expanded={hasChildren ? sectionOpen : undefined}
                    aria-controls={hasChildren ? subitemsId : undefined}
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

    return (
        <aside className={classes} style={style} id={id}>
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
        </aside>
    );
}
