import { ApiError } from '../../services/api/api.types';
import { apiClient } from '../../services/api/client';
import { tokenStorage } from '../../services/storage/storage.service';
import { getRecentUserActions, initializeUserActionTrail } from '../../services/api/userActionTrail';

type ClientErrorKind = 'react_render' | 'window_error' | 'unhandled_rejection';

interface ClientErrorPayload {
    kind: ClientErrorKind;
    message: string;
    stack?: string;
    sourceLocation?: string;
    componentStack?: string;
    actionTrail?: string;
    route: string;
    occurredAt: string;
    userAgent: string;
}

const lastReports = new Map<string, number>();
let initialized = false;

function scrub(value: string, limit: number): string {
    return value
        .replace(/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/gi, '[email masqué]')
        .replace(/\bBearer\s+[^\s]+/gi, 'Bearer [jeton masqué]')
        .replace(/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/g, '[jeton masqué]')
        .replace(/\b(password|passwd|token|secret|authorization)\s*[:=]\s*[^\s,;]+/gi, '$1=[valeur masquée]')
        .replace(/\b\+?\d[\d ()-]{7,}\d\b/g, '[numéro masqué]')
        .slice(0, limit);
}

interface ClientErrorOptions {
    sourceLocation?: string;
    componentStack?: string;
}

/** Envoie uniquement les erreurs d’exécution inattendues, sans contenu métier. */
export function reportClientError(kind: ClientErrorKind, error: unknown, fallbackMessage?: string, options: ClientErrorOptions = {}): void {
    if (!tokenStorage.getAccessToken()) return;
    if (error instanceof ApiError) return; // Les erreurs HTTP sont déjà traitées par le client / backend.

    const actualError = error instanceof Error ? error : null;
    const message = scrub(actualError?.message ?? fallbackMessage ?? String(error), 1200);
    if (!message || message === 'Script error.' || message === 'Script error') return;

    const stack = scrub(actualError?.stack ?? '', 7000);
    const signature = `${kind}:${message}:${stack.split('\n').slice(0, 3).join('|')}`;
    const now = Date.now();
    if (now - (lastReports.get(signature) ?? 0) < 60_000) return;
    lastReports.set(signature, now);

    const payload: ClientErrorPayload = {
        kind,
        message,
        ...(stack ? { stack } : {}),
        ...(options.sourceLocation ? { sourceLocation: scrub(options.sourceLocation, 500) } : {}),
        ...(options.componentStack ? { componentStack: scrub(options.componentStack, 4000) } : {}),
        ...(getRecentUserActions() ? { actionTrail: getRecentUserActions() } : {}),
        route: window.location.pathname.slice(0, 500),
        occurredAt: new Date().toISOString(),
        userAgent: navigator.userAgent.slice(0, 300),
    };

    // Le signalement est toujours best-effort : il ne doit ni masquer
    // l’erreur d’origine, ni déclencher une seconde notification d’erreur.
    void apiClient.post('/v1/system/client-errors', payload, { retries: 0 }).catch(() => undefined);
}

export function initializeClientErrorReporting(): void {
    if (initialized || typeof window === 'undefined') return;
    initialized = true;
    initializeUserActionTrail();

    window.addEventListener('error', (event) => {
        const target = event.target;
        const resource = target instanceof HTMLScriptElement ? target.src : target instanceof HTMLLinkElement ? target.href : '';
        const sourceLocation = event.filename
            ? `${event.filename}:${event.lineno}:${event.colno}`
            : resource ? `${new URL(resource, window.location.href).pathname} (ressource)` : undefined;
        const failure = event.error ?? (resource ? new Error('Échec de chargement d’une ressource JavaScript ou CSS.') : null);
        if (failure) reportClientError('window_error', failure, event.message, { sourceLocation });
    }, true);
    window.addEventListener('unhandledrejection', (event) => {
        if (event.reason instanceof ApiError) return;
        reportClientError('unhandled_rejection', event.reason, 'Une opération asynchrone a échoué sans traitement.');
    });
}
