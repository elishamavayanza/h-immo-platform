import { useCallback, useEffect, useId, useRef, useState, type PointerEvent as ReactPointerEvent, type KeyboardEvent as ReactKeyboardEvent } from 'react';
import { createPortal } from 'react-dom';
import { useLocation } from 'react-router-dom';
import { useAuth } from '../../../../app/providers/AuthProvider';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { filterHelpQuestions, resolveContextualHelp } from '../services/contextualHelpRegistry';
import { helpButtonPositionToPixels, normalizeHelpButtonPosition, parseHelpButtonPosition, type HelpButtonPosition } from '../utils/helpButtonPosition';
import './ContextualHelp.scss';

const HELP_POSITION_KEY = 'h-immo:contextual-help-position';

function HelpMark() {
    return <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" /><path d="M9.7 9a2.4 2.4 0 1 1 4.2 1.6c-1.1 1.1-1.9 1.4-1.9 3" /><path d="M12 17.2h.01" /></svg>;
}

export function ContextualHelp() {
    const { pathname } = useLocation();
    const { platformRole, organizationRole } = useOrganization();
    const { user } = useAuth();
    const help = resolveContextualHelp(pathname, platformRole, organizationRole);
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [expanded, setExpanded] = useState<string | null>(null);
    const id = useId();
    const triggerRef = useRef<HTMLButtonElement>(null);
    const searchRef = useRef<HTMLInputElement>(null);
    const panelRef = useRef<HTMLElement>(null);
    const dragRef = useRef<{ pointerId: number; startX: number; startY: number; originX: number; originY: number; moved: boolean } | null>(null);
    const positionRef = useRef<HelpButtonPosition | null>(null);
    const suppressClickRef = useRef(false);
    const [position, setPosition] = useState<HelpButtonPosition | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const storageKey = `${HELP_POSITION_KEY}:${user?.uuid ?? 'guest'}`;
    const filteredQuestions = help ? filterHelpQuestions(help.questions, search) : [];

    const storePosition = useCallback((next: HelpButtonPosition) => {
        positionRef.current = next;
        setPosition(next);
        try { window.localStorage.setItem(storageKey, JSON.stringify(next)); } catch { /* la position reste active pour cette visite */ }
    }, [storageKey]);

    useEffect(() => {
        let saved: HelpButtonPosition | null = null;
        try { saved = parseHelpButtonPosition(window.localStorage.getItem(storageKey)); } catch { /* stockage désactivé */ }
        if (saved) {
            // Une ancienne position enregistrée au bord de l'écran doit être corrigée
            // avant de l'afficher, pour éviter que le bouton apparaisse partiellement coupé.
            saved = normalizeHelpButtonPosition(
                saved.x * window.innerWidth,
                saved.y * window.innerHeight,
                window.innerWidth,
                window.innerHeight,
            );
        }
        positionRef.current = saved;
        setPosition(saved);
    }, [storageKey]);

    useEffect(() => {
        if (!position) return;
        const reclamp = () => {
            const next = normalizeHelpButtonPosition(position.x * window.innerWidth, position.y * window.innerHeight, window.innerWidth, window.innerHeight);
            if (next.x !== positionRef.current?.x || next.y !== positionRef.current?.y) storePosition(next);
        };
        window.addEventListener('resize', reclamp);
        return () => window.removeEventListener('resize', reclamp);
    }, [position, storePosition]);

    const moveByKeyboard = (event: ReactKeyboardEvent<HTMLButtonElement>) => {
        const movement: Record<string, [number, number]> = {
            ArrowUp: [0, -16], ArrowDown: [0, 16], ArrowLeft: [-16, 0], ArrowRight: [16, 0],
        };
        const delta = movement[event.key];
        if (!delta) return;
        event.preventDefault();
        const rect = triggerRef.current?.getBoundingClientRect();
        const current = position
            ? helpButtonPositionToPixels(position, window.innerWidth, window.innerHeight)
            : { x: (rect?.left ?? window.innerWidth - 36) + (rect?.width ?? 48) / 2, y: (rect?.top ?? window.innerHeight - 36) + (rect?.height ?? 48) / 2 };
        storePosition(normalizeHelpButtonPosition(current.x + delta[0], current.y + delta[1], window.innerWidth, window.innerHeight));
    };

    const beginDrag = (event: ReactPointerEvent<HTMLButtonElement>) => {
        if (event.button !== 0) return;
        const rect = event.currentTarget.getBoundingClientRect();
        dragRef.current = { pointerId: event.pointerId, startX: event.clientX, startY: event.clientY, originX: rect.left + rect.width / 2, originY: rect.top + rect.height / 2, moved: false };
        event.currentTarget.setPointerCapture(event.pointerId);
    };

    const moveDrag = (event: ReactPointerEvent<HTMLButtonElement>) => {
        const drag = dragRef.current;
        if (!drag || drag.pointerId !== event.pointerId) return;
        const dx = event.clientX - drag.startX;
        const dy = event.clientY - drag.startY;
        if (!drag.moved && Math.hypot(dx, dy) < 6) return;
        drag.moved = true;
        setIsDragging(true);
        const next = normalizeHelpButtonPosition(drag.originX + dx, drag.originY + dy, window.innerWidth, window.innerHeight);
        positionRef.current = next;
        setPosition(next);
        event.preventDefault();
    };

    const endDrag = (event: ReactPointerEvent<HTMLButtonElement>) => {
        const drag = dragRef.current;
        if (!drag || drag.pointerId !== event.pointerId) return;
        if (drag.moved) {
            suppressClickRef.current = true;
            if (positionRef.current) storePosition(positionRef.current);
        }
        dragRef.current = null;
        setIsDragging(false);
    };

    useEffect(() => {
        if (!open) return;
        const priorOverflow = document.body.style.overflow;
        searchRef.current?.focus();
        document.body.style.overflow = 'hidden';
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                setOpen(false);
            }
            if (event.key !== 'Tab') return;
            const focusable = panelRef.current?.querySelectorAll<HTMLElement>('button:not([disabled]), input:not([disabled]), [href], [tabindex]:not([tabindex="-1"])');
            if (!focusable?.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        };
        document.addEventListener('keydown', onKeyDown);
        return () => {
            document.body.style.overflow = priorOverflow;
            document.removeEventListener('keydown', onKeyDown);
            triggerRef.current?.focus();
        };
    }, [open]);

    useEffect(() => {
        setOpen(false);
        setSearch('');
        setExpanded(null);
    }, [pathname, platformRole, organizationRole]);

    if (!help) return null;

    const trigger = <button
            ref={triggerRef}
            type="button"
            className={`contextual-help__trigger${position ? ' contextual-help__trigger--positioned' : ''}${isDragging ? ' contextual-help__trigger--dragging' : ''}`}
            style={position ? { left: `${position.x * 100}%`, top: `${position.y * 100}%`, right: 'auto', bottom: 'auto' } : undefined}
            aria-label={`Aide de cette page : ${help.title}. Déplaçable par glissement ou avec les flèches.`}
            aria-haspopup="dialog"
            aria-expanded={open}
            aria-controls={`${id}-panel`}
            aria-keyshortcuts="ArrowUp ArrowDown ArrowLeft ArrowRight"
            title="Glissez pour déplacer · Flèches pour ajuster"
            onPointerDown={beginDrag}
            onPointerMove={moveDrag}
            onPointerUp={endDrag}
            onPointerCancel={endDrag}
            onKeyDown={moveByKeyboard}
            onClick={() => { if (suppressClickRef.current) { suppressClickRef.current = false; return; } setOpen(true); }}
        ><HelpMark /></button>;

    return <>
        {createPortal(trigger, document.body)}
        {open && createPortal(
            <div className="contextual-help__layer" onMouseDown={(event) => { if (event.target === event.currentTarget) setOpen(false); }}>
                <aside ref={panelRef} id={`${id}-panel`} className="contextual-help__panel" role="dialog" aria-modal="true" aria-labelledby={`${id}-title`} aria-describedby={`${id}-description`}>
                    <header className="contextual-help__header">
                        <div className="contextual-help__heading-icon"><HelpMark /></div>
                        <div className="contextual-help__heading-copy"><h2 id={`${id}-title`}>Aide de cette page</h2><p>{help.title}</p></div>
                        <button type="button" className="contextual-help__close" onClick={() => setOpen(false)} aria-label="Fermer l’aide">×</button>
                    </header>
                    <p id={`${id}-description`} className="contextual-help__description">{help.description}</p>
                    <label className="contextual-help__search-label" htmlFor={`${id}-search`}>Rechercher dans les questions</label>
                    <input ref={searchRef} id={`${id}-search`} className="contextual-help__search" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Ex. ajouter, filtre, accès…" />
                    {filteredQuestions.length ? <div className="contextual-help__questions">
                        {filteredQuestions.map((item) => {
                            const isExpanded = expanded === item.id;
                            return <section className="contextual-help__item" key={item.id}>
                                <h3><button type="button" aria-expanded={isExpanded} aria-controls={`${id}-${item.id}`} onClick={() => setExpanded(isExpanded ? null : item.id)}>{item.question}<span aria-hidden="true">{isExpanded ? '−' : '+'}</span></button></h3>
                                {isExpanded && <div id={`${id}-${item.id}`} className="contextual-help__answer">
                                    <p>{item.answer}</p>
                                    {item.guide && <div className="contextual-help__guide">
                                        <p className="contextual-help__guide-location"><strong>Où aller</strong><span>{item.guide.location}</span></p>
                                        <div className="contextual-help__guide-procedure"><strong>Comment procéder</strong><ol>{item.guide.steps.map((step, index) => <li key={`${item.id}-step-${index}`}>{step}</li>)}</ol></div>
                                        {item.guide.result && <p className="contextual-help__guide-result"><strong>À vérifier</strong><span>{item.guide.result}</span></p>}
                                    </div>}
                                </div>}
                            </section>;
                        })}
                    </div> : <div className="contextual-help__empty"><p>Aucune question ne correspond à « {search.trim()} ».</p><button type="button" onClick={() => setSearch('')}>Effacer la recherche</button></div>}
                    <footer className="contextual-help__footer">Les actions disponibles dépendent de votre rôle et des autorisations de votre espace.</footer>
                </aside>
            </div>, document.body
        )}
    </>;
}
