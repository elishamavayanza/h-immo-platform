import { useMemo } from 'react';

export type SpinnerSize = 'small' | 'medium' | 'large';
export type SpinnerVariant = 'primary' | 'secondary' | 'light';

export interface UseSpinnerProps {
    size?: SpinnerSize;
    variant?: SpinnerVariant;
    className?: string;
}

export function useSpinner({ size = 'medium', variant = 'primary', className = '' }: UseSpinnerProps) {
    const classes = useMemo(() => {
        const base = 'spinner';
        const sizeClass = `spinner--${size}`;
        const variantClass = `spinner--${variant}`;
        return [base, sizeClass, variantClass, className].filter(Boolean).join(' ');
    }, [size, variant, className]);

    return { classes };
}
