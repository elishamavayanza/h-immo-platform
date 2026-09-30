import { useMemo } from 'react';

export type SelectVariant = 'default' | 'error' | 'success';
export type SelectSize = 'small' | 'medium' | 'large';

export interface UseSelectProps {
    variant?: SelectVariant;
    fieldSize?: SelectSize;           // évite le conflit avec l'attribut HTML size
    fullWidth?: boolean;
    disabled?: boolean;
    className?: string;
}

export function useSelect({
                              variant = 'default',
                              fieldSize = 'medium',
                              fullWidth = false,
                              disabled = false,
                              className = '',
                          }: UseSelectProps) {
    const classes = useMemo(() => {
        const base = 'select-field';
        const variantClass = `select-field--${variant}`;
        const sizeClass = `select-field--${fieldSize}`;
        const fullWidthClass = fullWidth ? 'select-field--full-width' : '';
        const disabledClass = disabled ? 'select-field--disabled' : '';
        return [base, variantClass, sizeClass, fullWidthClass, disabledClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, fieldSize, fullWidth, disabled, className]);

    const ariaProps = {
        'aria-invalid': variant === 'error' ? true : undefined,
    };

    return { classes, ariaProps };
}
