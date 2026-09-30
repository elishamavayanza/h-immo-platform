import { useMemo } from 'react';

export type IconButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger';
export type IconButtonSize = 'small' | 'medium' | 'large';

export interface UseIconButtonProps {
    variant?: IconButtonVariant;
    fieldSize?: IconButtonSize;   // pour éviter conflit avec size HTML
    disabled?: boolean;
    isLoading?: boolean;
    className?: string;
}

export function useIconButton({
                                  variant = 'primary',
                                  fieldSize = 'medium',
                                  disabled = false,
                                  isLoading = false,
                                  className = '',
                              }: UseIconButtonProps) {
    const classes = useMemo(() => {
        const base = 'icon-btn';
        const variantClass = `icon-btn--${variant}`;
        const sizeClass = `icon-btn--${fieldSize}`;
        const disabledClass = disabled || isLoading ? 'icon-btn--disabled' : '';
        const loadingClass = isLoading ? 'icon-btn--loading' : '';
        return [base, variantClass, sizeClass, disabledClass, loadingClass, className].filter(Boolean).join(' ');
    }, [variant, fieldSize, disabled, isLoading, className]);

    const ariaProps = {
        'aria-busy': isLoading ? true : undefined,
    };

    return { classes, ariaProps };
}
