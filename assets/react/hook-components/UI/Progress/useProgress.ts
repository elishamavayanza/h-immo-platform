import { useMemo } from 'react';

export type ProgressVariant = 'primary' | 'secondary' | 'success' | 'warning' | 'error';
export type ProgressSize = 'small' | 'medium' | 'large';

export interface UseProgressProps {
    value: number;               // 0 à 100
    max?: number;                // défaut 100
    variant?: ProgressVariant;
    size?: ProgressSize;
    showLabel?: boolean;
    labelFormat?: (value: number, max: number) => string;
    className?: string;
}

export function useProgress({
                                value,
                                max = 100,
                                variant = 'primary',
                                size = 'medium',
                                showLabel = false,
                                labelFormat,
                                className = '',
                            }: UseProgressProps) {
    const percentage = Math.min(100, Math.max(0, (value / max) * 100));

    const classes = useMemo(() => {
        const base = 'progress';
        const variantClass = `progress--${variant}`;
        const sizeClass = `progress--${size}`;
        const labelClass = showLabel ? 'progress--with-label' : '';
        return [base, variantClass, sizeClass, labelClass, className].filter(Boolean).join(' ');
    }, [variant, size, showLabel, className]);

    const style = useMemo(() => {
        return { width: `${percentage}%` };
    }, [percentage]);

    const label = labelFormat
        ? labelFormat(value, max)
        : `${Math.round(percentage)}%`;

    return { classes, style, label, percentage, value, max };
}
