import React, { forwardRef } from 'react';
import { useTextarea, UseTextareaProps } from '../../../hook-components/Forms/Textarea';

export interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement>, UseTextareaProps {}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
    (
        {
            variant = 'default',
            size = 'medium',
            fullWidth = false,
            disabled = false,
            readOnly = false,
            className,
            ...rest
        },
        ref
    ) => {
        const { classes, ariaProps } = useTextarea({
            variant,
            size,
            fullWidth,
            disabled,
            readOnly,
            className,
        });

        return (
            <textarea
                ref={ref}
                className={classes}
                disabled={disabled}
                readOnly={readOnly}
                {...ariaProps}
                {...rest}
            />
        );
    }
);

Textarea.displayName = 'Textarea';
