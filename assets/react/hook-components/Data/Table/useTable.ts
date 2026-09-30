import { useMemo, useState } from 'react';

export type TableVariant = 'default' | 'striped' | 'bordered';
export type TableSize = 'small' | 'medium' | 'large';
export type SortDirection = 'asc' | 'desc';

export interface TableColumn<T> {
    key: keyof T | string;
    title: React.ReactNode;
    sortable?: boolean;
    render?: (row: T, index: number) => React.ReactNode;
}

export interface UseTableProps<T> {
    columns: TableColumn<T>[];
    data: T[];
    variant?: TableVariant;
    size?: TableSize;
    hoverable?: boolean;
    stickyHeader?: boolean;
    fullWidth?: boolean;
    className?: string;
}

export function useTable<T>({
                                columns,
                                data,
                                variant = 'default',
                                size = 'medium',
                                hoverable = false,
                                stickyHeader = false,
                                fullWidth = true,
                                className = '',
                            }: UseTableProps<T>) {
    const [sortKey, setSortKey] = useState<string | null>(null);
    const [sortDirection, setSortDirection] = useState<SortDirection>('asc');

    const toggleSort = (key: string) => {
        if (sortKey === key) {
            setSortDirection((prev) => (prev === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortKey(key);
            setSortDirection('asc');
        }
    };

    const sortedData = useMemo(() => {
        if (!sortKey) return data;
        const column = columns.find((col) => col.key === sortKey);
        if (!column?.sortable) return data;
        const sorted = [...data].sort((a: T, b: T) => {
            const aVal = a[column.key as keyof T];
            const bVal = b[column.key as keyof T];
            if (aVal === bVal) return 0;
            if (aVal == null) return 1;
            if (bVal == null) return -1;
            if (typeof aVal === 'string' && typeof bVal === 'string') {
                return aVal.localeCompare(bVal) * (sortDirection === 'asc' ? 1 : -1);
            }
            return (aVal < bVal ? -1 : 1) * (sortDirection === 'asc' ? 1 : -1);
        });
        return sorted;
    }, [data, sortKey, sortDirection, columns]);

    const classes = useMemo(() => {
        const base = 'table';
        const variantClass = `table--${variant}`;
        const sizeClass = `table--${size}`;
        const hoverableClass = hoverable ? 'table--hoverable' : '';
        const stickyClass = stickyHeader ? 'table--sticky-header' : '';
        const fullWidthClass = fullWidth ? 'table--full-width' : '';
        return [base, variantClass, sizeClass, hoverableClass, stickyClass, fullWidthClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, size, hoverable, stickyHeader, fullWidth, className]);

    return {
        classes,
        sortedData,
        sortKey,
        sortDirection,
        toggleSort,
    };
}
