import React from 'react';
import { useSidebar, UseSidebarProps, SidebarItem, SidebarSubItem, SidebarGroup } from '../../../hook-components/Navigation/Sidebar';
import { useI18n } from '@/react/i18n/I18nContext';

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

export interface SidebarProps extends UseSidebarProps {
    header?: React.ReactNode;
    footer?: React.ReactNode;
    activeRoute?: string;
}

export function Sidebar({
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
        isCollapsed,
        toggleCollapse,
        openSections,
        toggleSection,
        filteredItems,
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
    const { t } = useI18n();

    // Forcer l’affichage des textes sur mobile (collapsible=false)
    const displayCollapsed = collapsible ? isCollapsed : false;
    const classes = collapsible
        ? hookClasses
        : hookClasses.replace(' sidebar--collapsed', '');

    const handleItemClick = (item: SidebarItem | SidebarSubItem) => {
        onItemClick?.(item);
        if ('children' in item && item.children) {
            toggleSection(item.id);
        }
    };

    const isItemActive = (item: SidebarItem | SidebarSubItem): boolean => {
        if (item.active) return true;
        if (activeId && item.id === activeId) return true;
        if (activeRoute && 'route' in item && item.route === activeRoute) return true;
        return false;
    };

    // Clic sur le logo : replie/déplie si collapsible, sinon ferme le mobile
    const handleBrandClick = () => {
        if (collapsible) {
            toggleCollapse();
        } else if (onMobileClose) {
            onMobileClose();
        }
    };

    const handleBrandKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            handleBrandClick();
        }
    };

    const isBrandInteractive = collapsible || !!onMobileClose;

    const renderItem = (item: SidebarItem, collapsed: boolean) => (
        <div key={item.id} className="sidebar__group">
            <div
                className={`sidebar__item ${isItemActive(item) ? 'sidebar__item--active' : ''} ${item.disabled ? 'sidebar__item--disabled' : ''}`}
                onClick={() => handleItemClick(item)}
                role="button"
                tabIndex={item.disabled ? -1 : 0}
            >
                {item.icon && <span className="sidebar__icon">{item.icon}</span>}
                {!collapsed && <span className="sidebar__label">{item.label}</span>}
                {!collapsed && item.children && (
                    <span className="sidebar__arrow">
                        {openSections[item.id] ? '▾' : '▸'}
                    </span>
                )}
            </div>
            {!collapsed && item.children && openSections[item.id] && (
                <div className="sidebar__subitems">
                    {item.children.map((child) => (
                        <div
                            key={child.id}
                            className={`sidebar__subitem ${isItemActive(child) ? 'sidebar__subitem--active' : ''}`}
                            onClick={() => handleItemClick(child)}
                            role="button"
                            tabIndex={0}
                        >
                            {child.icon && <span className="sidebar__icon">{child.icon}</span>}
                            <span className="sidebar__label">{child.label}</span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );

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
        <aside className={classes}>
            <div className="sidebar__header">
                <div
                    className="sidebar__header-brand"
                    onClick={handleBrandClick}
                    role={isBrandInteractive ? 'button' : undefined}
                    tabIndex={isBrandInteractive ? 0 : undefined}
                    onKeyDown={isBrandInteractive ? handleBrandKeyDown : undefined}
                    title={
                        collapsible
                            ? (displayCollapsed ? t('Déplier le menu') : t('Replier le menu'))
                            : onMobileClose
                                ? t('Fermer le menu')
                                : undefined
                    }
                >
                    {header}
                </div>
                {collapsible && !displayCollapsed && (
                    <button
                        className="sidebar__collapse"
                        onClick={toggleCollapse}
                        aria-label={t('Replier le menu')}
                        title={t('Replier')}
                    >
                        <CollapseIcon />
                    </button>
                )}
            </div>

            <nav className="sidebar__nav" aria-label={t('Navigation latérale')}>
                {renderContent()}
            </nav>

            {footer && <div className="sidebar__footer">{footer}</div>}
        </aside>
    );
}
