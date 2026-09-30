import React, { useRef, useEffect } from 'react';
import { useTree } from '@/react/hook-components/Data/Tree/useTree';
import { TreeNode, TreeProps } from '@/react/hook-components/Data/Tree/types';
import { useI18n } from '@/react/i18n/I18nContext';
import '../../../../styles/components/Data/Tree.scss';

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

export function Tree({
                         nodes,
                         onNodeClick,
                         onNodeDoubleClick,
                         onNodeToggle,
                         filter,
                         selectable = false,
                         selectedId,
                         size = 'medium',
                         variant = 'default',
                         showLines = false,
                      }: TreeProps) {
    const { t } = useI18n();
    const {
        expandedMap,
        toggleNode,
        selected,
        selectNode,
        filteredNodes,
    } = useTree({ nodes, selectedId, filter, onNodeToggle });

    const clickTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const handleSingleClick = (node: TreeNode) => {
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

    const handleDoubleClick = (node: TreeNode) => {
        if (node.disabled) return;
        if (clickTimer.current) {
            clearTimeout(clickTimer.current);
            clickTimer.current = null;
        }
        onNodeDoubleClick?.(node);
    };

    const renderNode = (node: TreeNode, level: number): React.ReactNode => {
        const hasChildren = node.children && node.children.length > 0;
        const isExpanded = expandedMap[node.id] ?? false;
        const isSelected = selectable && selected === node.id;
        const nodeIcon = node.icon ?? (hasChildren ? <FolderIcon /> : <LeafIcon />);

        return (
            <div key={node.id} className="tree-node-container">
                <div
                    className={`tree-node tree-node--${size} ${isSelected ? 'tree-node--selected' : ''} ${
                        node.disabled ? 'tree-node--disabled' : ''
                    }`}
                    style={{ paddingLeft: `${level * 20 + 8}px` }}
                    onClick={() => handleSingleClick(node)}
                    onDoubleClick={() => handleDoubleClick(node)}
                >
                    {hasChildren ? (
                        <button
                            className={`tree-node__toggle ${isExpanded ? 'tree-node__toggle--expanded' : ''}`}
                            onClick={(e) => {
                                e.stopPropagation();
                                toggleNode(node);
                            }}
                            aria-label={isExpanded ? t('Replier') : t('Déplier')}
                        >
                            <ChevronIcon />
                        </button>
                    ) : (
                        <span className="tree-node__toggle-placeholder" />
                    )}

                    <span className="tree-node__icon">{nodeIcon}</span>
                    <span className="tree-node__label">{node.label}</span>

                    {node.actions && (
                        <span className="tree-node__actions" onClick={(e) => e.stopPropagation()}>
                            {node.actions}
                        </span>
                    )}
                </div>

                {hasChildren && isExpanded && (
                    <div className="tree-node__children">
                        {node.children!.map((child) => renderNode(child, level + 1))}
                    </div>
                )}
            </div>
        );
    };

    return (
        <div className={`tree tree--${variant} ${showLines ? 'tree--lines' : ''}`}>
            {filteredNodes.length === 0 ? (
                <div className="tree__empty">{t('Aucun résultat')}</div>
            ) : (
                filteredNodes.map((node) => renderNode(node, 0))
            )}
        </div>
    );
}
