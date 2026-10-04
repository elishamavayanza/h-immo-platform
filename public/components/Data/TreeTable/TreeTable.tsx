import React, { useRef } from 'react';
import { useTreeTable } from '../../../../assets/react/hook-components/Data/TreeTable/useTreeTable.ts';
import { TreeTableNode, TreeTableColumn, TreeTableProps } from '../../../../assets/react/hook-components/Data/TreeTable/types.ts';
import '../../../../styles/components/Data/TreeTable.scss';

const ChevronIcon = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2">
        <polyline points="9 18 15 12 9 6" />
    </svg>
);

const FolderIcon = () => (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2">
        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" />
    </svg>
);

const LeafIcon = () => (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2">
        <circle cx="12" cy="12" r="3" />
        <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" />
    </svg>
);

export function TreeTable<T>({
                                 nodes,
                                 columns,
                                 treeColumnKey,
                                 onNodeClick,
                                 onNodeDoubleClick,
                                 onNodeToggle,
                                 filter,
                                 selectable = false,
                                 selectedId,
                                 size = 'medium',
                                 variant = 'default',
                                 hoverable = false,
                                 showLines = false,
                             className,
                         }: TreeTableProps<T>) {
    const {
        expandedMap,
        toggleNode,
        selected,
        selectNode,
        filteredNodes,
        sortKey,
        sortDirection,
        toggleSort,
    } = useTreeTable<T>({
        nodes,
        columns,
        selectedId,
        filter,
        treeColumnKey,
        onNodeToggle,
    });

    const clickTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const handleSingleClick = (node: TreeTableNode<T>) => {
        if (node.disabled) return;
        selectNode(node);
        if (clickTimer.current) {
            clearTimeout(clickTimer.current);
            clickTimer.current = null;
            return;
        }
        clickTimer.current = setTimeout(() => {
            clickTimer.current = null;
            onNodeClick?.(node);
        }, 250);
    };

    const handleDoubleClick = (node: TreeTableNode<T>) => {
        if (node.disabled) return;
        if (clickTimer.current) {
            clearTimeout(clickTimer.current);
            clickTimer.current = null;
        }
        onNodeDoubleClick?.(node);
    };

    const treeColumnIndex = treeColumnKey
        ? columns.findIndex((col) => col.key === treeColumnKey)
        : 0;

    const renderNode = (node: TreeTableNode<T>, level: number, index: number): React.ReactNode => {
        const hasChildren = node.children && node.children.length > 0;
        const isExpanded = expandedMap[node.id] ?? false;
        const isSelected = selectable && selected === node.id;
        const nodeIcon = node.icon ?? (hasChildren ? <FolderIcon /> : <LeafIcon />);

        return (
            <React.Fragment key={node.id}>
                <tr
                    className={`tree-table__row ${isSelected ? 'tree-table__row--selected' : ''} ${
                        node.disabled ? 'tree-table__row--disabled' : ''
                    }`}
                    onClick={() => handleSingleClick(node)}
                    onDoubleClick={() => handleDoubleClick(node)}
                >
                    {columns.map((col, colIndex) => {
                        if (colIndex === treeColumnIndex) {
                            // Colonne arbre : chevron + icône + label
                            return (
                                <td
                                    key={String(col.key)}
                                    style={{ paddingLeft: `${level * 20 + 12}px` }}
                                    className="tree-table__tree-cell"
                                >
                                    <span className="tree-table__tree-content">
                                        {hasChildren ? (
                                            <button
                                                className={`tree-table__toggle ${isExpanded ? 'tree-table__toggle--expanded' : ''}`}
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    toggleNode(node);
                                                }}
                                                aria-label={isExpanded ? ('Replier') : ('Déplier')}
                                            >
                                                <ChevronIcon />
                                            </button>
                                        ) : (
                                            <span className="tree-table__toggle-placeholder" />
                                        )}
                                        <span className="tree-table__icon">{nodeIcon}</span>
                                        <span className="tree-table__label">{node.label}</span>
                                        {node.actions && (
                                            <span className="tree-table__actions" onClick={(e) => e.stopPropagation()}>
                                                {node.actions}
                                            </span>
                                        )}
                                    </span>
                                </td>
                            );
                        }

                        // Colonne normale : rendu custom ou donnée brute
                        return (
                            <td key={String(col.key)}>
                                {col.render
                                    ? col.render(node, index, isExpanded)
                                    : node.data
                                        ? (node.data as any)[col.key]
                                        : null}
                            </td>
                        );
                    })}
                </tr>

                {hasChildren && isExpanded && node.children!.map((child, childIndex) =>
                    renderNode(child, level + 1, childIndex)
                )}
            </React.Fragment>
        );
    };

    const classes = [
        'tree-table',
        `tree-table--${size}`,
        `tree-table--${variant}`,
        hoverable ? 'tree-table--hoverable' : '',
        showLines ? 'tree-table--lines' : '',
        className,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <div className="tree-table__wrapper">
            <table className={classes}>
                <thead>
                <tr>
                    {columns.map((col) => (
                        <th
                            key={String(col.key)}
                            className={col.sortable ? 'tree-table__th--sortable' : ''}
                            onClick={col.sortable ? () => toggleSort(String(col.key)) : undefined}
                            aria-sort={
                                col.sortable && sortKey === String(col.key)
                                    ? sortDirection === 'asc'
                                        ? 'ascending'
                                        : 'descending'
                                    : undefined
                            }
                        >
                                <span className="tree-table__th-content">
                                    {col.title}
                                    {col.sortable && (
                                        <span className="tree-table__sort-icon">
                                            {sortKey === String(col.key)
                                                ? sortDirection === 'asc'
                                                    ? ' ▲'
                                                    : ' ▼'
                                                : ' ⇅'}
                                        </span>
                                    )}
                                </span>
                        </th>
                    ))}
                </tr>
                </thead>
                <tbody>
                {filteredNodes.length > 0 ? (
                    filteredNodes.map((node, index) => renderNode(node, 0, index))
                ) : (
                    <tr>
                        <td colSpan={columns.length} className="tree-table__empty">
                            {('Aucune donnée disponible.')}
                        </td>
                    </tr>
                )}
                </tbody>
            </table>
        </div>
    );
}
