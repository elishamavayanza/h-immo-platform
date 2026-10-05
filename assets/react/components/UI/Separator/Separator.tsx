import React from 'react';
import { useSeparator, UseSeparatorProps } from '../../../hook-components/UI/Separator';

export interface SeparatorProps extends UseSeparatorProps {}

export function Separator({
                              orientation = 'horizontal',
                              variant = 'default',
                              thickness = 'medium',
                              label,
                              className,
                          }: SeparatorProps) {
    const { classes, label: separatorLabel } = useSeparator({
        orientation,
        variant,
        thickness,
        label,
        className,
    });

    if (orientation === 'vertical') {
        return <div className={classes} role="separator" aria-orientation="vertical" />;
    }

    if (separatorLabel) {
        return (
            <div className={classes} role="separator">
                <span className="separator__line" />
                <span className="separator__label">{typeof separatorLabel === 'string' ? (separatorLabel) : separatorLabel}</span>
                <span className="separator__line" />
            </div>
        );
    }

    return <hr className={classes} role="separator" aria-orientation="horizontal" />;
}
