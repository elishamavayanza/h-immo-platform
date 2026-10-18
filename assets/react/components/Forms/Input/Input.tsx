import React, { forwardRef } from 'react';
import {useInput, UseInputProps} from "../../../hook-components/Forms/Input";
import { DatePicker } from '../DatePicker';
import { DateTimePicker } from '../DateTimePicker/DateTimePicker';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement>, UseInputProps {
    icon?: React.ReactNode;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
    (
        {
            variant = 'default',
            fieldSize = 'medium',      // renommé
            fullWidth = false,
            disabled = false,
            readOnly = false,
            type: inputType = 'text',
            icon,
            iconPosition = 'left',
            className,
            ...rest
        },
        ref
    ) => {
        const { classes, ariaProps } = useInput({
            variant,
            fieldSize,
            fullWidth,
            disabled,
            readOnly,
            icon,
            iconPosition,
            className,
        });

        if (inputType === 'date') {
            return <DatePicker ref={ref} {...rest} variant={variant} fieldSize={fieldSize} fullWidth={fullWidth} disabled={disabled} className={className} icon={icon} />;
        }
        if (inputType === 'datetime-local') {
            return <DateTimePicker ref={ref} {...rest} fullWidth={fullWidth} disabled={disabled} className={className} />;
        }

        return (
            <div className="input-field__wrapper">
                {icon && iconPosition === 'left' && (
                    <span className="input-field__icon input-field__icon--left" aria-hidden="true">
            {icon}
          </span>
                )}
                <input
                    ref={ref}
                    className={classes}
                    type={inputType}
                    disabled={disabled}
                    readOnly={readOnly}
                    {...ariaProps}
                    {...rest}
                />
                {icon && iconPosition === 'right' && (
                    <span className="input-field__icon input-field__icon--right" aria-hidden="true">
            {icon}
          </span>
                )}
            </div>
        );
    }
);

Input.displayName = 'Input';
