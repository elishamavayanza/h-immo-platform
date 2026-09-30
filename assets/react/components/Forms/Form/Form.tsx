import React, { forwardRef } from 'react';
import { useForm, UseFormProps } from '@/react/hook-components/Forms/Form';

export interface FormProps extends React.FormHTMLAttributes<HTMLFormElement>, UseFormProps {}

export const Form = forwardRef<HTMLFormElement, FormProps>(
    (
        {
            layout = 'vertical',
            gap = 'medium',
            fullWidth = false,
            className,
            children,
            ...rest
        },
        ref
    ) => {
        const { classes } = useForm({
            layout,
            gap,
            fullWidth,
            className,
        });

        return (
            <form ref={ref} className={classes} {...rest}>
                {children}
            </form>
        );
    }
);

Form.displayName = 'Form';
