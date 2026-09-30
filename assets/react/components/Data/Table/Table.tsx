import React from 'react';
import { useTable, UseTableProps, TableColumn } from '../../../hook-components/Data/Table';
import { useI18n } from '@/react/i18n/I18nContext';

export interface TableProps<T> extends UseTableProps<T> {
    columns: TableColumn<T>[];
    data: T[];
}

export function Table<T>({
                             columns,
                             data,
                             variant = 'default',
                             size = 'medium',
                             hoverable = false,
                             stickyHeader = false,
                             fullWidth = true,
                         className,
                     }: TableProps<T>) {
    const { t } = useI18n();
    const { classes, sortedData, sortKey, sortDirection, toggleSort } = useTable<T>({
        columns,
        data,
        variant,
        size,
        hoverable,
        stickyHeader,
        fullWidth,
        className,
    });

    return (
        <div className="table__wrapper">
            <table className={classes}>
                <thead>
                <tr>
                    {columns.map((col) => (
                        <th
                            key={String(col.key)}  // Conversion en string pour éviter symbol/number
                            className={col.sortable ? 'table__th--sortable' : ''}
                            onClick={col.sortable ? () => toggleSort(String(col.key)) : undefined}
                            aria-sort={
                                col.sortable && sortKey === String(col.key)
                                    ? sortDirection === 'asc' ? 'ascending' : 'descending'
                                    : undefined
                            }
                        >
                <span className="table__th-content">
                  {col.title}
                    {col.sortable && (
                        <span className="table__sort-icon">
                      {sortKey === String(col.key)
                          ? sortDirection === 'asc' ? ' ▲' : ' ▼'
                          : ' ⇅'}
                    </span>
                    )}
                </span>
                        </th>
                    ))}
                </tr>
                </thead>
                <tbody>
                {sortedData.length > 0 ? (
                    sortedData.map((row, rowIndex) => (
                        <tr key={rowIndex}>
                            {columns.map((col) => (
                                <td key={String(col.key)}>  {/* Conversion en string */}
                                    {col.render
                                        ? col.render(row, rowIndex)
                                        : (row[col.key as keyof T] as React.ReactNode)}
                                </td>
                            ))}
                        </tr>
                    ))
                ) : (
                    <tr>
                        <td colSpan={columns.length} className="table__empty">
                            {t('Aucune donnée disponible.')}
                        </td>
                    </tr>
                )}
                </tbody>
            </table>
        </div>
    );
}
