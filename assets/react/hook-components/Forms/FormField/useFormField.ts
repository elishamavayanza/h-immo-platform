import { useMemo } from 'react';

export type FormFieldVariant = 'default' | 'error' | 'success';

export interface UseFormFieldProps {
    label?: React.ReactNode;
    htmlFor?: string;
    required?: boolean;
    error?: React.ReactNode;
    helpText?: React.ReactNode;
    variant?: FormFieldVariant;
    fullWidth?: boolean;
    className?: string;
}

export function useFormField({
                                 label,
                                 htmlFor,
                                 required = false,
                                 error,
                                 helpText,
                                 variant = 'default',
                                 fullWidth = true,
                                 className = '',
                             }: UseFormFieldProps) {
    const classes = useMemo(() => {
        const base = 'form-field';
        const variantClass = `form-field--${variant}`;
        const requiredClass = required ? 'form-field--required' : '';
        const fullWidthClass = fullWidth ? 'form-field--full-width' : '';
        return [base, variantClass, requiredClass, fullWidthClass, className].filter(Boolean).join(' ');
    }, [variant, required, fullWidth, className]);

    return {
        classes,
        error,
        helpText,
        label,
        htmlFor,
    };
}
