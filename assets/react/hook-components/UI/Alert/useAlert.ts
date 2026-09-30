import { useMemo } from 'react';

export type AlertVariant = 'info' | 'success' | 'warning' | 'error';

export interface UseAlertProps {
    variant?: AlertVariant;
    className?: string;
}

export function useAlert({
                             variant = 'info',
                             className = '',
                         }: UseAlertProps) {
    const classes = useMemo(() => {
        const base = 'alert';
        const variantClass = `alert--${variant}`;
        return [base, variantClass, className].filter(Boolean).join(' ');
    }, [variant, className]);

    return { classes };
}
