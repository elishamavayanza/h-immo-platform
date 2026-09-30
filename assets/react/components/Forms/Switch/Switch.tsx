import React, { forwardRef, useMemo } from 'react';
import { useSwitch, UseSwitchProps } from '@/react/hook-components/Forms/Switch';
import { useI18n } from '@/react/i18n/I18nContext';

export interface SwitchProps extends React.InputHTMLAttributes<HTMLInputElement>, UseSwitchProps {
    label?: React.ReactNode;
}

export const Switch = forwardRef<HTMLInputElement, SwitchProps>(
    (
        {
            variant = 'default',
            fieldSize = 'medium',
            disabled = false,
            className,
            label,
            ...rest
        },
        ref
    ) => {
        const { t } = useI18n();
        const { classes } = useSwitch({ variant, fieldSize, disabled, className });

        const wrapperClasses = useMemo(() => {
            const parts = ['switch-field__wrapper'];
            if (variant !== 'default') parts.push(`switch-field--${variant}`);
            if (fieldSize !== 'medium') parts.push(`switch-field--${fieldSize}`);
            if (disabled) parts.push('switch-field--disabled');
            if (className) parts.push(className);
            return parts.join(' ');
        }, [variant, fieldSize, disabled, className]);

        return (
            <label className={wrapperClasses}>
                <input
                    ref={ref}
                    type="checkbox"
                    className={classes}
                    disabled={disabled}
                    {...rest}
                />
                <span className="switch-field__track" aria-hidden="true">
                    <span className="switch-field__thumb" />
                </span>
                {label && <span className="switch-field__label">{typeof label === 'string' ? t(label) : label}</span>}
            </label>
        );
    }
);

Switch.displayName = 'Switch';
