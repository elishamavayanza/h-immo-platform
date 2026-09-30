import { useMemo } from 'react';

export type ListVariant = 'default' | 'striped' | 'bordered' | 'separated';
export type ListSize = 'small' | 'medium' | 'large';
export type ListOrder = 'unordered' | 'ordered' | 'none';

export interface UseListProps {
    variant?: ListVariant;
    size?: ListSize;
    order?: ListOrder;
    fullWidth?: boolean;
    className?: string;
}

export function useList({
                            variant = 'default',
                            size = 'medium',
                            order = 'none',
                            fullWidth = true,
                            className = '',
                        }: UseListProps) {
    const classes = useMemo(() => {
        const base = 'list';
        const variantClass = `list--${variant}`;
        const sizeClass = `list--${size}`;
        const orderClass = `list--${order}`;
        const fullWidthClass = fullWidth ? 'list--full-width' : '';
        return [base, variantClass, sizeClass, orderClass, fullWidthClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, size, order, fullWidth, className]);

    return { classes };
}
