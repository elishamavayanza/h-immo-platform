import { useState, useMemo } from 'react';

export type PasswordVariant = 'default' | 'error' | 'success';
export type PasswordSize = 'small' | 'medium' | 'large';

export interface UsePasswordProps {
    variant?: PasswordVariant;
    fieldSize?: PasswordSize;           // renommé depuis "size"
    fullWidth?: boolean;
    disabled?: boolean;
    readOnly?: boolean;
    className?: string;
}

export function usePassword({
                                variant = 'default',
                                fieldSize = 'medium',               // renommé
                                fullWidth = false,
                                disabled = false,
                                readOnly = false,
                                className = '',
                            }: UsePasswordProps) {
    const [showPassword, setShowPassword] = useState(false);

    const togglePassword = () => {
        setShowPassword((prev) => !prev);
    };

    const classes = useMemo(() => {
        const base = 'password-field';
        const variantClass = `password-field--${variant}`;
        const sizeClass = `password-field--${fieldSize}`;   // utilise fieldSize
        const fullWidthClass = fullWidth ? 'password-field--full-width' : '';
        const disabledClass = disabled ? 'password-field--disabled' : '';
        const readOnlyClass = readOnly ? 'password-field--readonly' : '';
        return [base, variantClass, sizeClass, fullWidthClass, disabledClass, readOnlyClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, fieldSize, fullWidth, disabled, readOnly, className]);

    const ariaProps = {
        'aria-invalid': variant === 'error' ? true : undefined,
    };

    return {
        classes,
        ariaProps,
        showPassword,
        togglePassword,
    };
}
