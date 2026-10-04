import React from 'react';
import { useBadge, UseBadgeProps } from '../../../../assets/react/hook-components/UI/Badge';

export interface BadgeProps extends UseBadgeProps {
    children: React.ReactNode;
}

export function Badge({
                          variant = 'default',
                          size = 'medium',
                          dot = false,
                          pill = false,
                          icon,
                          className,
                          children,
                      }: BadgeProps) {
    const { classes } = useBadge({ variant, size, dot, pill, icon, className });

    return (
        <span className={classes}>
      {dot && <span className="badge__dot" aria-hidden="true" />}
            {icon && <span className="badge__icon" aria-hidden="true">{icon}</span>}
            <span className="badge__text">{typeof children === 'string' ? (children) : children}</span>
    </span>
    );
}
