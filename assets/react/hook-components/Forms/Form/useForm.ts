import { useMemo } from 'react';

export type FormLayout = 'vertical' | 'horizontal' | 'inline';
export type FormGap = 'small' | 'medium' | 'large';

export interface UseFormProps {
    layout?: FormLayout;
    gap?: FormGap;
    fullWidth?: boolean;
    className?: string;
}

export function useForm({
                            layout = 'vertical',
                            gap = 'medium',
                            fullWidth = false,
                            className = '',
                        }: UseFormProps) {
    const classes = useMemo(() => {
        const base = 'form';
        const layoutClass = `form--${layout}`;
        const gapClass = `form--gap-${gap}`;
        const fullWidthClass = fullWidth ? 'form--full-width' : '';
        return [base, layoutClass, gapClass, fullWidthClass, className]
            .filter(Boolean)
            .join(' ');
    }, [layout, gap, fullWidth, className]);

    return { classes };
}
