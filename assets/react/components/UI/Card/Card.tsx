import React from 'react';
import {useCard, UseCardProps} from "../../../hook-components/UI/Card";


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
    const { classes } = useCard({ variant, padding, interactive, fullWidth, className });

    return (
        <div className={classes} onClick={onClick}>
            {header && <div className="card__header">{typeof header === 'string' ? (header) : header}</div>}
            <div className="card__body">{typeof children === 'string' ? (children) : children}</div>
            {footer && <div className="card__footer">{typeof footer === 'string' ? (footer) : footer}</div>}
        </div>
    );
}
