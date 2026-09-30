import { useState, useMemo } from 'react';

export type TooltipPosition = 'top' | 'bottom' | 'left' | 'right';
export type TooltipTrigger = 'hover' | 'click' | 'focus';

export interface UseTooltipProps {
    position?: TooltipPosition;
    trigger?: TooltipTrigger;
    delay?: number;
    className?: string;
}

export function useTooltip({
                               position = 'top',
                               trigger = 'hover',
                               delay = 200,
                               className = '',
                           }: UseTooltipProps) {
    const [isVisible, setIsVisible] = useState(false);
    let timeoutId: ReturnType<typeof setTimeout> | null = null;

    const show = () => {
        if (trigger === 'click') {
            setIsVisible((prev) => !prev);
            return;
        }
        if (timeoutId) clearTimeout(timeoutId);
        timeoutId = setTimeout(() => setIsVisible(true), delay);
    };

    const hide = () => {
        if (trigger === 'click') return; // pour le clic, on garde l'état
        if (timeoutId) clearTimeout(timeoutId);
        setIsVisible(false);
    };

    const toggle = () => {
        if (trigger === 'click') {
            setIsVisible((prev) => !prev);
        }
    };

    const classes = useMemo(() => {
        const base = 'tooltip';
        const positionClass = `tooltip--${position}`;
        const visibleClass = isVisible ? 'tooltip--visible' : '';
        return [base, positionClass, visibleClass, className].filter(Boolean).join(' ');
    }, [position, isVisible, className]);

    return {
        classes,
        isVisible,
        show,
        hide,
        toggle,
    };
}
