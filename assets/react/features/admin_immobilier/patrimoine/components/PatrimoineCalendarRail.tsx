import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { ActivityCalendar, type ActivityCalendarEvent } from '../../../shared/components/ActivityCalendar';
import { useFloatingPosition } from '../../../../hook-components/UI/PopoverMenu';

export interface PatrimoineCalendarRailProps {
    events: ActivityCalendarEvent[];
    selectedDate: string | null;
    onSelectDate: (date: string) => void;
    onClearDate: () => void;
    onMonthChange: (month: number, year: number) => void;
    description: string;
}

export function PatrimoineCalendarRail({ events, selectedDate, onSelectDate, onClearDate, onMonthChange, description }: PatrimoineCalendarRailProps) {
    const [isOpen, setOpen] = useState(false);
    const anchorRef = useRef<HTMLButtonElement>(null);
    const getAnchorEl = useCallback(() => anchorRef.current, []);
    const { floatingRef, coords, isPositioned } = useFloatingPosition<HTMLDivElement>({
        getAnchorEl,
        placement: 'left',
        offset: 8,
        isOpen,
    });

    useEffect(() => {
        if (!isOpen) return;
        const onPointerDown = (event: MouseEvent) => {
            const target = event.target as Node;
            if (floatingRef.current?.contains(target) || anchorRef.current?.contains(target)) return;
            setOpen(false);
        };
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key !== 'Escape') return;
            setOpen(false);
            anchorRef.current?.focus();
        };
        document.addEventListener('mousedown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);
        return () => {
            document.removeEventListener('mousedown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [isOpen, floatingRef]);

    return <>
        <button
            ref={anchorRef}
            type="button"
            className={`patrimoine-nav-rail__button${selectedDate ? ' is-active' : ''}`}
            aria-label="Ouvrir le calendrier du patrimoine"
            aria-haspopup="true"
            aria-expanded={isOpen}
            title="Calendrier du patrimoine"
            onClick={() => setOpen((value) => !value)}
        >
            <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <rect x="3" y="5" width="18" height="16" rx="2" />
                <path d="M16 3v4M8 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 17h.01M12 17h.01" />
            </svg>
        </button>
        {isOpen && typeof document !== 'undefined' && createPortal(
            <div
                ref={floatingRef}
                className="patrimoine-nav__popover patrimoine-calendar-rail__popover"
                role="dialog"
                aria-label="Calendrier de l’activité du patrimoine"
                style={{ position: 'fixed', top: coords?.top ?? 0, left: coords?.left ?? 0, visibility: isPositioned ? 'visible' : 'hidden' }}
            >
                <div className="patrimoine-nav__popover-title">Activité du patrimoine</div>
                <div className="patrimoine-nav__popover-content">
                    <ActivityCalendar
                        events={events}
                        selectedDate={selectedDate}
                        onSelectDate={onSelectDate}
                        onClearDate={onClearDate}
                        onMonthChange={onMonthChange}
                        description={description}
                        emptyMessage="Aucune activité autorisée enregistrée pour cette journée."
                    />
                </div>
            </div>,
            document.body,
        )}
    </>;
}
