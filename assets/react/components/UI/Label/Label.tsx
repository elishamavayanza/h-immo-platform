import React, { forwardRef } from 'react';
import { useLabel, UseLabelProps } from '../../../hook-components/UI/Label';
import { useI18n } from '@/react/i18n/I18nContext';

export interface LabelProps extends React.LabelHTMLAttributes<HTMLLabelElement>, UseLabelProps {
    // Aucune prop supplémentaire nécessaire
}

export const Label = forwardRef<HTMLLabelElement, LabelProps>(
    (
        {
            variant = 'default',
            size = 'medium',
            required = false,
            disabled = false,
            htmlFor,
            icon,
            iconPosition = 'left',
            className,
            children,
            ...rest
        },
        ref
    ) => {
        const { t } = useI18n();
        const { classes, ariaProps } = useLabel({
            variant,
            size,
            required,
            disabled,
            htmlFor,
            icon,
            iconPosition,
            className,
        });

        return (
            <label
                ref={ref}
                className={classes}
                htmlFor={htmlFor}
                {...ariaProps}
                {...rest}
            >
                {icon && iconPosition === 'left' && (
                    <span className="label__icon label__icon--left" aria-hidden="true">
            {icon}
          </span>
                )}
                {children && <span className="label__text">{typeof children === 'string' ? t(children) : children}</span>}
                {required && (
                    <span className="label__required-star" aria-hidden="true">*</span>
                )}
                {icon && iconPosition === 'right' && (
                    <span className="label__icon label__icon--right" aria-hidden="true">
            {icon}
          </span>
                )}
            </label>
        );
    }
);

Label.displayName = 'Label';
