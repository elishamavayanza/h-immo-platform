import { useMemo } from 'react';

export type InputVariant = 'default' | 'error' | 'success';
export type InputSize = 'small' | 'medium' | 'large';

export interface UseInputProps {
    variant?: InputVariant;
    fieldSize?: InputSize;          // renommé depuis "size"
    fullWidth?: boolean;
    disabled?: boolean;
    readOnly?: boolean;
    icon?: React.ReactNode;
    iconPosition?: 'left' | 'right';
    className?: string;
}

export function useInput({
                             variant = 'default',
                             fieldSize = 'medium',
                             fullWidth = false,
                             disabled = false,
                             readOnly = false,
                             icon,
                             iconPosition = 'left',
                             className = '',
                         }: UseInputProps) {
    const classes = useMemo(() => {
        const base = 'input-field';
        const variantClass = `input-field--${variant}`;
        const sizeClass = `input-field--${fieldSize}`;
        const fullWidthClass = fullWidth ? 'input-field--full-width' : '';
        const disabledClass = disabled ? 'input-field--disabled' : '';
        const readOnlyClass = readOnly ? 'input-field--readonly' : '';
        const withIconClass = icon ? `input-field--with-icon input-field--with-icon-${iconPosition}` : '';
        return [base, variantClass, sizeClass, fullWidthClass, disabledClass, readOnlyClass, withIconClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, fieldSize, fullWidth, disabled, readOnly, icon, iconPosition, className]);

    const ariaProps = {
        'aria-invalid': variant === 'error' ? true : undefined,
    };

    return { classes, ariaProps };
}
