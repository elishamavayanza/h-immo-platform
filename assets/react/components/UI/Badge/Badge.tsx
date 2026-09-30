import React from 'react';
import { useBadge, UseBadgeProps } from '../../../hook-components/UI/Badge';
import { useI18n } from '@/react/i18n/I18nContext';

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
    const { t } = useI18n();
    const { classes } = useBadge({ variant, size, dot, pill, icon, className });

    return (
        <span className={classes}>
      {dot && <span className="badge__dot" aria-hidden="true" />}
            {icon && <span className="badge__icon" aria-hidden="true">{icon}</span>}
            <span className="badge__text">{typeof children === 'string' ? t(children) : children}</span>
    </span>
    );
}
