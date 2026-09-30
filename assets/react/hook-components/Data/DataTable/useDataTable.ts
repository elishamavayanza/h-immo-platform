import { useState, useMemo } from 'react';

export type SortDirection = 'asc' | 'desc';

export interface DataTableColumn<T> {
    key: keyof T | string;
    title: React.ReactNode;
    sortable?: boolean;
    render?: (row: T, index: number) => React.ReactNode;
}

export interface UseDataTableProps<T> {
    columns: DataTableColumn<T>[];
    data: T[];
    pageSize?: number;
    initialSortKey?: string;
    initialSortDirection?: SortDirection;
    className?: string;
}

export function useDataTable<T>({
                                    columns,
                                    data,
                                    pageSize = 10,
                                    initialSortKey,
                                    initialSortDirection = 'asc',
                                    className = '',
                                }: UseDataTableProps<T>) {
    const [sortKey, setSortKey] = useState<string | null>(initialSortKey || null);
    const [sortDirection, setSortDirection] = useState<SortDirection>(initialSortDirection);
    const [currentPage, setCurrentPage] = useState(1);

    const sortedData = useMemo(() => {
        if (!sortKey) return data;
        const column = columns.find((col) => col.key === sortKey);
        if (!column?.sortable) return data;
        const sorted = [...data].sort((a, b) => {
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

    const totalPages = Math.max(1, Math.ceil(sortedData.length / pageSize));
    const safeCurrentPage = Math.min(currentPage, totalPages);

    const paginatedData = useMemo(() => {
        const start = (safeCurrentPage - 1) * pageSize;
        return sortedData.slice(start, start + pageSize);
    }, [sortedData, safeCurrentPage, pageSize]);

    const toggleSort = (key: string) => {
        if (sortKey === key) {
            setSortDirection((prev) => (prev === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortKey(key);
            setSortDirection('asc');
        }
        setCurrentPage(1);
    };

    const classes = useMemo(() => {
        return ['datatable', className].filter(Boolean).join(' ');
    }, [className]);

    return {
        classes,
        columns,
        paginatedData,
        totalPages,
        currentPage: safeCurrentPage,
        setCurrentPage,
        sortKey,
        sortDirection,
        toggleSort,
    };
}
