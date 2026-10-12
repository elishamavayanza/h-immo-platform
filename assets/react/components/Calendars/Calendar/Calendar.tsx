import React from 'react';
import {useCalendar, UseCalendarProps} from "../../../hook-components/Calendars/Calendar";

const WEEKDAYS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
const COMPACT_WEEKDAYS = ['Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa', 'Di'];
const FULL_WEEKDAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
const MONTHS = [
    'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
];

export interface CalendarProps extends UseCalendarProps { weekdayFormat?: 'short' | 'compact' }

export function Calendar({
                             selectedDate,
                             onDateSelect,
                             markedDates,
                             rangeStart,
                             rangeEnd,
                             minDate,
                             maxDate,
                             initialMonth,
                             onMonthChange,
                             className,
                             weekdayFormat = 'short',
                         }: CalendarProps) {
    const {
        classes,
        currentMonth,
        days,
        goToPreviousMonth,
        goToNextMonth,
        goToToday,
        selectDate,
    } = useCalendar({
        selectedDate,
        onDateSelect,
        markedDates,
        rangeStart,
        rangeEnd,
        minDate,
        maxDate,
        initialMonth,
        onMonthChange,
        className,
    });

    return (
        <div className={classes}>
            <div className="calendar__header">
                <button
                    type="button"
                    className="calendar__nav"
                    onClick={goToPreviousMonth}
                    aria-label={('Mois précédent')}
                >
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                </button>
                <div className="calendar__title">
                    <span className="calendar__month">{(MONTHS[currentMonth.getMonth()])}</span>{' '}
                    <span className="calendar__year">{currentMonth.getFullYear()}</span>
                </div>
                <button
                    type="button"
                    className="calendar__nav"
                    onClick={goToNextMonth}
                    aria-label={('Mois suivant')}
                >
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </button>
                <button type="button" className="calendar__today" onClick={goToToday}>
                    {("Aujourd'hui")}
                </button>
            </div>

            <div className="calendar__weekdays">
                {WEEKDAYS.map((day, index) => (
                    <div key={day} className="calendar__weekday" aria-label={FULL_WEEKDAYS[index]} title={FULL_WEEKDAYS[index]}>
                        {weekdayFormat === 'compact' ? COMPACT_WEEKDAYS[index] : day}
                    </div>
                ))}
            </div>

            <div className="calendar__grid">
                {days.map((day, index) => (
                    <button
                        key={index}
                        type="button"
                        className={`calendar__day ${
                            !day.isCurrentMonth ? 'calendar__day--outside' : ''
                        } ${day.isToday ? 'calendar__day--today' : ''} ${
                            day.isSelected ? 'calendar__day--selected' : ''
                        } ${day.isDisabled ? 'calendar__day--disabled' : ''} ${
                            day.isInRange ? 'calendar__day--in-range' : ''
                        } ${day.isRangeStart ? 'calendar__day--range-start' : ''} ${
                            day.isRangeEnd ? 'calendar__day--range-end' : ''
                        } ${day.markers.length > 0 ? 'calendar__day--has-events' : ''}`}
                        onClick={() => !day.isDisabled && selectDate(day.date)}
                        aria-label={`${day.date.toLocaleDateString('fr-FR', { dateStyle: 'full' })}${day.markers.length > 0 ? `, ${day.markers.length} activité${day.markers.length > 1 ? 's' : ''}` : ''}`}
                        aria-pressed={day.isSelected}
                        disabled={day.isDisabled}
                    >
                        <span className="calendar__day-number">{day.date.getDate()}</span>
                        {day.markers.length > 0 && (
                            <span className="calendar__markers">
                                {day.markers.map((marker, idx) => (
                                    <span
                                        key={idx}
                                        className={`calendar__marker calendar__marker--${marker}`}
                                    />
                                ))}
                            </span>
                        )}
                    </button>
                ))}
            </div>
        </div>
    );
}
