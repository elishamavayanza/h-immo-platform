import { useMemo } from 'react';

export type AvatarSize = 'small' | 'medium' | 'large' | 'xlarge';
export type AvatarShape = 'circle' | 'square';

export interface UseAvatarProps {
    size?: AvatarSize;
    shape?: AvatarShape;
    className?: string;
}

export function useAvatar({ size = 'medium', shape = 'circle', className = '' }: UseAvatarProps) {
    const classes = useMemo(() => {
        const base = 'avatar';
        const sizeClass = `avatar--${size}`;
        const shapeClass = `avatar--${shape}`;
        return [base, sizeClass, shapeClass, className].filter(Boolean).join(' ');
    }, [size, shape, className]);

    return { classes };
}
