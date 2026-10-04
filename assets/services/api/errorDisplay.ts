// ============================================================
// upload/services/api/errorDisplay.ts
// Normalisation des erreurs API → modèle d'affichage utilisateur
// Chaque erreur produit un titre + message compréhensible et une
// trace technique conservée pour le débogage (uniquement en log).
// ============================================================

import { ApiError } from './api.types';

export type ApiErrorKind =
    | 'bad-request'
    | 'unauthorized'
    | 'forbidden'
    | 'not-found'
    | 'server'
    | 'network'
    | 'timeout'
    | 'validation'
    | 'unknown';

export interface ErrorStateModel {
    /** Code HTTP (undefined pour une erreur inattendue). */
    status?: number;
    kind: ApiErrorKind;
    /** Libellé HTTP secondaire, ex. "Internal Server Error". */
    codeLabel?: string;
    title: string;
    message: string;
    /** Indique si un bouton « Réessayer » est pertinent. */
    retryable: boolean;
    /** Trace technique destinée aux logs, jamais affichée brute. */
    log: string;
}

const HTTP_LABELS: Record<number, string> = {
    400: 'Bad Request',
    401: 'Unauthorized',
    403: 'Forbidden',
    404: 'Not Found',
    408: 'Request Timeout',
    409: 'Conflict',
    422: 'Unprocessable Entity',
    429: 'Too Many Requests',
    500: 'Internal Server Error',
    502: 'Bad Gateway',
    503: 'Service Unavailable',
    504: 'Gateway Timeout',
};

const KIND_DEFINITIONS: Record<ApiErrorKind, { title: string; message: string }> = {
    'bad-request': {
        title: 'Requête invalide',
        message: 'La requête envoyée au serveur est invalide. Veuillez vérifier les informations saisies.',
    },
    unauthorized: {
        title: 'Connexion requise',
        message: 'Vous n\'êtes pas connecté ou votre session a expiré. Veuillez vous connecter pour continuer.',
    },
    forbidden: {
        title: 'Accès refusé',
        message: 'Vous n\'avez pas les permissions nécessaires pour accéder à cette ressource.',
    },
    'not-found': {
        title: 'Ressource introuvable',
        message: 'La page ou la ressource demandée n\'existe pas ou n\'est plus disponible.',
    },
    server: {
        title: 'Une erreur est survenue',
        message: 'Le serveur a rencontré un problème. Veuillez réessayer plus tard.',
    },
    network: {
        title: 'Serveur indisponible',
        message: 'Impossible de contacter le serveur. Vérifiez votre connexion Internet ou réessayez plus tard.',
    },
    timeout: {
        title: 'Temps de réponse dépassé',
        message: 'Le serveur met trop de temps à répondre. Veuillez réessayer.',
    },
    validation: {
        title: 'Données invalides',
        message: 'Certaines informations envoyées sont invalides. Veuillez vérifier votre saisie.',
    },
    unknown: {
        title: 'Une erreur est survenue',
        message: 'Une erreur inattendue est survenue. Veuillez réessayer.',
    },
};

export function getHttpLabel(status?: number): string | undefined {
    if (status === undefined) return undefined;
    return HTTP_LABELS[status];
}

function kindToState(kind: ApiErrorKind, status?: number): ErrorStateModel {
    const def = KIND_DEFINITIONS[kind];
    const retryable = ['bad-request', 'server', 'network', 'timeout', 'unknown'].includes(kind);
    return {
        status,
        kind,
        codeLabel: getHttpLabel(status),
        title: def.title,
        message: def.message,
        retryable,
        log: `[${status ?? '-'}] ${def.title}`,
    };
}

/**
 * Transforme n'importe quelle erreur (ApiError, DOMException, Error…)
 * en modèle d'affichage sûr pour l'utilisateur.
 */
export function toErrorState(
    error: unknown,
    options?: { fallbackMessage?: string },
): ErrorStateModel {
    if (error instanceof ApiError) {
        const { status } = error;
        let state: ErrorStateModel;

        if (status === 0) {
            state = kindToState('network', 0);
        } else if (status === 401) {
            state = kindToState('unauthorized', status);
        } else if (status === 403) {
            state = kindToState('forbidden', status);
        } else if (status === 404) {
            state = kindToState('not-found', status);
        } else if (status === 408) {
            state = kindToState('timeout', status);
        } else if (status === 422) {
            state = kindToState('validation', status);
        } else if (status >= 500) {
            state = kindToState('server', status);
        } else if (status >= 400) {
            state = kindToState('bad-request', status);
        } else {
            state = kindToState('unknown', status);
        }

        state.log = `ApiError ${status} ${error.statusText} — ${error.message}`
            + ` — ${error.config?.method ?? 'GET'} ${error.config?.url ?? ''}`
            + (error.violations && Object.keys(error.violations).length > 0
                ? ` — violations: ${Object.keys(error.violations).join(', ')}`
                : '')
            + (error.stack ? `\n${error.stack.split('\n').slice(0, 3).join('\n')}` : '');
        return state;
    }

    // Erreur réseau native (fetch) : "Failed to fetch", "NetworkError"…
    if (error instanceof TypeError || error instanceof DOMException) {
        const timeout = error instanceof DOMException && error.name === 'TimeoutError';
        return kindToState(timeout ? 'timeout' : 'network', 0);
    }

    if (error instanceof Error) {
        return {
            ...kindToState('unknown'),
            message: options?.fallbackMessage ?? kindToState('unknown').message,
            log: `${error.name}: ${error.message}`
                + (error.stack ? `\n${error.stack.split('\n').slice(0, 3).join('\n')}` : ''),
        };
    }

    return {
        ...kindToState('unknown'),
        message: options?.fallbackMessage ?? kindToState('unknown').message,
        log: String(error ?? 'Erreur inconnue'),
    };
}

/**
 * Journalise une erreur en conservant toute la trace technique,
 * sans jamais exposer ces détails à l'utilisateur.
 */
export function logApiError(error: unknown, context?: string): void {
    const state = toErrorState(error);
    console.error(
        `[ERREUR API]${context ? ` ${context}` : ''} — ${state.log}`,
        { kind: state.kind, status: state.status, error },
    );
}
