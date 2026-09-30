import React, { forwardRef } from 'react';
import {useInput, UseInputProps} from "../../../hook-components/Forms/Input";

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
