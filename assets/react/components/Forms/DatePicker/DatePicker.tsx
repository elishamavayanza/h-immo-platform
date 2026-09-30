import React, { forwardRef } from 'react';
import { useDatePicker, UseDatePickerProps } from '../../../hook-components/Forms/DatePicker';

const CalendarIcon = () => (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
        <line x1="16" y1="2" x2="16" y2="6" />
        <line x1="8" y1="2" x2="8" y2="6" />
        <line x1="3" y1="10" x2="21" y2="10" />
    </svg>
);

export interface DatePickerProps extends React.InputHTMLAttributes<HTMLInputElement>, UseDatePickerProps {
    icon?: React.ReactNode;
}

export const DatePicker = forwardRef<HTMLInputElement, DatePickerProps>(
    (
        {
            variant = 'default',
            fieldSize = 'medium',
            fullWidth = false,
            disabled = false,
            className,
            icon = <CalendarIcon />,
            ...rest
        },
        ref
    ) => {
        const { classes, ariaProps } = useDatePicker({
            variant,
            fieldSize,
            fullWidth,
            disabled,
            className,
        });

        return (
            <div className="datepicker-field-wrapper">
                {icon && (
                    <span className="datepicker-field__icon" aria-hidden="true">
                {icon}
            </span>
                )}
                <input
                    ref={ref}
                    type="date"
                    className={classes}
                    disabled={disabled}
                    {...ariaProps}
                    {...rest}
                />
            </div>
        );
    }
);

DatePicker.displayName = 'DatePicker';
