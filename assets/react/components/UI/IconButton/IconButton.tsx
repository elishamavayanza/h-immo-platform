import React, { forwardRef } from 'react';
import { useIconButton, UseIconButtonProps } from '@/react/hook-components/UI/IconButton';
import { useI18n } from '@/react/i18n/I18nContext';

export interface IconButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement>, UseIconButtonProps {
    icon: React.ReactNode;
    ariaLabel?: string;
}

export const IconButton = forwardRef<HTMLButtonElement, IconButtonProps>(
    (
        {
            icon,
            variant = 'primary',
            fieldSize = 'medium',
            disabled = false,
            isLoading = false,
            className,
            ariaLabel,
            ...rest
        },
        ref
    ) => {
        const { t } = useI18n();
        const { classes, ariaProps } = useIconButton({
            variant,
            fieldSize,
            disabled,
            isLoading,
            className,
        });

        return (
            <button
                ref={ref}
                type="button"
                className={classes}
                disabled={disabled || isLoading}
                aria-label={typeof ariaLabel === 'string' ? t(ariaLabel) : ariaLabel}
                {...ariaProps}
                {...rest}
            >
                {isLoading ? <span className="icon-btn__spinner" aria-hidden="true" /> : icon}
            </button>
        );
    }
);

IconButton.displayName = 'IconButton';
