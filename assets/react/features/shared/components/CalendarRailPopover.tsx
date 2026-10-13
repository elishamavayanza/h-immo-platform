import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { useFloatingPosition } from '../../../hook-components/UI/PopoverMenu';

export function CalendarRailPopover({ title, label, children }: { title: string; label: string; children: ReactNode }) {
    const [isOpen, setOpen] = useState(false);
    const anchorRef = useRef<HTMLButtonElement>(null);
    const getAnchorEl = useCallback(() => anchorRef.current, []);
    const { floatingRef, coords, isPositioned } = useFloatingPosition<HTMLDivElement>({ getAnchorEl, placement: 'left', offset: 8, isOpen });

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
        <button ref={anchorRef} type="button" className={`financial-calendar-rail__button${isOpen ? ' is-active' : ''}`} aria-label={label} aria-haspopup="dialog" aria-expanded={isOpen} title={label} onClick={() => setOpen((open) => !open)}>
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 17h.01M12 17h.01"/></svg>
        </button>
        {isOpen && typeof document !== 'undefined' && createPortal(
            <div ref={floatingRef} className="calendar-rail-popover" role="dialog" aria-label={label} style={{ position: 'fixed', top: coords?.top ?? 0, left: coords?.left ?? 0, visibility: isPositioned ? 'visible' : 'hidden' }}>
                <div className="calendar-rail-popover__title">{title}</div>
                <div className="calendar-rail-popover__content">{children}</div>
            </div>,
            document.body,
        )}
    </>;
}
