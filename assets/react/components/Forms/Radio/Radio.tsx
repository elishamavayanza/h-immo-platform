import React, { forwardRef } from 'react';
import { useRadio, UseRadioProps } from '../../../hook-components/Forms/Radio';

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
                {label && <span className="radio-field__label">{typeof label === 'string' ? (label) : label}</span>}
            </label>
        );
    }
);

Radio.displayName = 'Radio';
