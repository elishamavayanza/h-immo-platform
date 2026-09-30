import { useMemo } from 'react';

export type LabelVariant = 'default' | 'error' | 'success' | 'warning';
export type LabelSize = 'small' | 'medium' | 'large';

export interface UseLabelProps {
    variant?: LabelVariant;
    size?: LabelSize;
    required?: boolean;
    disabled?: boolean;
    htmlFor?: string;
    icon?: React.ReactNode;
    iconPosition?: 'left' | 'right';
    className?: string;
}

export function useLabel({
                             variant = 'default',
                             size = 'medium',
                             required = false,
                             disabled = false,
                             htmlFor,
                             icon,
                             iconPosition = 'left',
                             className = '',
                         }: UseLabelProps) {
    const classes = useMemo(() => {
        const base = 'label';
        const variantClass = `label--${variant}`;
        const sizeClass = `label--${size}`;
        const requiredClass = required ? 'label--required' : '';
        const disabledClass = disabled ? 'label--disabled' : '';
        const withIconClass = icon ? `label--with-icon label--with-icon-${iconPosition}` : '';
        return [base, variantClass, sizeClass, requiredClass, disabledClass, withIconClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, size, required, disabled, icon, iconPosition, className]);

    const ariaProps = {
        'aria-disabled': disabled ? true : undefined,
    };

    return { classes, ariaProps };
}
