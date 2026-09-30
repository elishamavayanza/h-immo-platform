import { useMemo } from 'react';

export type CheckboxVariant = 'default' | 'error' | 'success';
export type CheckboxSize = 'small' | 'medium' | 'large';

export interface UseCheckboxProps {
    variant?: CheckboxVariant;
    fieldSize?: CheckboxSize;       // évite le conflit avec l'attribut HTML size
    disabled?: boolean;
    className?: string;
}

export function useCheckbox({
                                variant = 'default',
                                fieldSize = 'medium',
                                disabled = false,
                                className = '',
                            }: UseCheckboxProps) {
    const classes = useMemo(() => {
        const base = 'checkbox-field';
        const variantClass = `checkbox-field--${variant}`;
        const sizeClass = `checkbox-field--${fieldSize}`;
        const disabledClass = disabled ? 'checkbox-field--disabled' : '';
        return [base, variantClass, sizeClass, disabledClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, fieldSize, disabled, className]);

    const ariaProps = {
        'aria-invalid': variant === 'error' ? true : undefined,
    };

    return { classes, ariaProps };
}
