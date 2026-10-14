import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';

import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { searchOrganization } from '../services/globalSearchService';
import type { GlobalSearchResult } from '../types/globalSearch.types';
import '../../../../../styles/components/Navigation/_globalSearch.scss';

export function GlobalSearch() {
    const { currentOrganization } = useOrganization();
    const navigate = useNavigate();
    const wrapperRef = useRef<HTMLDivElement>(null);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<GlobalSearchResult[]>([]);
    const [isLoading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [isOpen, setOpen] = useState(false);

    useEffect(() => {
        const term = query.trim();
        if (term.length < 2 || !currentOrganization?.uuid) {
            setResults([]);
            setLoading(false);
            setError(null);
            return;
        }
        let current = true;
        const timer = window.setTimeout(() => {
            setLoading(true);
            setError(null);
            void searchOrganization(currentOrganization.uuid, term)
                .then((items) => { if (current) setResults(items.slice(0, 15)); })
                .catch((cause: unknown) => {
                    if (!current) return;
                    setResults([]);
                    setError(cause instanceof ApiError ? cause.message : 'La recherche est momentanément indisponible.');
                })
                .finally(() => { if (current) setLoading(false); });
        }, 250);
        return () => { current = false; window.clearTimeout(timer); };
    }, [currentOrganization?.uuid, query]);

    useEffect(() => {
        const closeOnOutsideClick = (event: MouseEvent) => {
            if (event.target instanceof Node && !wrapperRef.current?.contains(event.target)) setOpen(false);
        };
        document.addEventListener('mousedown', closeOnOutsideClick);
        return () => document.removeEventListener('mousedown', closeOnOutsideClick);
    }, []);

    const openResult = (item: GlobalSearchResult) => {
        setOpen(false);
        setQuery('');
        navigate(item.path);
    };

    if (!currentOrganization) return null;

    return (
        <div className="global-search" ref={wrapperRef}>
            <label className="global-search__field">
                <span aria-hidden="true">⌕</span>
                <input
                    type="search"
                    value={query}
                    placeholder="Rechercher une ville, parcelle, bâtiment, unité ou locataire…"
                    aria-label="Recherche globale dans mon organisation"
                    aria-expanded={isOpen && query.trim().length >= 2}
                    aria-controls="global-search-results"
                    aria-autocomplete="list"
                    onFocus={() => setOpen(true)}
                    onChange={(event) => { setQuery(event.target.value); setOpen(true); }}
                    onKeyDown={(event) => {
                        if (event.key === 'Escape') setOpen(false);
                        if (event.key === 'Enter' && isOpen && results[0]) openResult(results[0]);
                    }}
                />
                {isLoading && <span className="global-search__loading" aria-label="Recherche en cours" />}
            </label>
            {isOpen && query.trim().length >= 2 && (
                <div className="global-search__results" id="global-search-results" role="listbox" aria-label="Résultats de la recherche">
                    {isLoading && <p className="global-search__message" role="status">Recherche en cours…</p>}
                    {!isLoading && error && <p className="global-search__message global-search__message--error" role="alert">{error}</p>}
                    {!isLoading && !error && results.length === 0 && <p className="global-search__message">Aucun résultat pour « {query.trim()} ».</p>}
                    {!isLoading && !error && results.map((item) => (
                        <button type="button" role="option" aria-selected="false" className="global-search__result" key={`${item.type}:${item.id}`} onClick={() => openResult(item)}>
                            <span className="global-search__result-type">{item.type}</span>
                            <span className="global-search__result-copy"><strong>{item.label}</strong><small>{item.detail || ' '}</small></span>
                            <span aria-hidden="true">↗</span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
