import React from 'react';
import { useEmptyState, UseEmptyStateProps } from '../../../../assets/react/hook-components/Data/EmptyState';

// Icône par défaut – une boîte vide stylisée
const DefaultIcon = () => (
    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" strokeWidth="1.5">
        <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
        <line x1="8" y1="12" x2="16" y2="12" />
        <line x1="8" y1="8" x2="12" y2="8" />
        <line x1="8" y1="16" x2="14" y2="16" />
    </svg>
);

export interface EmptyStateProps extends UseEmptyStateProps {
    icon?: React.ReactNode;
    title: React.ReactNode;
    description?: React.ReactNode;
    action?: React.ReactNode;
}

export function EmptyState({
                               variant = 'default',
                               size = 'medium',
                               fullWidth = true,
                               className,
                               icon,
                               title,
                               description,
                               action,
                           }: EmptyStateProps) {
    const { classes } = useEmptyState({ variant, size, fullWidth, className });

    return (
        <div className={classes}>
            <div className="empty-state__icon" aria-hidden="true">
                {icon || <DefaultIcon />}
            </div>
            <h3 className="empty-state__title">{typeof title === 'string' ? (title) : title}</h3>
            {description && <p className="empty-state__description">{typeof description === 'string' ? (description) : description}</p>}
            {action && <div className="empty-state__action">{action}</div>}
        </div>
    );
}
