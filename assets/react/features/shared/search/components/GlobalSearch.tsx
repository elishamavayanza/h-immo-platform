import { useEffect, useId, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';

import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { searchOrganization } from '../services/globalSearchService';
import type { GlobalSearchResult } from '../types/globalSearch.types';
import '../../../../../styles/components/Navigation/_globalSearch.scss';

const MIN_QUERY_LENGTH = 2;
const RESULT_TYPES: GlobalSearchResult['type'][] = ['Ville', 'Parcelle', 'Bâtiment', 'Unité', 'Locataire'];

export function GlobalSearch() {
    const { currentOrganization } = useOrganization();
    const navigate = useNavigate();
    const inputId = useId();
    const listId = `${inputId}-results`;
    const wrapperRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<GlobalSearchResult[]>([]);
    const [isLoading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [partial, setPartial] = useState(false);
    const [isOpen, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const term = query.trim();
    const canSearch = term.length >= MIN_QUERY_LENGTH;
    const activeResultId = activeIndex >= 0 ? `${listId}-option-${activeIndex}` : undefined;

    useEffect(() => {
        if (!canSearch || !currentOrganization?.uuid) {
            setResults([]);
            setLoading(false);
            setError(null);
            setPartial(false);
            setActiveIndex(-1);
            return;
        }

        const controller = new AbortController();
        setLoading(true);
        setError(null);
        setPartial(false);
        setActiveIndex(-1);
        const timer = window.setTimeout(() => {
            void searchOrganization(currentOrganization.uuid, term, controller.signal)
                .then((response) => {
                    if (controller.signal.aborted) return;
                    setResults(response.items);
                    setPartial(response.partial);
                })
                .catch((cause: unknown) => {
                    if (controller.signal.aborted) return;
                    setResults([]);
                    setError(cause instanceof ApiError ? cause.message : 'La recherche est momentanément indisponible.');
                })
                .finally(() => {
                    if (!controller.signal.aborted) setLoading(false);
                });
        }, 300);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [canSearch, currentOrganization?.uuid, term]);

    useEffect(() => {
        const closeOnOutsidePointer = (event: PointerEvent) => {
            if (event.target instanceof Node && !wrapperRef.current?.contains(event.target)) setOpen(false);
        };
        const focusSearchShortcut = (event: KeyboardEvent) => {
            const target = event.target;
            const isTyping = target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
            if (!isTyping && (event.metaKey || event.ctrlKey) && event.key.toLocaleLowerCase() === 'k') {
                event.preventDefault();
                inputRef.current?.focus();
                setOpen(true);
            }
        };
        document.addEventListener('pointerdown', closeOnOutsidePointer);
        document.addEventListener('keydown', focusSearchShortcut);
        return () => {
            document.removeEventListener('pointerdown', closeOnOutsidePointer);
            document.removeEventListener('keydown', focusSearchShortcut);
        };
    }, []);

    const openResult = (item: GlobalSearchResult) => {
        setOpen(false);
        setQuery('');
        setActiveIndex(-1);
        inputRef.current?.blur();
        navigate(item.path);
    };

    if (!currentOrganization) return null;

    const moveSelection = (direction: 1 | -1) => {
        if (results.length === 0) return;
        setActiveIndex((current) => current < 0
            ? (direction === 1 ? 0 : results.length - 1)
            : (current + direction + results.length) % results.length);
    };

    return (
        <div className="global-search" ref={wrapperRef}>
            <div className="global-search__field">
                <label className="global-search__label" htmlFor={inputId}>Recherche dans {currentOrganization.name}</label>
                <span className="global-search__icon" aria-hidden="true">⌕</span>
                <input
                    ref={inputRef}
                    id={inputId}
                    type="search"
                    value={query}
                    placeholder="Rechercher une ville, parcelle, bâtiment, unité ou locataire…"
                    role="combobox"
                    aria-label={`Recherche globale dans ${currentOrganization.name}`}
                    aria-haspopup="listbox"
                    aria-expanded={isOpen && canSearch}
                    aria-controls={listId}
                    aria-activedescendant={isOpen ? activeResultId : undefined}
                    aria-autocomplete="list"
                    autoComplete="off"
                    onFocus={() => setOpen(true)}
                    onChange={(event) => { setQuery(event.target.value); setOpen(true); }}
                    onKeyDown={(event) => {
                        if (event.key === 'Escape') {
                            setOpen(false);
                            setActiveIndex(-1);
                        } else if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            setOpen(true);
                            moveSelection(1);
                        } else if (event.key === 'ArrowUp') {
                            event.preventDefault();
                            moveSelection(-1);
                        } else if (event.key === 'Enter' && isOpen && results[activeIndex >= 0 ? activeIndex : 0]) {
                            event.preventDefault();
                            openResult(results[activeIndex >= 0 ? activeIndex : 0]);
                        }
                    }}
                />
                {isLoading && <span className="global-search__loading" aria-label="Recherche en cours" />}
                {!isLoading && query && <button type="button" className="global-search__clear" aria-label="Effacer la recherche" onClick={() => { setQuery(''); setOpen(true); inputRef.current?.focus(); }}>×</button>}
                <kbd className="global-search__shortcut" aria-hidden="true">Ctrl/⌘ K</kbd>
            </div>
            {isOpen && !canSearch && term.length > 0 && <div className="global-search__results" role="status"><p className="global-search__message">Saisissez au moins 2 caractères pour lancer la recherche.</p></div>}
            {isOpen && canSearch && (
                <div className="global-search__results" id={listId} role="listbox" aria-label="Résultats de la recherche">
                    <p className="global-search__context">Résultats dans {currentOrganization.name}</p>
                    {isLoading && <p className="global-search__message" role="status">Recherche en cours…</p>}
                    {!isLoading && error && <p className="global-search__message global-search__message--error" role="alert">{error}</p>}
                    {!isLoading && !error && partial && <p className="global-search__message global-search__message--notice" role="status">Certains types de résultats sont temporairement indisponibles.</p>}
                    {!isLoading && !error && results.length === 0 && <p className="global-search__message">Aucun résultat pour « {term} ».</p>}
                    {!isLoading && !error && RESULT_TYPES.map((type) => {
                        const items = results.filter((item) => item.type === type);
                        if (items.length === 0) return null;
                        return (
                            <div className="global-search__group" key={type} role="group" aria-label={type === 'Unité' ? 'Unités' : `${type}s`}>
                                <p className="global-search__group-title">{type === 'Unité' ? 'Unités' : `${type}s`}</p>
                                {items.map((item) => {
                                    const index = results.indexOf(item);
                                    return (
                                        <button
                                            type="button"
                                            role="option"
                                            id={`${listId}-option-${index}`}
                                            aria-selected={activeIndex === index}
                                            className={`global-search__result${activeIndex === index ? ' is-active' : ''}`}
                                            key={`${item.type}:${item.id}`}
                                            onMouseEnter={() => setActiveIndex(index)}
                                            onClick={() => openResult(item)}
                                        >
                                            <span className="global-search__result-copy"><strong>{item.label}</strong><small>{item.detail || item.type}</small></span>
                                            <span className="global-search__result-type" aria-hidden="true">{item.type}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        );
                    })}
                    {!isLoading && !error && results.length > 0 && <p className="global-search__hint">↑ ↓ pour choisir · Entrée pour ouvrir · Échap pour fermer</p>}
                </div>
            )}
        </div>
    );
}
