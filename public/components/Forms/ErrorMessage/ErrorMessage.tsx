import React from 'react';
import { useErrorMessage, UseErrorMessageProps } from '../../../../assets/react/hook-components/Forms/ErrorMessage';

const IconError = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2">
        <circle cx="12" cy="12" r="10" />
        <line x1="15" y1="9" x2="9" y2="15" />
        <line x1="9" y1="9" x2="15" y2="15" />
    </svg>
);

const IconWarning = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
        <line x1="12" y1="9" x2="12" y2="13" />
        <line x1="12" y1="17" x2="12.01" y2="17" />
    </svg>
);

const IconInfo = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2">
        <circle cx="12" cy="12" r="10" />
        <line x1="12" y1="16" x2="12" y2="12" />
        <line x1="12" y1="8" x2="12.01" y2="8" />
    </svg>
);

const IconSuccess = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
        <polyline points="22 4 12 14.01 9 11.01" />
    </svg>
);

const variantIcons = {
    error: <IconError />,
    warning: <IconWarning />,
    info: <IconInfo />,
    success: <IconSuccess />,
};

export interface ErrorMessageProps extends UseErrorMessageProps {
    children: React.ReactNode;
    icon?: React.ReactNode;
}

export function ErrorMessage({
                                 variant = 'error',
                                 size = 'medium',
                                 className,
                                 children,
                                 icon,
                             }: ErrorMessageProps) {
    const { classes } = useErrorMessage({ variant, size, className });

    return (
        <div className={classes} role={variant === 'error' ? 'alert' : 'status'}>
      <span className="error-message__icon" aria-hidden="true">
        {icon || variantIcons[variant]}
      </span>
            <span className="error-message__text">{typeof children === 'string' ? (children) : children}</span>
        </div>
    );
}
