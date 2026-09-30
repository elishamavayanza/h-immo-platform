import React, { forwardRef } from 'react';
import { useTimePicker, UseTimePickerProps } from '../../../hook-components/Calendars/TimePicker';

const ClockIcon = () => (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2">
        <circle cx="12" cy="12" r="10" />
        <polyline points="12 6 12 12 16 14" />
    </svg>
);

export interface TimePickerProps extends UseTimePickerProps {
    icon?: React.ReactNode;
    inputProps?: React.InputHTMLAttributes<HTMLInputElement>;
}

export const TimePicker = forwardRef<HTMLInputElement, TimePickerProps>(
    (
        {
            value,
            onChange,
            variant = 'default',
            fieldSize = 'medium',
            disabled = false,
            fullWidth = false,
            minTime,
            maxTime,
            className,
            icon = <ClockIcon />,
            inputProps,
        },
        ref
    ) => {
        const { classes, value: currentValue, handleChange } = useTimePicker({
            value,
            onChange,
            variant,
            fieldSize,
            disabled,
            fullWidth,
            minTime,
            maxTime,
            className,
        });

        return (
            <div className="time-picker-wrapper">
                {icon && (
                    <span className="time-picker__icon" aria-hidden="true">
                {icon}
            </span>
                )}
                <input
                    ref={ref}
                    type="time"
                    className={classes}
                    value={currentValue}
                    onChange={(e) => handleChange(e.target.value)}
                    disabled={disabled}
                    min={minTime}
                    max={maxTime}
                    {...inputProps}
                />
            </div>
        );
    }
);

TimePicker.displayName = 'TimePicker';
