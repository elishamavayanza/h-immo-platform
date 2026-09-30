import React from 'react';

export interface TreeTableNode<T = any> {
    id: string;
    label: string;
    children?: TreeTableNode<T>[];
    /** Données associées pour les colonnes */
    data?: T;
    icon?: React.ReactNode;
    actions?: React.ReactNode;
    disabled?: boolean;
    active?: boolean;
    expanded?: boolean;
    className?: string;
}

export interface TreeTableColumn<T = any> {
    key: string;
    title: React.ReactNode;
    sortable?: boolean;
    render?: (node: TreeTableNode<T>, index: number, expanded: boolean) => React.ReactNode;
}

export interface TreeTableProps<T = any> {
    nodes: TreeTableNode<T>[];
    columns: TreeTableColumn<T>[];
    /** Clé de la colonne qui doit contenir l’arbre (chevron + icône + label) */
    treeColumnKey?: string;
    onNodeClick?: (node: TreeTableNode<T>) => void;
    onNodeDoubleClick?: (node: TreeTableNode<T>) => void;
    onNodeToggle?: (node: TreeTableNode<T>, expanded: boolean) => void;
    filter?: string;
    selectable?: boolean;
    selectedId?: string;
    size?: 'small' | 'medium' | 'large';
    variant?: 'default' | 'bordered' | 'striped';
    hoverable?: boolean;
    showLines?: boolean;
    className?: string;
}
