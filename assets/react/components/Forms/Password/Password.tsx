import React, { forwardRef } from 'react';
import { usePassword, UsePasswordProps } from '../../../hook-components/Forms/Password';
import { useI18n } from '@/react/i18n/I18nContext';

export interface PasswordProps extends React.InputHTMLAttributes<HTMLInputElement>, UsePasswordProps {
    showIcon?: React.ReactNode;
    hideIcon?: React.ReactNode;
}

const defaultShowIcon = (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
        <circle cx="12" cy="12" r="3" />
    </svg>
);

const defaultHideIcon = (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2">
        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94" />
        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19" />
        <line x1="1" y1="1" x2="23" y2="23" />
    </svg>
);

export const Password = forwardRef<HTMLInputElement, PasswordProps>(
    (
        {
            variant = 'default',
            fieldSize = 'medium',            // renommé
            fullWidth = false,
            disabled = false,
            readOnly = false,
            className,
            showIcon = defaultShowIcon,
            hideIcon = defaultHideIcon,
            ...rest
        },
        ref
    ) => {
        const { t } = useI18n();
        const { classes, ariaProps, showPassword, togglePassword } = usePassword({
            variant,
            fieldSize,                     // passage correct
            fullWidth,
            disabled,
            readOnly,
            className,
        });

        return (
            <div className="password-field__wrapper">
                <input
                    ref={ref}
                    type={showPassword ? 'text' : 'password'}
                    className={classes}
                    disabled={disabled}
                    readOnly={readOnly}
                    {...ariaProps}
                    {...rest}
                />
                <button
                    type="button"
                    className="password-field__toggle"
                    onClick={togglePassword}
                    disabled={disabled || readOnly}
                    tabIndex={-1}
                    aria-label={showPassword ? t('Masquer le mot de passe') : t('Afficher le mot de passe')}
                >
                    {showPassword ? hideIcon : showIcon}
                </button>
            </div>
        );
    }
);

Password.displayName = 'Password';
