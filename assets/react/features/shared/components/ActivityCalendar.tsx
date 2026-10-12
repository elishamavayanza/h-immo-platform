import { useMemo } from 'react';

import { Calendar } from '../../../components/Calendars/Calendar';
import type { CalendarMarkedDate } from '../../../hook-components/Calendars/Calendar';
import { formatUserDate } from '../../../services/userPreferences';

export type ActivityTone = 'info' | 'success' | 'warning' | 'error';

export interface ActivityCalendarEvent {
    date: string;
    title: string;
    detail?: string;
    tone?: ActivityTone;
}

/** Clé de jour locale stable pour une date seule ou un horodatage API. */
export function activityDateKey(value: string): string | null {
    const dateOnly = /^(\d{4}-\d{2}-\d{2})$/.exec(value)?.[1];
    if (dateOnly) return dateOnly;

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
}

export function calendarDateFromKey(key: string): Date | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(key);
    if (!match) return null;
    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    return Number.isNaN(date.getTime()) ? null : date;
}

export interface ActivityCalendarProps {
    events: ActivityCalendarEvent[];
    selectedDate: string | null;
    onSelectDate: (date: string) => void;
    onClearDate: () => void;
    description: string;
    label?: string;
    onMonthChange?: (month: number, year: number) => void;
    emptyMessage?: string;
}

export function ActivityCalendar({
    events,
    selectedDate,
    onSelectDate,
    onClearDate,
    description,
    label = 'Calendrier des activités',
    onMonthChange,
    emptyMessage = 'Aucune activité connue pour cette journée.',
}: ActivityCalendarProps) {
    const markedDates = useMemo<CalendarMarkedDate[]>(() => {
        const unique = new Map<string, CalendarMarkedDate>();
        for (const event of events) {
            const key = activityDateKey(event.date);
            const date = key ? calendarDateFromKey(key) : null;
            if (!key || !date) continue;
            const marker = { date, type: event.tone ?? 'info' } satisfies CalendarMarkedDate;
            unique.set(`${key}:${marker.type}`, marker);
        }
        return [...unique.values()];
    }, [events]);

    const selectedEvents = useMemo(
        () => selectedDate ? events.filter((event) => activityDateKey(event.date) === selectedDate) : [],
        [events, selectedDate],
    );
    const selectedDateObject = selectedDate ? calendarDateFromKey(selectedDate) : null;

    return (
        <section className="activity-calendar" aria-label={label}>
            <p className="activity-calendar__description">{description}</p>
            <Calendar
                selectedDate={selectedDateObject}
                markedDates={markedDates}
                onDateSelect={(date) => onSelectDate([date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-'))}
                onMonthChange={onMonthChange}
                className="activity-calendar__calendar"
            />
            <div className="activity-calendar__results" aria-live="polite">
                <div className="activity-calendar__results-heading">
                    <div>
                        <strong>{selectedDate ? formatUserDate(`${selectedDate}T12:00:00`) : 'Choisir une journée'}</strong>
                        {selectedDate && <span>{selectedEvents.length} activité{selectedEvents.length > 1 ? 's' : ''}</span>}
                    </div>
                    {selectedDate && <button type="button" onClick={onClearDate}>Effacer</button>}
                </div>
                {selectedDate && (selectedEvents.length > 0 ? (
                    <ul className="activity-calendar__event-list">
                        {selectedEvents.slice(0, 8).map((event, index) => (
                            <li key={`${event.title}:${event.date}:${index}`}>
                                <span className={`activity-calendar__event-dot activity-calendar__event-dot--${event.tone ?? 'info'}`} aria-hidden="true" />
                                <span><strong>{event.title}</strong>{event.detail && <small>{event.detail}</small>}</span>
                            </li>
                        ))}
                        {selectedEvents.length > 8 && <li className="activity-calendar__more">+ {selectedEvents.length - 8} autres activités</li>}
                    </ul>
                ) : <p className="activity-calendar__empty">{emptyMessage}</p>)}
            </div>
        </section>
    );
}
