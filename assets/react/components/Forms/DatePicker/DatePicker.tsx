import React, { forwardRef, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Calendar } from '../../Calendars/Calendar';
import { useDatePicker, UseDatePickerProps } from '../../../hook-components/Forms/DatePicker';

const CalendarIcon = () => (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
        <line x1="16" y1="2" x2="16" y2="6" />
        <line x1="8" y1="2" x2="8" y2="6" />
        <line x1="3" y1="10" x2="21" y2="10" />
    </svg>
);

export interface DatePickerProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type' | 'onChange'>, UseDatePickerProps {
    icon?: React.ReactNode;
    onChange?: React.ChangeEventHandler<HTMLInputElement>;
}

function parseDate(value?: string | null): Date | null {
    if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day ? date : null;
}

function dateValue(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function displayDate(date: Date | null, placeholder?: string): string {
    return date ? new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date) : placeholder ?? '';
}

function parseDisplayedDate(value: string): Date | null {
    const normalized = value.trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(normalized)) return parseDate(normalized);
    const match = /^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})$/.exec(normalized);
    if (!match) return null;
    const [, day, month, year] = match;
    return parseDate(`${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`);
}

export const DatePicker = forwardRef<HTMLInputElement, DatePickerProps>(
    ({
        variant = 'default', fieldSize = 'medium', fullWidth = false, disabled = false,
        className, icon = <CalendarIcon />, value, defaultValue, onChange, min, max,
        id, name, required, 'aria-label': ariaLabel, 'aria-describedby': ariaDescribedBy,
        placeholder, ...inputProps
    }, ref) => {
        const { classes } = useDatePicker({ variant, fieldSize, fullWidth, disabled, className });
        const [uncontrolledValue, setUncontrolledValue] = useState(typeof defaultValue === 'string' ? defaultValue : '');
        const rawValue = typeof value === 'string' ? value : uncontrolledValue;
        const selectedDate = parseDate(rawValue);
        const [displayValue, setDisplayValue] = useState(displayDate(selectedDate));
        const minDate = useMemo(() => parseDate(typeof min === 'string' ? min : undefined), [min]);
        const maxDate = useMemo(() => parseDate(typeof max === 'string' ? max : undefined), [max]);
        const [isOpen, setIsOpen] = useState(false);
        const [popoverPosition, setPopoverPosition] = useState<{ left: number; top: number } | null>(null);
        const wrapperRef = useRef<HTMLDivElement>(null);
        const triggerRef = useRef<HTMLInputElement>(null);
        const popoverRef = useRef<HTMLDivElement>(null);
        const generatedId = React.useId();
        const popoverId = `${id ?? generatedId}-calendar`;

        useEffect(() => {
            setDisplayValue(displayDate(selectedDate));
        }, [rawValue]);

        useLayoutEffect(() => {
            if (!isOpen) return;
            const positionPopover = () => {
                const rect = triggerRef.current?.getBoundingClientRect();
                if (!rect) return;
                const width = Math.min(360, window.innerWidth - 24);
                const estimatedHeight = Math.min(390, window.innerHeight - 24);
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
                document.removeEventListener('pointerdown', onPointerDown);
                document.removeEventListener('keydown', onKeyDown);
                window.removeEventListener('resize', positionPopover);
                window.removeEventListener('scroll', positionPopover, true);
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

        const emitDateChange = (date: Date) => {
            const nextValue = dateValue(date);
            if ((minDate && nextValue < dateValue(minDate)) || (maxDate && nextValue > dateValue(maxDate))) {
                triggerRef.current?.setCustomValidity('La date choisie est en dehors de la période autorisée.');
                return;
            }
            if (value === undefined) setUncontrolledValue(nextValue);
            setDisplayValue(displayDate(date));
            triggerRef.current?.setCustomValidity('');
            if (onChange) {
                const target = { name: name ?? '', value: nextValue } as HTMLInputElement;
                onChange({ target, currentTarget: target } as React.ChangeEvent<HTMLInputElement>);
            }
            setIsOpen(false);
        };

        const clearDate = () => {
            if (value === undefined) setUncontrolledValue('');
            setDisplayValue('');
            triggerRef.current?.setCustomValidity('');
            if (onChange) {
                const target = { name: name ?? '', value: '' } as HTMLInputElement;
                onChange({ target, currentTarget: target } as React.ChangeEvent<HTMLInputElement>);
            }
            setIsOpen(false);
        };

        return (
            <div ref={wrapperRef} className="datepicker-field-wrapper">
                <input
                    ref={(element) => {
                        triggerRef.current = element;
                        if (typeof ref === 'function') ref(element);
                        else if (ref) ref.current = element;
                    }}
                    id={id}
                    type="text"
                    className={`${classes} datepicker-field__trigger`}
                    disabled={disabled}
                    required={required}
                    placeholder={placeholder ?? 'Sélectionner une date'}
                    value={displayValue}
                    aria-label={ariaLabel ?? placeholder}
                    aria-describedby={ariaDescribedBy}
                    role="combobox"
                    aria-haspopup="dialog"
                    aria-expanded={isOpen}
                    aria-controls={popoverId}
                    aria-required={required || undefined}
                    aria-invalid={variant === 'error' ? true : inputProps['aria-invalid']}
                    autoComplete="off"
                    {...inputProps}
                    onChange={(event) => {
                        const typedDate = event.target.value;
                        setDisplayValue(typedDate);
                        const parsedDate = parseDisplayedDate(typedDate);
                        if (parsedDate) {
                            triggerRef.current?.setCustomValidity('');
                            emitDateChange(parsedDate);
                        } else if (typedDate) {
                            triggerRef.current?.setCustomValidity('Saisissez une date valide au format JJ/MM/AAAA.');
                        } else {
                            triggerRef.current?.setCustomValidity('');
                            if (!required) clearDate();
                        }
                    }}
                    onClick={() => setIsOpen((open) => !open)}
                    onKeyDown={(event) => {
                        inputProps.onKeyDown?.(event);
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            setIsOpen((open) => !open);
                        }
                    }}
                />
                {icon && <span className="datepicker-field__icon" aria-hidden="true">{icon}</span>}
                {selectedDate && !required && (
                    <button type="button" className="datepicker-field__clear" aria-label="Effacer la date" title="Effacer la date" onClick={clearDate}>
                        <svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" /></svg>
                    </button>
                )}
                <input type="hidden" name={name} value={rawValue} />
                {isOpen && popoverPosition && createPortal(
                    <div ref={popoverRef} id={popoverId} className="datepicker-calendar-popover" style={popoverPosition} role="dialog" aria-label="Choisir une date">
                        <Calendar
                            key={`${selectedDate?.getFullYear() ?? 'today'}-${selectedDate?.getMonth() ?? 'today'}`}
                            selectedDate={selectedDate}
                            initialMonth={selectedDate ?? undefined}
                            minDate={minDate ?? undefined}
                            maxDate={maxDate ?? undefined}
                            onDateSelect={emitDateChange}
                            weekdayFormat="compact"
                            className="calendar--datepicker"
                        />
                    </div>,
                    document.body
                )}
            </div>
        );
    }
);

DatePicker.displayName = 'DatePicker';
