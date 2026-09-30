import { useMemo } from 'react';

export type BadgeVariant = 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'error' | 'info';
export type BadgeSize = 'small' | 'medium' | 'large';

export interface UseBadgeProps {
    variant?: BadgeVariant;
    size?: BadgeSize;
    dot?: boolean;
    pill?: boolean;
    icon?: React.ReactNode;
    className?: string;
}

export function useBadge({
                             variant = 'default',
                             size = 'medium',
                             dot = false,
                             pill = false,
                             icon,
                             className = '',
                         }: UseBadgeProps) {
    const classes = useMemo(() => {
        const base = 'badge';
        const variantClass = `badge--${variant}`;
        const sizeClass = `badge--${size}`;
        const dotClass = dot ? 'badge--dot' : '';
        const pillClass = pill ? 'badge--pill' : '';
        const withIconClass = icon ? 'badge--with-icon' : '';
        return [base, variantClass, sizeClass, dotClass, pillClass, withIconClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, size, dot, pill, icon, className]);

    return { classes };
}
