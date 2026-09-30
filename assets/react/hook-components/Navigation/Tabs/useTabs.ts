import { useState, useMemo } from 'react';

export type TabsVariant = 'default' | 'pills' | 'underline';

export interface TabItem {
    id: string;
    label: React.ReactNode;
    icon?: React.ReactNode;
    disabled?: boolean;
}

export interface UseTabsProps {
    tabs: TabItem[];
    defaultActiveTabId?: string;
    variant?: TabsVariant;
    onChange?: (tabId: string) => void;
    className?: string;
}

export function useTabs({
                            tabs,
                            defaultActiveTabId,
                            variant = 'default',
                            onChange,
                            className = '',
                        }: UseTabsProps) {
    const initialActive = defaultActiveTabId || (tabs.length > 0 ? tabs[0].id : '');
    const [activeTabId, setActiveTabId] = useState(initialActive);

    const activeTab = tabs.find((tab) => tab.id === activeTabId);

    const selectTab = (tabId: string) => {
        const tab = tabs.find((t) => t.id === tabId);
        if (tab && !tab.disabled) {
            setActiveTabId(tabId);
            onChange?.(tabId);
        }
    };

    const classes = useMemo(() => {
        const base = 'tabs';
        const variantClass = `tabs--${variant}`;
        return [base, variantClass, className].filter(Boolean).join(' ');
    }, [variant, className]);

    return {
        classes,
        activeTabId,
        activeTab,
        selectTab,
    };
}
