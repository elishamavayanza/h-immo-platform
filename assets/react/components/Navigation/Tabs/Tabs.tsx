import React, { useEffect, useLayoutEffect, useRef, useState } from 'react';
import {TabItem, useTabs, UseTabsProps} from '../../../hook-components/Navigation/Tabs';
import { SidebarItemTooltip } from '../Sidebar/SidebarItemTooltip';
export interface TabsProps extends UseTabsProps {
    children?: (activeTab: TabItem) => React.ReactNode; // contenu personnalisé par onglet
    renderContent?: (activeTabId: string) => React.ReactNode;
}

export function Tabs({
                         tabs,
                         defaultActiveTabId,
                         variant,
                         onChange,
                         className,
                         children,
                         renderContent,
                     }: TabsProps) {
    const tabListRef = useRef<HTMLDivElement>(null);
    const tooltipTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const [tooltip, setTooltip] = useState<{ anchor: HTMLElement; label: string } | null>(null);
    const { classes, activeTabId, activeTab, selectTab } = useTabs({
        tabs,
        defaultActiveTabId,
        variant,
        onChange,
        className,
    });

    useLayoutEffect(() => {
        const tabList = tabListRef.current;
        if (!tabList) return;

        const measureTabs = () => {
            const widestTab = Array.from(tabList.querySelectorAll<HTMLButtonElement>('.tabs__tab'))
                .reduce((widest, tab) => {
                    const label = tab.querySelector<HTMLElement>('.tabs__label');
                    const icon = tab.querySelector<HTMLElement>('.tabs__icon');
                    const style = getComputedStyle(tab);
                    const horizontalSpace = ['paddingLeft', 'paddingRight', 'borderLeftWidth', 'borderRightWidth']
                        .reduce((total, property) => total + Number.parseFloat(style[property as keyof CSSStyleDeclaration] as string), 0);
                    const iconSpace = icon
                        ? icon.getBoundingClientRect().width + Number.parseFloat(style.columnGap || style.gap || '0')
                        : 0;
                    const requiredWidth = (label?.scrollWidth ?? 0) + iconSpace + horizontalSpace;

                    return Math.max(widest, requiredWidth);
                }, 0);

            if (widestTab > 0) tabList.style.setProperty('--tab-width', `${Math.ceil(widestTab)}px`);
        };

        measureTabs();
        if (typeof ResizeObserver === 'undefined') return;

        const observer = new ResizeObserver(measureTabs);
        observer.observe(tabList);
        return () => observer.disconnect();
    }, [tabs, activeTabId]);

    useEffect(() => () => {
        if (tooltipTimerRef.current !== null) clearTimeout(tooltipTimerRef.current);
    }, []);

    const cancelTooltip = () => {
        if (tooltipTimerRef.current !== null) {
            clearTimeout(tooltipTimerRef.current);
            tooltipTimerRef.current = null;
        }
        setTooltip(null);
    };

    const scheduleTooltip = (anchor: HTMLButtonElement) => {
        const label = anchor.querySelector('.tabs__label')?.textContent?.trim();
        if (!label) return;

        if (tooltipTimerRef.current !== null) clearTimeout(tooltipTimerRef.current);
        tooltipTimerRef.current = setTimeout(() => setTooltip({ anchor, label }), 250);
    };

    return (
        <>
            <div className={classes}>
                <div
                    className="tabs__list"
                    role="tablist"
                    ref={tabListRef}
                >
                    {tabs.map((tab) => (
                        <button
                            key={tab.id}
                            role="tab"
                            id={`tab-${tab.id}`}
                            aria-selected={tab.id === activeTabId}
                            aria-controls={`panel-${tab.id}`}
                            tabIndex={tab.id === activeTabId ? 0 : -1}
                            className={`tabs__tab ${tab.id === activeTabId ? 'tabs__tab--active' : ''} ${tab.disabled ? 'tabs__tab--disabled' : ''}`}
                            onClick={() => selectTab(tab.id)}
                            onMouseEnter={(event) => scheduleTooltip(event.currentTarget)}
                            onMouseLeave={cancelTooltip}
                            onFocus={(event) => scheduleTooltip(event.currentTarget)}
                            onBlur={cancelTooltip}
                            disabled={tab.disabled}
                        >
                            {tab.icon && <span className="tabs__icon">{tab.icon}</span>}
                            <span className="tabs__label">{tab.label}</span>
                        </button>
                    ))}
                </div>

                <div
                    className="tabs__content"
                    role="tabpanel"
                    id={`panel-${activeTabId}`}
                    aria-labelledby={`tab-${activeTabId}`}
                >
                    {renderContent
                        ? renderContent(activeTabId)
                        : children
                            ? children(activeTab!)
                            : null}
                </div>
            </div>
            {tooltip && <SidebarItemTooltip anchorEl={tooltip.anchor} label={tooltip.label} placement="top" />}
        </>
    );
}
