import { useState, useMemo } from 'react';

export type RightSidebarVariant = 'default' | 'light' | 'dark';
export type RightSidebarSize = 'small' | 'medium' | 'large';

export interface UseRightSidebarProps {
    variant?: RightSidebarVariant;
    size?: RightSidebarSize;
    collapsible?: boolean;
    defaultCollapsed?: boolean;
    className?: string;
}

export function useRightSidebar({
                                    variant = 'default',
                                    size = 'medium',
                                    collapsible = false,
                                    defaultCollapsed = false,
                                    className = '',
                                }: UseRightSidebarProps) {
    const [isCollapsed, setIsCollapsed] = useState(defaultCollapsed);

    const toggleCollapse = () => {
        if (collapsible) setIsCollapsed((prev) => !prev);
    };

    const classes = useMemo(() => {
        const base = 'right-sidebar';
        const variantClass = `right-sidebar--${variant}`;
        const sizeClass = `right-sidebar--${size}`;
        const collapsedClass = collapsible && isCollapsed ? 'right-sidebar--collapsed' : '';
        return [base, variantClass, sizeClass, collapsedClass, className].filter(Boolean).join(' ');
    }, [variant, size, collapsible, isCollapsed, className]);

    return {
        classes,
        isCollapsed,
        toggleCollapse,
    };
}
