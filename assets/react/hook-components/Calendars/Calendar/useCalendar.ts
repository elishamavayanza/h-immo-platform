import { useState, useMemo, useCallback } from 'react';

export type CalendarMarkedType = 'info' | 'success' | 'warning' | 'error';

export interface CalendarMarkedDate {
    date: Date;
    type?: CalendarMarkedType;
}

export interface UseCalendarProps {
    selectedDate?: Date | null;
    onDateSelect?: (date: Date) => void;
    markedDates?: CalendarMarkedDate[];
    rangeStart?: Date | null;
    rangeEnd?: Date | null;
    minDate?: Date;
    maxDate?: Date;
    initialMonth?: Date;
    onMonthChange?: (month: number, year: number) => void;
    className?: string;
}

export interface CalendarDay {
    date: Date;
    isCurrentMonth: boolean;
    isToday: boolean;
    isSelected: boolean;
    isDisabled: boolean;
    isInRange: boolean;
    isRangeStart: boolean;
    isRangeEnd: boolean;
    markers: CalendarMarkedType[];
}

function isSameDay(d1: Date, d2: Date): boolean {
    return (
        d1.getFullYear() === d2.getFullYear() &&
        d1.getMonth() === d2.getMonth() &&
        d1.getDate() === d2.getDate()
    );
}

function isSameMonth(d1: Date, d2: Date): boolean {
    return d1.getFullYear() === d2.getFullYear() && d1.getMonth() === d2.getMonth();
}

export function useCalendar({
                                selectedDate = null,
                                onDateSelect,
                                markedDates = [],
                                rangeStart = null,
                                rangeEnd = null,
                                minDate,
                                maxDate,
                                initialMonth,
                                onMonthChange,
                                className = '',
                            }: UseCalendarProps) {
    const today = useMemo(() => {
        const now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), now.getDate());
    }, []);

    const [currentMonth, setCurrentMonth] = useState<Date>(() => {
        const base = initialMonth || selectedDate || rangeStart || today;
        return new Date(base.getFullYear(), base.getMonth(), 1);
    });

    // Fonction utilitaire pour vérifier si une date est désactivée
    const isDateDisabled = useCallback(
        (date: Date): boolean => {
            const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());

            if (minDate) {
                const min = new Date(minDate.getFullYear(), minDate.getMonth(), minDate.getDate());
                if (dateOnly < min) return true;
            }
            if (maxDate) {
                const max = new Date(maxDate.getFullYear(), maxDate.getMonth(), maxDate.getDate());
                if (dateOnly > max) return true;
            }
            return false;
        },
        [minDate, maxDate]
    );

    const normalizedRange = useMemo(() => {
        if (!rangeStart && !rangeEnd) {
            return { start: null as Date | null, end: null as Date | null };
        }

        const start = rangeStart
            ? new Date(rangeStart.getFullYear(), rangeStart.getMonth(), rangeStart.getDate())
            : null;
        const end = rangeEnd
            ? new Date(rangeEnd.getFullYear(), rangeEnd.getMonth(), rangeEnd.getDate())
            : null;

        if (start && end && start > end) {
            return { start: end, end: start };
        }

        return { start, end };
    }, [rangeStart, rangeEnd]);

    const days = useMemo(() => {
        const year = currentMonth.getFullYear();
        const month = currentMonth.getMonth();

        const firstDayOfMonth = new Date(year, month, 1);
        const startWeekday = firstDayOfMonth.getDay(); // 0 dimanche
        const mondayOffset = startWeekday === 0 ? -6 : 1 - startWeekday;
        const gridStart = new Date(year, month, 1 + mondayOffset);

        const daysList: CalendarDay[] = [];

        for (let i = 0; i < 42; i++) {
            const date = new Date(gridStart);
            date.setDate(gridStart.getDate() + i);
            const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());

            const isCurrentMonth = isSameMonth(date, currentMonth);
            const isToday = isSameDay(date, today);
            const isSelected = selectedDate ? isSameDay(date, selectedDate) : false;
            const isDisabled = isDateDisabled(date);
            const isRangeStart = normalizedRange.start ? isSameDay(date, normalizedRange.start) : false;
            const isRangeEnd = normalizedRange.end ? isSameDay(date, normalizedRange.end) : false;
            const isInRange = Boolean(
                normalizedRange.start &&
                normalizedRange.end &&
                dateOnly >= normalizedRange.start &&
                dateOnly <= normalizedRange.end
            );

            const markers = markedDates
                .filter((m) => isSameDay(m.date, date))
                .map((m) => m.type || 'info');

            daysList.push({
                date,
                isCurrentMonth,
                isToday,
                isSelected,
                isDisabled,
                isInRange,
                isRangeStart,
                isRangeEnd,
                markers,
            });
        }

        return daysList;
    }, [currentMonth, selectedDate, markedDates, normalizedRange, today, isDateDisabled]);

    const goToPreviousMonth = useCallback(() => {
        setCurrentMonth((prev) => {
            const newMonth = new Date(prev);
            newMonth.setMonth(newMonth.getMonth() - 1);
            onMonthChange?.(newMonth.getMonth(), newMonth.getFullYear());
            return newMonth;
        });
    }, [onMonthChange]);

    const goToNextMonth = useCallback(() => {
        setCurrentMonth((prev) => {
            const newMonth = new Date(prev);
            newMonth.setMonth(newMonth.getMonth() + 1);
            onMonthChange?.(newMonth.getMonth(), newMonth.getFullYear());
            return newMonth;
        });
    }, [onMonthChange]);

    const goToToday = useCallback(() => {
        setCurrentMonth(new Date(today.getFullYear(), today.getMonth(), 1));
        onMonthChange?.(today.getMonth(), today.getFullYear());
    }, [today, onMonthChange]);

    const selectDate = useCallback(
        (date: Date) => {
            if (onDateSelect && !isDateDisabled(date)) {
                onDateSelect(date);
            }
        },
        [onDateSelect, isDateDisabled]
    );

    const classes = useMemo(() => {
        return ['calendar', className].filter(Boolean).join(' ');
    }, [className]);

    return {
        classes,
        currentMonth,
        days,
        goToPreviousMonth,
        goToNextMonth,
        goToToday,
        selectDate,
        selectedDate,
    };
}
