import React from 'react';
import { useProgress, UseProgressProps } from '../../../hook-components/UI/Progress';

export interface ProgressProps extends UseProgressProps {}

export function Progress({
                             value,
                             max,
                             variant,
                             size,
                             showLabel,
                             labelFormat,
                             className,
                         }: ProgressProps) {
    const { classes, style, label, percentage, max: maxValue } = useProgress({
        value,
        max,
        variant,
        size,
        showLabel,
        labelFormat,
        className,
    });

    return (
        <div
            className={classes}
            role="progressbar"
            aria-valuenow={value}
            aria-valuemin={0}
            aria-valuemax={maxValue}
            aria-label={label}
        >
            <div className="progress__track">
                <div className="progress__bar" style={style} />
            </div>
            {showLabel && <span className="progress__label">{label}</span>}
        </div>
    );
}
