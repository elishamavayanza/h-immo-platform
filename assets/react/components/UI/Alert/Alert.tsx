import React from 'react';
import { useAlert, UseAlertProps } from '../../../hook-components/UI/Alert';
import { useI18n } from '@/react/i18n/I18nContext';

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
    const { t } = useI18n();
    const { classes } = useAlert({ variant, className });

    return (
        <div className={classes} role="alert">
            {icon && <span className="alert__icon">{icon}</span>}
            <div className="alert__content">{children}</div>
            {onClose && (
                <button className="alert__close" onClick={onClose} aria-label={t("Fermer")}>
                    &times;
                </button>
            )}
        </div>
    );
}
