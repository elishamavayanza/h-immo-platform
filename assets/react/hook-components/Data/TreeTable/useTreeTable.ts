import { useState, useCallback, useMemo } from 'react';
import { TreeTableNode, TreeTableColumn } from './types.ts';

export interface UseTreeTableOptions<T> {
    nodes: TreeTableNode<T>[];
    columns: TreeTableColumn<T>[];
    selectedId?: string;
    filter?: string;
    treeColumnKey?: string;
    onNodeToggle?: (node: TreeTableNode<T>, expanded: boolean) => void;
}

export function useTreeTable<T>({
                                    nodes,
                                    columns,
                                    selectedId,
                                    filter,
                                    treeColumnKey,
                                    onNodeToggle,
                                }: UseTreeTableOptions<T>) {
    const [expandedMap, setExpandedMap] = useState<Record<string, boolean>>(() => {
        const initial: Record<string, boolean> = {};
        const init = (items: TreeTableNode<T>[]) => {
            items.forEach((node) => {
                if (node.expanded !== undefined) initial[node.id] = node.expanded;
                if (node.children) init(node.children);
            });
        };
        init(nodes);
        return initial;
    });

    const [selected, setSelected] = useState<string | undefined>(selectedId);
    const [sortKey, setSortKey] = useState<string | null>(null);
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');

    const toggleNode = useCallback(
        (node: TreeTableNode<T>) => {
            setExpandedMap((prev) => {
                const isExpanded = prev[node.id] ?? false;
                const next = { ...prev, [node.id]: !isExpanded };
                onNodeToggle?.(node, !isExpanded);
                return next;
            });
        },
        [onNodeToggle]
    );

    const selectNode = useCallback((node: TreeTableNode<T>) => {
        setSelected(node.id);
    }, []);

    // Filtrage récursif : conserve un nœud si son label correspond ou si un enfant correspond
    const filteredNodes = useMemo(() => {
        if (!filter) return nodes;
        const lower = filter.toLowerCase();
        const filterRecursive = (items: TreeTableNode<T>[]): TreeTableNode<T>[] => {
            return items.reduce<TreeTableNode<T>[]>((acc, node) => {
                const matches = node.label.toLowerCase().includes(lower);
                const children = node.children ? filterRecursive(node.children) : [];
                if (matches || children.length > 0) {
                    acc.push({ ...node, children });
                }
                return acc;
            }, []);
        };
        return filterRecursive(nodes);
    }, [nodes, filter]);

    // Tri récursif
    const sortedNodes = useMemo(() => {
        if (!sortKey) return filteredNodes;
        const column = columns.find((col) => col.key === sortKey);
        if (!column?.sortable) return filteredNodes;

        const sortRecursive = (items: TreeTableNode<T>[]): TreeTableNode<T>[] => {
            const sorted = [...items].sort((a, b) => {
                let aVal: any, bVal: any;
                if (treeColumnKey && sortKey === treeColumnKey) {
                    aVal = a.label;
                    bVal = b.label;
                } else {
                    aVal = a.data ? (a.data as any)[sortKey] : undefined;
                    bVal = b.data ? (b.data as any)[sortKey] : undefined;
                }
                if (aVal == null) return 1;
                if (bVal == null) return -1;
                if (typeof aVal === 'string' && typeof bVal === 'string') {
                    return aVal.localeCompare(bVal) * (sortDirection === 'asc' ? 1 : -1);
                }
                return (aVal < bVal ? -1 : 1) * (sortDirection === 'asc' ? 1 : -1);
            });
            // Trier récursivement les enfants
            return sorted.map((node) => ({
                ...node,
                children: node.children ? sortRecursive(node.children) : undefined,
            }));
        };
        return sortRecursive(filteredNodes);
    }, [filteredNodes, sortKey, sortDirection, columns, treeColumnKey]);

    const toggleSort = (key: string) => {
        if (sortKey === key) {
            setSortDirection((prev) => (prev === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortKey(key);
            setSortDirection('asc');
        }
    };

    return {
        expandedMap,
        toggleNode,
        selected,
        selectNode,
        filteredNodes: sortedNodes,
        sortKey,
        sortDirection,
        toggleSort,
    };
}
