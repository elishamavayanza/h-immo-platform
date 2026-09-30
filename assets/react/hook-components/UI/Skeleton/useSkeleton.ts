import { useMemo } from 'react';

export type SkeletonVariant = 'text' | 'rect' | 'circle';
export type SkeletonSize = 'small' | 'medium' | 'large';

export interface UseSkeletonProps {
    variant?: SkeletonVariant;
    size?: SkeletonSize;
    width?: string | number;
    height?: string | number;
    className?: string;
}

export function useSkeleton({
                                variant = 'text',
                                size = 'medium',
                                width,
                                height,
                                className = '',
                            }: UseSkeletonProps) {
    const classes = useMemo(() => {
        const base = 'skeleton';
        const variantClass = `skeleton--${variant}`;
        const sizeClass = `skeleton--${size}`;
        return [base, variantClass, sizeClass, className].filter(Boolean).join(' ');
    }, [variant, size, className]);

    const style = useMemo(() => {
        return {
            width: width ?? undefined,
            height: height ?? undefined,
        };
    }, [width, height]);

    return { classes, style };
}
