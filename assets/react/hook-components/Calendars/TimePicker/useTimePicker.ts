import { useState, useMemo, useCallback } from 'react';

export type TimePickerVariant = 'default' | 'error' | 'success';
export type TimePickerSize = 'small' | 'medium' | 'large';

export interface UseTimePickerProps {
    value?: string;           // format "HH:mm"
    onChange?: (time: string) => void;
    variant?: TimePickerVariant;
    fieldSize?: TimePickerSize; // évite conflit avec `size` HTML
    disabled?: boolean;
    fullWidth?: boolean;
    minTime?: string;         // format "HH:mm"
    maxTime?: string;         // format "HH:mm"
    className?: string;
}

function isValidTimeFormat(time: string): boolean {
    return /^([01]\d|2[0-3]):([0-5]\d)$/.test(time);
}

function timeToMinutes(time: string): number {
    const [h, m] = time.split(':').map(Number);
    return h * 60 + m;
}

export function useTimePicker({
                                  value,
                                  onChange,
                                  variant = 'default',
                                  fieldSize = 'medium',
                                  disabled = false,
                                  fullWidth = false,
                                  minTime,
                                  maxTime,
                                  className = '',
                              }: UseTimePickerProps) {
    const [internalValue, setInternalValue] = useState(value || '');
    const currentValue = value !== undefined ? value : internalValue;

    const handleChange = useCallback(
        (newTime: string) => {
            if (!isValidTimeFormat(newTime)) return;
            if (minTime && timeToMinutes(newTime) < timeToMinutes(minTime)) return;
            if (maxTime && timeToMinutes(newTime) > timeToMinutes(maxTime)) return;
            if (value === undefined) setInternalValue(newTime);
            onChange?.(newTime);
        },
        [onChange, value, minTime, maxTime]
    );

    const classes = useMemo(() => {
        const base = 'time-picker';
        const variantClass = `time-picker--${variant}`;
        const sizeClass = `time-picker--${fieldSize}`;
        const fullWidthClass = fullWidth ? 'time-picker--full-width' : '';
        const disabledClass = disabled ? 'time-picker--disabled' : '';
        return [base, variantClass, sizeClass, fullWidthClass, disabledClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, fieldSize, fullWidth, disabled, className]);

    return {
        classes,
        value: currentValue,
        handleChange,
        disabled,
    };
}
