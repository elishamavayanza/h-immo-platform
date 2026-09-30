import React from 'react';
import { useAlert, UseAlertProps } from '../../../hook-components/UI/Alert';

export interface AlertProps extends UseAlertProps {
    children: React.ReactNode;
    icon?: React.ReactNode;
    onClose?: () => void;
}

export function Alert({
                          variant = 'info',
                          className,
                          children,
                          icon,
                          onClose,
                      }: AlertProps) {
    const { classes } = useAlert({ variant, className });

    return (
        <div className={classes} role="alert">
            {icon && <span className="alert__icon">{icon}</span>}
            <div className="alert__content">{children}</div>
            {onClose && (
                <button className="alert__close" onClick={onClose} aria-label={("Fermer")}>
                    &times;
                </button>
            )}
        </div>
    );
}
