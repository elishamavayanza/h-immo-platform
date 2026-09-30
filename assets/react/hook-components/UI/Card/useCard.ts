import { useMemo } from 'react';

export type CardVariant = 'default' | 'outlined' | 'elevated' | 'filled';
export type CardPadding = 'none' | 'small' | 'medium' | 'large';

export interface UseCardProps {
    variant?: CardVariant;
    padding?: CardPadding;
    interactive?: boolean;
    fullWidth?: boolean;
    className?: string;
}

export function useCard({
                            variant = 'default',
                            padding = 'medium',
                            interactive = false,
                            fullWidth = true,
                            className = '',
                        }: UseCardProps) {
    const classes = useMemo(() => {
        const base = 'card';
        const variantClass = `card--${variant}`;
        const paddingClass = `card--padding-${padding}`;
        const interactiveClass = interactive ? 'card--interactive' : '';
        const fullWidthClass = fullWidth ? 'card--full-width' : '';
        return [base, variantClass, paddingClass, interactiveClass, fullWidthClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, padding, interactive, fullWidth, className]);

    return { classes };
}
