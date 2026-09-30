import React from 'react';
import { useSeparator, UseSeparatorProps } from '../../../hook-components/UI/Separator';
import { useI18n } from '@/react/i18n/I18nContext';

export interface SeparatorProps extends UseSeparatorProps {}

export function Separator({
                              orientation = 'horizontal',
                              variant = 'default',
                              thickness = 'medium',
                              label,
                              className,
                          }: SeparatorProps) {
    const { t } = useI18n();
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
                <span className="separator__label">{typeof separatorLabel === 'string' ? t(separatorLabel) : separatorLabel}</span>
                <span className="separator__line" />
            </div>
        );
    }

    return <hr className={classes} role="separator" aria-orientation="horizontal" />;
}
