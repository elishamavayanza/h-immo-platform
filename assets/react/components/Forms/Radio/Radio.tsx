import React, { forwardRef } from 'react';
import { useRadio, UseRadioProps } from '../../../hook-components/Forms/Radio';
import { useI18n } from '@/react/i18n/I18nContext';

export interface RadioProps extends React.InputHTMLAttributes<HTMLInputElement>, UseRadioProps {
    label?: React.ReactNode;
}

export const Radio = forwardRef<HTMLInputElement, RadioProps>(
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
        const { classes, ariaProps } = useRadio({
            variant,
            fieldSize,
            disabled,
            className,
        });

        return (
            <label className={`${classes}__wrapper`}>
                <input
                    ref={ref}
                    type="radio"
                    className={classes}
                    disabled={disabled}
                    {...ariaProps}
                    {...rest}
                />
                <span className="radio-field__visual" aria-hidden="true" />
                {label && <span className="radio-field__label">{typeof label === 'string' ? t(label) : label}</span>}
            </label>
        );
    }
);

Radio.displayName = 'Radio';
