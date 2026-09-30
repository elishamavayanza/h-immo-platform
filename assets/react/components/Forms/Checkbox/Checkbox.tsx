import React, { forwardRef } from 'react';
import {useCheckbox, UseCheckboxProps} from "../../../hook-components/Forms/Checkbox";

export interface CheckboxProps extends React.InputHTMLAttributes<HTMLInputElement>, UseCheckboxProps {
    label?: React.ReactNode;
}

export const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(
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
        const { classes, ariaProps } = useCheckbox({
            variant,
            fieldSize,
            disabled,
            className,
        });

        return (
            <label className="checkbox-field__wrapper">
                <input
                    ref={ref}
                    type="checkbox"
                    className={classes}
                    disabled={disabled}
                    {...ariaProps}
                    {...rest}
                />
                <span className="checkbox-field__visual" aria-hidden="true" />
                {label && <span className="checkbox-field__label">{typeof label === 'string' ? (label) : label}</span>}
            </label>
        );
    }
);

Checkbox.displayName = 'Checkbox';
