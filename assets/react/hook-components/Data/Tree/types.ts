import { ReactNode } from 'react';

export interface TreeNode {
    id: string;
    label: string;
    icon?: ReactNode;
    children?: TreeNode[];
    /** Donnée métier optionnelle attachée au nœud */
    data?: unknown;
    /** Actions/boutons affichés à droite du nœud */
    actions?: ReactNode;
    /** État d'expansion initial (défaut : false) */
    expanded?: boolean;
    /** Classes CSS additionnelles */
    className?: string;
    /** Désactiver le nœud */
    disabled?: boolean;
    /** Indique si le nœud est actif/sélectionné */
    active?: boolean;
}

export interface TreeProps {
    nodes: TreeNode[];
    /** Callback au clic simple sur un nœud */
    onNodeClick?: (node: TreeNode) => void;
    /** Callback au double clic sur un nœud */
    onNodeDoubleClick?: (node: TreeNode) => void;
    /** Callback au clic sur le chevron (expansion) */
    onNodeToggle?: (node: TreeNode, expanded: boolean) => void;
    /** Permet de filtrer (on garde les nœuds correspondants et leurs parents) */
    filter?: string;
    /** Indique si les nœuds sont sélectionnables */
    selectable?: boolean;
    /** Nœud sélectionné par défaut */
    selectedId?: string;
    /** Taille : small, medium, large */
    size?: 'small' | 'medium' | 'large';
    /** Variante : default, bordered, ghost */
    variant?: 'default' | 'bordered' | 'ghost';
    /** Afficher les lignes de connexion entre nœuds */
    showLines?: boolean;
}
