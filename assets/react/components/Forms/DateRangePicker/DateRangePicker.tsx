import React from 'react';
import { useDateRangePicker, UseDateRangePickerProps } from '../../../hook-components/Forms/DateRangePicker';
import { DatePicker } from '../DatePicker';

export interface DateRangePickerProps extends UseDateRangePickerProps {
    labelStart?: string;
    labelEnd?: string;
}

export function DateRangePicker({
                                    startDate,
                                    endDate,
                                    variant,
                                    fieldSize,
                                    fullWidth,
                                    disabled,
                                    onChange,
                                    className,
                                    labelStart,
                                    labelEnd,
                                }: DateRangePickerProps) {
    const effectiveLabelStart = labelStart ?? ('Date de début');
    const effectiveLabelEnd = labelEnd ?? ('Date de fin');
    const { classes, startDate: start, endDate: end, updateStartDate, updateEndDate } =
        useDateRangePicker({
            startDate,
            endDate,
            variant,
            fieldSize,
            fullWidth,
            disabled,
            onChange,
            className,
        });

    return (
        <div className={classes}>
            <div className="daterange__field">
                <label className="daterange__label">{effectiveLabelStart}</label>
                <DatePicker
                    value={start}
                    onChange={(event) => updateStartDate(event.target.value)}
                    disabled={disabled}
                    fullWidth
                    variant={variant}
                    fieldSize={fieldSize}
                    aria-label={effectiveLabelStart}
                />
            </div>
            <span className="daterange__separator">→</span>
            <div className="daterange__field">
                <label className="daterange__label">{effectiveLabelEnd}</label>
                <DatePicker
                    value={end}
                    onChange={(event) => updateEndDate(event.target.value)}
                    disabled={disabled}
                    fullWidth
                    variant={variant}
                    fieldSize={fieldSize}
                    aria-label={effectiveLabelEnd}
                />
            </div>
        </div>
    );
}
