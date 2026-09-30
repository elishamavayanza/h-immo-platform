import { useMemo } from 'react';

export interface BreadcrumbItem {
    id: string | number;
    label: React.ReactNode;
    href?: string;
    icon?: React.ReactNode;
    disabled?: boolean;
}

export interface UseBreadcrumbProps {
    items: BreadcrumbItem[];
    separator?: React.ReactNode;
    size?: 'small' | 'medium' | 'large';
    className?: string;
}

export function useBreadcrumb({
                                  items,
                                  separator = '/',
                                  size = 'medium',
                                  className = '',
                              }: UseBreadcrumbProps) {
    const classes = useMemo(() => {
        const base = 'breadcrumb';
        const sizeClass = `breadcrumb--${size}`;
        return [base, sizeClass, className].filter(Boolean).join(' ');
    }, [size, className]);

    return { classes, items, separator };
}
