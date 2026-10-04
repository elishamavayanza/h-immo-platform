import React, { forwardRef } from 'react';
import {useSelect, UseSelectProps} from "../../../../assets/react/hook-components/Forms/Select";


export interface SelectOption {
    value: string | number;
    label: string;
    disabled?: boolean;
}

export interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement>, UseSelectProps {
    options: SelectOption[];
    placeholder?: string;
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(
    (
        {
            variant = 'default',
            fieldSize = 'medium',
            fullWidth = false,
            disabled = false,
            className,
            options,
            placeholder,
            children,
            ...rest
        },
        ref
    ) => {
        const { classes, ariaProps } = useSelect({
            variant,
            fieldSize,
            fullWidth,
            disabled,
            className,
        });

        return (
            <div className="select-field__wrapper">
                <select
                    ref={ref}
                    className={classes}
                    disabled={disabled}
                    {...ariaProps}
                    {...rest}
                >
                    {placeholder && <option value="">{(placeholder)}</option>}
                    {options.map((opt) => (
                        <option key={opt.value} value={opt.value} disabled={opt.disabled}>
                            {opt.label}
                        </option>
                    ))}
                    {children}
                </select>
                <span className="select-field__arrow" aria-hidden="true" />
            </div>
        );
    }
);

Select.displayName = 'Select';
