import { useMemo } from 'react';

export type RadioVariant = 'default' | 'error' | 'success';
export type RadioSize = 'small' | 'medium' | 'large';

export interface UseRadioProps {
    variant?: RadioVariant;
    fieldSize?: RadioSize;
    disabled?: boolean;
    className?: string;
}

export function useRadio({
                             variant = 'default',
                             fieldSize = 'medium',
                             disabled = false,
                             className = '',
                         }: UseRadioProps) {
    const classes = useMemo(() => {
        const base = 'radio-field';
        const variantClass = `radio-field--${variant}`;
        const sizeClass = `radio-field--${fieldSize}`;
        const disabledClass = disabled ? 'radio-field--disabled' : '';
        return [base, variantClass, sizeClass, disabledClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, fieldSize, disabled, className]);

    const ariaProps = {
        'aria-invalid': variant === 'error' ? true : undefined,
    };

    return { classes, ariaProps };
}
