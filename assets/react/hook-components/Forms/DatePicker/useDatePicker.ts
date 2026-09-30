import { useMemo } from 'react';

export type DatePickerVariant = 'default' | 'error' | 'success';
export type DatePickerSize = 'small' | 'medium' | 'large';

export interface UseDatePickerProps {
    variant?: DatePickerVariant;
    fieldSize?: DatePickerSize;   // évite le conflit avec l'attribut HTML size
    fullWidth?: boolean;
    disabled?: boolean;
    className?: string;
}

export function useDatePicker({
                                  variant = 'default',
                                  fieldSize = 'medium',
                                  fullWidth = false,
                                  disabled = false,
                                  className = '',
                              }: UseDatePickerProps) {
    const classes = useMemo(() => {
        const base = 'datepicker-field';
        const variantClass = `datepicker-field--${variant}`;
        const sizeClass = `datepicker-field--${fieldSize}`;
        const fullWidthClass = fullWidth ? 'datepicker-field--full-width' : '';
        const disabledClass = disabled ? 'datepicker-field--disabled' : '';
        return [base, variantClass, sizeClass, fullWidthClass, disabledClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, fieldSize, fullWidth, disabled, className]);

    const ariaProps = {
        'aria-invalid': variant === 'error' ? true : undefined,
    };

    return { classes, ariaProps };
}
