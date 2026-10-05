import React from 'react';
import { useSpinner, UseSpinnerProps } from '../../../hook-components/UI/Spinner';

export interface SpinnerProps extends UseSpinnerProps {}

export function Spinner({ size = 'medium', variant = 'primary', className }: SpinnerProps) {
    const { classes } = useSpinner({ size, variant, className });
    return <span className={classes} role="status" aria-live="polite" />;
}
