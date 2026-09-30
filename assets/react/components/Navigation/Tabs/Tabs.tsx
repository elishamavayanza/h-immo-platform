import React from 'react';
import {TabItem, useTabs, UseTabsProps} from '../../../hook-components/Navigation/Tabs';
import { useI18n } from '@/react/i18n/I18nContext';

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
    const { t } = useI18n();
    const { classes, activeTabId, activeTab, selectTab } = useTabs({
        tabs,
        defaultActiveTabId,
        variant,
        onChange,
        className,
    });

    return (
        <div className={classes}>
            <div className="tabs__list" role="tablist">
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
                        disabled={tab.disabled}
                    >
                        {tab.icon && <span className="tabs__icon">{tab.icon}</span>}
                        {typeof tab.label === 'string' ? t(tab.label) : tab.label}
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
    );
}
