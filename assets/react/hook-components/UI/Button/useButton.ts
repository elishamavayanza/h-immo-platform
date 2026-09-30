import { useMemo } from 'react';

export type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger' | 'success';
export type ButtonSize = 'small' | 'medium' | 'large';

export interface UseButtonProps {
    variant?: ButtonVariant;
    size?: ButtonSize;
    isLoading?: boolean;
    disabled?: boolean;
    fullWidth?: boolean;
    icon?: React.ReactNode;
    iconPosition?: 'left' | 'right';
    children?: React.ReactNode;
    className?: string;
}

export function useButton({
                              variant = 'primary',
                              size = 'medium',
                              isLoading = false,
                              disabled = false,
                              fullWidth = false,
                              icon,
                              iconPosition = 'left',
                              className = '',
                              children,
                          }: UseButtonProps) {
    // Construction dynamique des classes CSS
    const classes = useMemo(() => {
        const base = 'btn';
        const variantClass = `btn--${variant}`;
        const sizeClass = `btn--${size}`;
        const loadingClass = isLoading ? 'btn--loading' : '';
        const disabledClass = disabled || isLoading ? 'btn--disabled' : '';
        const fullWidthClass = fullWidth ? 'btn--full-width' : '';
        const iconOnlyClass = icon && !children ? 'btn--icon-only' : '';

        return [
            base,
            variantClass,
            sizeClass,
            loadingClass,
            disabledClass,
            fullWidthClass,
            iconOnlyClass,
            className,
        ]
            .filter(Boolean)
            .join(' ');
    }, [variant, size, isLoading, disabled, fullWidth, icon, children, className]);

    const isDisabled = disabled || isLoading;

    // Propriétés ARIA (sans `disabled` pour éviter les doublons)
    const ariaProps = {
        'aria-busy': isLoading ? true : undefined,
    };

    return {
        classes,
        isDisabled,
        ariaProps,
    };
}
