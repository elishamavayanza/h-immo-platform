import React from 'react';
import { useFormField, UseFormFieldProps } from '@/react/hook-components/Forms/FormField';
import { useI18n } from '@/react/i18n/I18nContext';

export interface FormFieldProps extends UseFormFieldProps {
    children: React.ReactNode;
}

export function FormField({
                              label,
                              htmlFor,
                              required = false,
                              error,
                              helpText,
                              variant = 'default',
                              fullWidth = true,
                              className,
                              children,
                          }: FormFieldProps) {
    const { t } = useI18n();
    const { classes } = useFormField({
        label,
        htmlFor,
        required,
        error,
        helpText,
        variant,
        fullWidth,
        className,
    });

    return (
        <div className={classes}>
            {label && (
                <label className="form-field__label" htmlFor={htmlFor}>
                    {typeof label === 'string' ? t(label) : label}
                    {required && <span className="form-field__required-star" aria-hidden="true">*</span>}
                </label>
            )}
            <div className="form-field__control">{children}</div>
            {error && <div className="form-field__error" role="alert">{typeof error === 'string' ? t(error) : error}</div>}
            {helpText && !error && <div className="form-field__help">{typeof helpText === 'string' ? t(helpText) : helpText}</div>}
        </div>
    );
}
