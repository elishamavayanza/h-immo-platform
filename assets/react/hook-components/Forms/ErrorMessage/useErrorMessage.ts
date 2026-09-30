import { useMemo } from 'react';

export type ErrorMessageVariant = 'error' | 'warning' | 'info' | 'success';

export interface UseErrorMessageProps {
    variant?: ErrorMessageVariant;
    size?: 'small' | 'medium' | 'large';
    className?: string;
}

export function useErrorMessage({
                                    variant = 'error',
                                    size = 'medium',
                                    className = '',
                                }: UseErrorMessageProps) {
    const classes = useMemo(() => {
        const base = 'error-message';
        const variantClass = `error-message--${variant}`;
        const sizeClass = `error-message--${size}`;
        return [base, variantClass, sizeClass, className].filter(Boolean).join(' ');
    }, [variant, size, className]);

    return { classes };
}
