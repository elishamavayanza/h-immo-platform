import { useState, useMemo } from 'react';

export type DateRangeVariant = 'default' | 'error' | 'success';
export type DateRangeSize = 'small' | 'medium' | 'large';

export interface DateRange {
    startDate: string;
    endDate: string;
}

export interface UseDateRangePickerProps {
    startDate?: string;
    endDate?: string;
    variant?: DateRangeVariant;
    fieldSize?: DateRangeSize; // évite conflit avec size HTML
    fullWidth?: boolean;
    disabled?: boolean;
    onChange?: (range: DateRange) => void;
    className?: string;
}

export function useDateRangePicker({
                                       startDate: initialStart = '',
                                       endDate: initialEnd = '',
                                       variant = 'default',
                                       fieldSize = 'medium',
                                       fullWidth = false,
                                       disabled = false,
                                       onChange,
                                       className = '',
                                   }: UseDateRangePickerProps) {
    const [startDate, setStartDate] = useState(initialStart);
    const [endDate, setEndDate] = useState(initialEnd);

    const updateStartDate = (value: string) => {
        setStartDate(value);
        onChange?.({ startDate: value, endDate });
    };

    const updateEndDate = (value: string) => {
        setEndDate(value);
        onChange?.({ startDate, endDate: value });
    };

    const classes = useMemo(() => {
        const base = 'daterange';
        const variantClass = `daterange--${variant}`;
        const sizeClass = `daterange--${fieldSize}`;
        const fullWidthClass = fullWidth ? 'daterange--full-width' : '';
        const disabledClass = disabled ? 'daterange--disabled' : '';
        return [base, variantClass, sizeClass, fullWidthClass, disabledClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, fieldSize, fullWidth, disabled, className]);

    return {
        classes,
        startDate,
        endDate,
        updateStartDate,
        updateEndDate,
    };
}
