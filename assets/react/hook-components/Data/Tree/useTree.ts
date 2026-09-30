import { useState, useCallback, useMemo } from 'react';
import { TreeNode } from './types.ts';

export interface UseTreeOptions {
    nodes: TreeNode[];
    selectedId?: string;
    filter?: string;
    onNodeToggle?: (node: TreeNode, expanded: boolean) => void;
}

export function useTree({ nodes, selectedId, filter, onNodeToggle }: UseTreeOptions) {
    // Map pour stocker l'état d'expansion : id -> boolean
    const [expandedMap, setExpandedMap] = useState<Record<string, boolean>>(() => {
        const initial: Record<string, boolean> = {};
        const init = (nodes: TreeNode[]) => {
            nodes.forEach((node) => {
                if (node.expanded !== undefined) {
                    initial[node.id] = node.expanded;
                }
                if (node.children) init(node.children);
            });
        };
        init(nodes);
        return initial;
    });

    const [selected, setSelected] = useState<string | undefined>(selectedId);

    const toggleNode = useCallback(
        (node: TreeNode) => {
            setExpandedMap((prev) => {
                const isExpanded = prev[node.id] ?? false;
                const next = { ...prev, [node.id]: !isExpanded };
                onNodeToggle?.(node, !isExpanded);
                return next;
            });
        },
        [onNodeToggle]
    );

    const selectNode = useCallback((node: TreeNode) => {
        setSelected(node.id);
    }, []);

    // Filtrage simple : on garde un nœud si son label correspond au filtre, ou si un descendant correspond
    const filteredNodes = useMemo(() => {
        if (!filter) return nodes;
        const lowercaseFilter = filter.toLowerCase();
        const filterNodes = (nodes: TreeNode[]): TreeNode[] => {
            return nodes.reduce<TreeNode[]>((acc, node) => {
                const matches = node.label.toLowerCase().includes(lowercaseFilter);
                const children = node.children ? filterNodes(node.children) : [];
                if (matches || children.length > 0) {
                    acc.push({ ...node, children });
                }
                return acc;
            }, []);
        };
        return filterNodes(nodes);
    }, [nodes, filter]);

    return {
        expandedMap,
        toggleNode,
        selected,
        selectNode,
        filteredNodes,
    };
}
