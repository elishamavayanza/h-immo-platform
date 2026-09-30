import { useMemo } from 'react';

export type TextareaVariant = 'default' | 'error' | 'success';
export type TextareaSize = 'small' | 'medium' | 'large';

export interface UseTextareaProps {
    variant?: TextareaVariant;
    size?: TextareaSize;
    fullWidth?: boolean;
    disabled?: boolean;
    readOnly?: boolean;
    className?: string;
}

export function useTextarea({
                                variant = 'default',
                                size = 'medium',
                                fullWidth = false,
                                disabled = false,
                                readOnly = false,
                                className = '',
                            }: UseTextareaProps) {
    const classes = useMemo(() => {
        const base = 'textarea-field';
        const variantClass = `textarea-field--${variant}`;
        const sizeClass = `textarea-field--${size}`;
        const fullWidthClass = fullWidth ? 'textarea-field--full-width' : '';
        const disabledClass = disabled ? 'textarea-field--disabled' : '';
        const readOnlyClass = readOnly ? 'textarea-field--readonly' : '';
        return [base, variantClass, sizeClass, fullWidthClass, disabledClass, readOnlyClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, size, fullWidth, disabled, readOnly, className]);

    const ariaProps = {
        'aria-invalid': variant === 'error' ? true : undefined,
    };

    return { classes, ariaProps };
}
