import { useMemo } from 'react';

export type EmptyStateVariant = 'default' | 'info' | 'success' | 'warning' | 'error';
export type EmptyStateSize = 'small' | 'medium' | 'large';

export interface UseEmptyStateProps {
    variant?: EmptyStateVariant;
    size?: EmptyStateSize;
    fullWidth?: boolean;
    className?: string;
}

export function useEmptyState({
                                  variant = 'default',
                                  size = 'medium',
                                  fullWidth = true,
                                  className = '',
                              }: UseEmptyStateProps) {
    const classes = useMemo(() => {
        const base = 'empty-state';
        const variantClass = `empty-state--${variant}`;
        const sizeClass = `empty-state--${size}`;
        const fullWidthClass = fullWidth ? 'empty-state--full-width' : '';
        return [base, variantClass, sizeClass, fullWidthClass, className].filter(Boolean).join(' ');
    }, [variant, size, fullWidth, className]);

    return { classes };
}
