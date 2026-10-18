import React, { forwardRef, useEffect, useState } from 'react';
import { DatePicker } from '../DatePicker';
import { TimePicker } from '../../Calendars/TimePicker';

export interface DateTimePickerProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type' | 'onChange'> {
    fullWidth?: boolean;
    className?: string;
    onChange?: React.ChangeEventHandler<HTMLInputElement>;
}

function emit(onChange: DateTimePickerProps['onChange'], name: string | undefined, value: string) {
    if (!onChange) return;
    const target = { name: name ?? '', value } as HTMLInputElement;
    onChange({ target, currentTarget: target } as React.ChangeEvent<HTMLInputElement>);
}

export const DateTimePicker = forwardRef<HTMLInputElement, DateTimePickerProps>(
    ({ value = '', defaultValue, onChange, min, max, disabled, required, id, name, fullWidth = false, className, ...props }, ref) => {
        const currentValue = typeof value === 'string' ? value : '';
        const initial = currentValue || (typeof defaultValue === 'string' ? defaultValue : '');
        const [date, setDate] = useState(initial.slice(0, 10));
        const [time, setTime] = useState(initial.slice(11, 16));

        useEffect(() => {
            if (currentValue) {
                setDate(currentValue.slice(0, 10));
                setTime(currentValue.slice(11, 16));
            }
        }, [currentValue]);

        const updateDate = (nextDate: string) => {
            setDate(nextDate);
            if (!nextDate) {
                setTime('');
                emit(onChange, name, '');
            } else if (time) {
                let nextTime = time;
                if (typeof min === 'string' && min.startsWith(`${nextDate}T`) && nextTime < min.slice(11, 16)) nextTime = min.slice(11, 16);
                if (typeof max === 'string' && max.startsWith(`${nextDate}T`) && nextTime > max.slice(11, 16)) nextTime = max.slice(11, 16);
                setTime(nextTime);
                emit(onChange, name, `${nextDate}T${nextTime}`);
            }
        };
        const updateTime = (nextTime: string) => {
            setTime(nextTime);
            if (date && nextTime) emit(onChange, name, `${date}T${nextTime}`);
        };

        return (
            <div className={`datetime-picker${fullWidth ? ' datetime-picker--full-width' : ''}${className ? ` ${className}` : ''}`}>
                <DatePicker
                    id={id}
                    value={date}
                    onChange={(event) => updateDate(event.target.value)}
                    min={typeof min === 'string' ? min.slice(0, 10) : undefined}
                    max={typeof max === 'string' ? max.slice(0, 10) : undefined}
                    disabled={disabled}
                    required={required}
                    aria-label={props['aria-label'] ? `${props['aria-label']} — date` : undefined}
                    fullWidth
                    className="datetime-picker__date"
                />
                <TimePicker
                    value={time}
                    onChange={updateTime}
                    minTime={typeof min === 'string' && min.slice(0, 10) === date ? min.slice(11, 16) : undefined}
                    maxTime={typeof max === 'string' && max.slice(0, 10) === date ? max.slice(11, 16) : undefined}
                    disabled={disabled}
                    fullWidth
                    className="datetime-picker__time"
                    inputProps={{
                        id: id ? `${id}-time` : undefined,
                        name: undefined,
                        required,
                        'aria-label': props['aria-label'] ? `${props['aria-label']} — heure` : 'Heure',
                        ...props,
                    }}
                />
                <input ref={ref} type="hidden" name={name} value={date && time ? `${date}T${time}` : ''} />
            </div>
        );
    }
);

DateTimePicker.displayName = 'DateTimePicker';
