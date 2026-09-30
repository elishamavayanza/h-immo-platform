import React from 'react';
import { useDateRangePicker, UseDateRangePickerProps } from '../../../hook-components/Forms/DateRangePicker';

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
                <input
                    type="date"
                    className="daterange__input"
                    value={start}
                    onChange={(e) => updateStartDate(e.target.value)}
                    disabled={disabled}
                />
            </div>
            <span className="daterange__separator">→</span>
            <div className="daterange__field">
                <label className="daterange__label">{effectiveLabelEnd}</label>
                <input
                    type="date"
                    className="daterange__input"
                    value={end}
                    onChange={(e) => updateEndDate(e.target.value)}
                    disabled={disabled}
                />
            </div>
        </div>
    );
}
