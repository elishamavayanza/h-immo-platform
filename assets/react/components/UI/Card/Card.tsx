import React from 'react';
import { useCard, UseCardProps } from '@/react/hook-components/UI/Card';
import { useI18n } from '@/react/i18n/I18nContext';

export interface CardProps extends UseCardProps {
    header?: React.ReactNode;
    footer?: React.ReactNode;
    children: React.ReactNode;
    onClick?: React.MouseEventHandler<HTMLDivElement>;
}

export function Card({
                         variant = 'default',
                         padding = 'medium',
                         interactive = false,
                         fullWidth = true,
                         className,
                         header,
                         footer,
                         children,
                         onClick,
                     }: CardProps) {
    const { t } = useI18n();
    const { classes } = useCard({ variant, padding, interactive, fullWidth, className });

    return (
        <div className={classes} onClick={onClick}>
            {header && <div className="card__header">{typeof header === 'string' ? t(header) : header}</div>}
            <div className="card__body">{typeof children === 'string' ? t(children) : children}</div>
            {footer && <div className="card__footer">{typeof footer === 'string' ? t(footer) : footer}</div>}
        </div>
    );
}
