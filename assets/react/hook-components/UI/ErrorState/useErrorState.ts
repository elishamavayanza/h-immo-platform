import { useMemo } from 'react';

export type ErrorStateSize = 'auto' | 'full';
export type ErrorStateTone = 'default' | 'warning' | 'danger' | 'info';

export interface UseErrorStateProps {
    size?: ErrorStateSize;
    tone?: ErrorStateTone;
    compact?: boolean;
    className?: string;
}

export function useErrorState({
                                  size = 'auto',
                                  tone = 'default',
                                  compact = false,
                                  className = '',
                              }: UseErrorStateProps) {
    const classes = useMemo(() => {
        const base = 'error-state';
        const sizeClass = `error-state--${size}`;
        const toneClass = `error-state--${tone}`;
        const compactClass = compact ? 'error-state--compact' : '';
        return [base, sizeClass, toneClass, compactClass, className]
            .filter(Boolean)
            .join(' ');
    }, [size, tone, compact, className]);

    return { classes };
}