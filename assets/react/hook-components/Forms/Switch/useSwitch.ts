import { useMemo } from 'react';

export type SwitchVariant = 'default' | 'error' | 'success';
export type SwitchSize = 'small' | 'medium' | 'large';

export interface UseSwitchProps {
    variant?: SwitchVariant;
    fieldSize?: SwitchSize;        // on évite "size" pour ne pas entrer en conflit avec HTML
    disabled?: boolean;
    className?: string;
}

export function useSwitch({
                              variant = 'default',
                              fieldSize = 'medium',
                              disabled = false,
                              className = '',
                          }: UseSwitchProps) {
    const classes = useMemo(() => {
        const base = 'switch-field';
        const variantClass = `switch-field--${variant}`;
        const sizeClass = `switch-field--${fieldSize}`;
        const disabledClass = disabled ? 'switch-field--disabled' : '';
        return [base, variantClass, sizeClass, disabledClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, fieldSize, disabled, className]);

    return { classes };
}
