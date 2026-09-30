import React, { forwardRef } from 'react';
import { useButton, UseButtonProps } from '../../../hook-components/UI/Button';
import { useI18n } from '@/react/i18n/I18nContext';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement>, UseButtonProps {
    as?: 'button' | 'a';
    href?: string;
    spinner?: React.ReactNode;
}

const defaultSpinner = (
    <span className="btn__spinner" aria-hidden="true" />
);

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
    (
        {
            variant = 'primary',
            size = 'medium',
            isLoading = false,
            disabled = false,
            fullWidth = false,
            icon,
            iconPosition = 'left',
            className,
            children,
            spinner = defaultSpinner,
            as = 'button',
            href,
            type = 'button',
            ...rest
        },
        ref
    ) => {
        const { t } = useI18n();
        const { classes, isDisabled, ariaProps } = useButton({
            variant,
            size,
            isLoading,
            disabled,
            fullWidth,
            icon,
            iconPosition,
            className,
            children,
        });

        const content = (
            <>
                {isLoading && spinner}
                {icon && !isLoading && (
                    <span className={`btn__icon btn__icon--${iconPosition}`} aria-hidden="true">
            {icon}
          </span>
                )}
                {children && <span className="btn__label">{typeof children === 'string' ? t(children) : children}</span>}
            </>
        );

        if (as === 'a') {
            return (
                <a
                    className={classes}
                    href={href}
                    aria-disabled={isDisabled}
                    tabIndex={isDisabled ? -1 : undefined}
                    {...(rest as React.AnchorHTMLAttributes<HTMLAnchorElement>)}
                >
                    {content}
                </a>
            );
        }

        return (
            <button
                ref={ref}
                type={type}
                className={classes}
                disabled={isDisabled}
                {...ariaProps}
                {...rest}
            >
                {content}
            </button>
        );
    }
);

Button.displayName = 'Button';
