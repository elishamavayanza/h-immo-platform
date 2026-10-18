import React, { forwardRef, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useTimePicker, UseTimePickerProps } from '../../../hook-components/Calendars/TimePicker';

const ClockIcon = () => (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
        <circle cx="12" cy="12" r="10" />
        <polyline points="12 6 12 12 16 14" />
    </svg>
);

export interface TimePickerProps extends UseTimePickerProps {
    icon?: React.ReactNode;
    inputProps?: React.InputHTMLAttributes<HTMLInputElement>;
}

function isWithinBounds(time: string, minTime?: string, maxTime?: string): boolean {
    return (!minTime || time >= minTime) && (!maxTime || time <= maxTime);
}

function isValidTime(time: string): boolean {
    return /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(time);
}

export const TimePicker = forwardRef<HTMLInputElement, TimePickerProps>(
    (
        {
            value,
            onChange,
            variant = 'default',
            fieldSize = 'medium',
            disabled = false,
            fullWidth = false,
            minTime,
            maxTime,
            className,
            icon = <ClockIcon />,
            inputProps,
        },
        ref
    ) => {
        const { classes, value: currentValue, handleChange } = useTimePicker({
            value, onChange, variant, fieldSize, disabled, fullWidth, minTime, maxTime, className,
        });
        const [inputValue, setInputValue] = useState(currentValue);
        const [isOpen, setIsOpen] = useState(false);
        const [popoverPosition, setPopoverPosition] = useState<{ left: number; top: number } | null>(null);
        const wrapperRef = useRef<HTMLDivElement>(null);
        const triggerRef = useRef<HTMLInputElement>(null);
        const popoverRef = useRef<HTMLDivElement>(null);
        const generatedId = React.useId();
        const id = inputProps?.id ?? generatedId;
        const popoverId = `${id}-time-options`;
        const selectedHour = currentValue.slice(0, 2);
        const selectedMinute = currentValue.slice(3, 5);
        const hours = useMemo(() => Array.from({ length: 24 }, (_, hour) => String(hour).padStart(2, '0')), []);
        const minutes = useMemo(() => Array.from({ length: 60 }, (_, minute) => String(minute).padStart(2, '0')), []);

        useEffect(() => {
            setInputValue(currentValue);
        }, [currentValue]);

        useLayoutEffect(() => {
            if (!isOpen) return;
            const positionPopover = () => {
                const rect = triggerRef.current?.getBoundingClientRect();
                if (!rect) return;
                const width = Math.min(340, window.innerWidth - 24);
                const estimatedHeight = Math.min(330, window.innerHeight - 24);
                const left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12));
                const below = rect.bottom + 8;
                const top = below + estimatedHeight <= window.innerHeight - 12
                    ? below
                    : Math.max(12, rect.top - estimatedHeight - 8);
                setPopoverPosition({ left, top });
            };
            const onPointerDown = (event: PointerEvent) => {
                if (event.target instanceof Node && !wrapperRef.current?.contains(event.target) && !popoverRef.current?.contains(event.target)) setIsOpen(false);
            };
            const onKeyDown = (event: KeyboardEvent) => {
                if (event.key === 'Escape') {
                    setIsOpen(false);
                    triggerRef.current?.focus();
                }
            };
            positionPopover();
            window.addEventListener('resize', positionPopover);
            window.addEventListener('scroll', positionPopover, true);
            document.addEventListener('pointerdown', onPointerDown);
            document.addEventListener('keydown', onKeyDown);
            return () => {
                window.removeEventListener('resize', positionPopover);
                window.removeEventListener('scroll', positionPopover, true);
                document.removeEventListener('pointerdown', onPointerDown);
                document.removeEventListener('keydown', onKeyDown);
            };
        }, [isOpen]);

        useLayoutEffect(() => {
            if (!isOpen || !popoverPosition || !popoverRef.current || !triggerRef.current) return;
            const trigger = triggerRef.current.getBoundingClientRect();
            const height = popoverRef.current.getBoundingClientRect().height;
            const below = trigger.bottom + 8;
            const top = below + height <= window.innerHeight - 12
                ? below
                : Math.max(12, trigger.top - height - 8);
            setPopoverPosition((current) => current && current.top !== top ? { ...current, top } : current);
        }, [isOpen, popoverPosition]);

        const selectHour = (hour: string) => {
            const minute = selectedMinute || (minTime?.startsWith(`${hour}:`) ? minTime.slice(3, 5) : '00');
            const next = `${hour}:${minute}`;
            if (!isWithinBounds(next, minTime, maxTime)) return;
            handleChange(next);
        };

        const selectMinute = (minute: string) => {
            const hour = selectedHour || minTime?.slice(0, 2) || '00';
            const next = `${hour}:${minute}`;
            if (!isWithinBounds(next, minTime, maxTime)) return;
            handleChange(next);
            setIsOpen(false);
        };

        const clear = () => {
            handleChange('');
            setIsOpen(false);
        };

        return (
            <div ref={wrapperRef} className="time-picker-wrapper">
                <input
                    ref={(element) => {
                        triggerRef.current = element;
                        if (typeof ref === 'function') ref(element);
                        else if (ref) ref.current = element;
                    }}
                    id={id}
                    type="text"
                    className={classes}
                    value={inputValue}
                    placeholder={inputProps?.placeholder ?? '--:--'}
                    disabled={disabled}
                    required={inputProps?.required}
                    inputMode="numeric"
                    maxLength={5}
                    autoComplete="off"
                    role="combobox"
                    aria-haspopup="dialog"
                    aria-expanded={isOpen}
                    aria-controls={popoverId}
                    aria-label={inputProps?.['aria-label']}
                    aria-describedby={inputProps?.['aria-describedby']}
                    aria-invalid={inputProps?.['aria-invalid']}
                    onChange={(event) => {
                        const nextTime = event.target.value;
                        setInputValue(nextTime);
                        inputProps?.onChange?.(event);
                        if (!nextTime) {
                            triggerRef.current?.setCustomValidity('');
                            handleChange('');
                            return;
                        }
                        if (!isValidTime(nextTime)) {
                            triggerRef.current?.setCustomValidity('Saisissez une heure valide au format HH:MM.');
                            return;
                        }
                        if (!isWithinBounds(nextTime, minTime, maxTime)) {
                            triggerRef.current?.setCustomValidity('L’heure choisie est en dehors de la période autorisée.');
                            return;
                        }
                        triggerRef.current?.setCustomValidity('');
                        handleChange(nextTime);
                    }}
                    onClick={() => setIsOpen((open) => !open)}
                    onKeyDown={(event) => {
                        inputProps?.onKeyDown?.(event);
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            setIsOpen((open) => !open);
                        }
                    }}
                />
                {icon && <span className="time-picker__icon" aria-hidden="true">{icon}</span>}
                {currentValue && !inputProps?.required && <button type="button" className="time-picker__clear" aria-label="Effacer l’heure" title="Effacer l’heure" onClick={clear}>×</button>}
                {inputProps?.name && <input type="hidden" name={inputProps.name} value={currentValue} />}
                {isOpen && popoverPosition && createPortal(
                    <div ref={popoverRef} id={popoverId} className="time-picker-popover" style={popoverPosition} role="dialog" aria-label="Choisir une heure">
                        <section className="time-picker-popover__column" aria-label="Heures">
                            <h3>Heure</h3>
                            <div className="time-picker-popover__options">
                                {hours.map((hour) => {
                                    const minute = selectedMinute || (minTime?.startsWith(`${hour}:`) ? minTime.slice(3, 5) : '00');
                                    const unavailable = !isWithinBounds(`${hour}:${minute}`, minTime, maxTime);
                                    return <button key={hour} type="button" className={hour === selectedHour ? 'is-selected' : undefined} aria-pressed={hour === selectedHour} disabled={unavailable} onClick={() => selectHour(hour)}>{hour}</button>;
                                })}
                            </div>
                        </section>
                        <section className="time-picker-popover__column" aria-label="Minutes">
                            <h3>Minute</h3>
                            <div className="time-picker-popover__options time-picker-popover__options--minutes">
                                {minutes.map((minute) => {
                                    const hour = selectedHour || minTime?.slice(0, 2) || '00';
                                    const unavailable = !isWithinBounds(`${hour}:${minute}`, minTime, maxTime);
                                    return <button key={minute} type="button" className={minute === selectedMinute ? 'is-selected' : undefined} aria-pressed={minute === selectedMinute} disabled={unavailable} onClick={() => selectMinute(minute)}>{minute}</button>;
                                })}
                            </div>
                        </section>
                        {currentValue && !inputProps?.required && <button type="button" className="time-picker-popover__clear" onClick={clear}>Effacer l’heure</button>}
                    </div>,
                    document.body
                )}
            </div>
        );
    }
);

TimePicker.displayName = 'TimePicker';
