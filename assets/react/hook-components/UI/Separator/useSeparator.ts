import { useMemo } from 'react';

export type SeparatorOrientation = 'horizontal' | 'vertical';
export type SeparatorVariant = 'default' | 'primary' | 'secondary' | 'light';

export interface UseSeparatorProps {
    orientation?: SeparatorOrientation;
    variant?: SeparatorVariant;
    thickness?: 'thin' | 'medium' | 'thick';
    label?: React.ReactNode;
    className?: string;
}

export function useSeparator({
                                 orientation = 'horizontal',
                                 variant = 'default',
                                 thickness = 'medium',
                                 label,
                                 className = '',
                             }: UseSeparatorProps) {
    const classes = useMemo(() => {
        const base = 'separator';
        const orientationClass = `separator--${orientation}`;
        const variantClass = `separator--${variant}`;
        const thicknessClass = `separator--${thickness}`;
        const labelClass = label ? 'separator--with-label' : '';
        return [base, orientationClass, variantClass, thicknessClass, labelClass, className].filter(Boolean).join(' ');
    }, [orientation, variant, thickness, label, className]);

    return { classes, label };
}
