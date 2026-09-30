import React, { forwardRef } from 'react';
import { useTooltip, UseTooltipProps } from '../../../hook-components/UI/Tooltip';
import { useI18n } from '@/react/i18n/I18nContext';

export interface TooltipProps extends UseTooltipProps {
    content: React.ReactNode;
    children: React.ReactNode;
}

export function Tooltip({ content, children, position, trigger, delay, className }: TooltipProps) {
    const { t } = useI18n();
    const { classes, show, hide, toggle } = useTooltip({ position, trigger, delay, className });

    const handleMouseEnter = () => show();
    const handleMouseLeave = () => hide();
    const handleFocus = () => show();
    const handleBlur = () => hide();
    const handleClick = () => toggle();

    return (
        <span
            className="tooltip__wrapper"
            onMouseEnter={handleMouseEnter}
            onMouseLeave={handleMouseLeave}
            onFocus={handleFocus}
            onBlur={handleBlur}
            onClick={handleClick}
        >
      {children}
            <span className={classes} role="tooltip">
        {typeof content === 'string' ? t(content) : content}
      </span>
    </span>
    );
}
