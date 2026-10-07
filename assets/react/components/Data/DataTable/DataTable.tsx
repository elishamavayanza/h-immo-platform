import React, { useState } from 'react';
import { Pagination } from '../Pagination';
import {useDataTable, UseDataTableProps} from "../../../hook-components/Data/DataTable";

export interface DataTableProps<T> extends UseDataTableProps<T> {
    /** Mode serveur : la pagination et le tri sont contrôlés par le parent. */
    mode?: 'local' | 'server';
    /** Chargement en cours (mode serveur) */
    loading?: boolean;
    /** Nombre total d'éléments (mode serveur) */
    totalItems?: number;
    /** Page courante (mode serveur, contrôlée) */
    currentPage?: number;
    /** Changement de page (mode serveur) */
    onPageChange?: (page: number) => void;
    /** Changement de tri (mode serveur) */
    onSort?: (key: string, direction: 'asc' | 'desc') => void;
}

export function DataTable<T>({
                                 columns,
                                 data,
                                 pageSize = 12,
                                 initialSortKey,
                                 initialSortDirection,
                                 className,
                                 mode = 'local',
                                 loading = false,
                                 totalItems,
                                 currentPage = 1,
                                 onPageChange,
                                 onSort,
                             }: DataTableProps<T>) {
    const isServer = mode === 'server';

    const [serverSortKey, setServerSortKey] = useState<string | null>(initialSortKey ?? null);
    const [serverSortDirection, setServerSortDirection] = useState<'asc' | 'desc'>(initialSortDirection ?? 'asc');

    const {
        classes,
        paginatedData,
        totalPages: localTotalPages,
        currentPage: localCurrentPage,
        setCurrentPage,
        sortKey: localSortKey,
        sortDirection: localSortDirection,
        toggleSort,
    } = useDataTable<T>({
        columns,
        data,
        pageSize,
        initialSortKey,
        initialSortDirection,
        className,
    });

    const sortKey = isServer ? serverSortKey : localSortKey;
    const sortDirection = isServer ? serverSortDirection : localSortDirection;

    const displayData = isServer ? data : paginatedData;
    const serverTotalPages = Math.max(1, Math.ceil((totalItems ?? data.length) / pageSize));
    const activeTotalPages = isServer ? serverTotalPages : localTotalPages;
    const activeCurrentPage = isServer ? currentPage : localCurrentPage;

    const handleSort = (key: string) => {
        const nextDirection: 'asc' | 'desc' =
            sortKey === key ? (sortDirection === 'asc' ? 'desc' : 'asc') : 'asc';

        if (isServer) {
            setServerSortKey(key);
            setServerSortDirection(nextDirection);
            onSort?.(key, nextDirection);
        } else {
            toggleSort(key);
        }
    };

    const handlePageChange = (page: number) => {
        if (isServer) {
            onPageChange?.(page);
        } else {
            setCurrentPage(page);
        }
    };

    return (
        <div className={classes}>
            <div className="datatable__wrapper" role="region" tabIndex={0} aria-label="Tableau défilable horizontalement">
                <table className="datatable__table">
                    <thead>
                    <tr>
                        {columns.map((col) => (
                            <th
                                key={String(col.key)}
                                aria-sort={
                                    col.sortable && sortKey === String(col.key)
                                        ? sortDirection === 'asc' ? 'ascending' : 'descending'
                                        : undefined
                                }
                            >
                                {col.sortable ? (
                                    <button
                                        type="button"
                                        className="datatable__sort-button"
                                        onClick={() => handleSort(String(col.key))}
                                        aria-label={`Trier par ${String(col.title)}`}
                                    >
                                        <span className="datatable__th-content">
                                            {col.title}
                                            <span className="datatable__sort-icon" aria-hidden="true">
                                                {sortKey === String(col.key)
                                                    ? sortDirection === 'asc' ? ' ▲' : ' ▼'
                                                    : ' ⇅'}
                                            </span>
                                        </span>
                                    </button>
                                ) : col.title}
                            </th>
                        ))}
                    </tr>
                    </thead>
                    <tbody>
                    {loading ? (
                        <tr>
                            <td colSpan={columns.length} className="datatable__empty datatable__loading">
                                Chargement...
                            </td>
                        </tr>
                    ) : displayData.length > 0 ? (
                        displayData.map((row, rowIndex) => (
                            <tr key={rowIndex}>
                                {columns.map((col) => (
                                    <td key={String(col.key)}>
                                        {col.render
                                            ? col.render(row, rowIndex + (activeCurrentPage - 1) * pageSize)
                                            : (row[col.key as keyof T] as React.ReactNode)}
                                    </td>
                                ))}
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td colSpan={columns.length} className="datatable__empty">
                                Aucun élément à afficher.
                            </td>
                        </tr>
                    )}
                    </tbody>
                </table>
            </div>

            {!loading && activeTotalPages > 1 && (
                <div className="datatable__pagination">
                    <Pagination
                        totalItems={totalItems ?? data.length}
                        pageSize={pageSize}
                        initialPage={activeCurrentPage}
                        currentPage={activeCurrentPage}
                        onPageChange={handlePageChange}
                    />
                </div>
            )}
        </div>
    );
}
