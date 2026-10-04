// ============================================================
// upload/services/api/interceptors.ts
// Pipeline d'intercepteurs request/response (pattern Axios-like)
//
// ⚠️ Volontairement ABSENT : le renouvellement de jeton.
// H-Immo n'émet qu'un jeton d'accès, valable `JWT_TTL` (1 h par défaut).
// Il n'existe ni endpoint de refresh, ni refresh token, ni rotation :
// `AuthController::login()` renvoie `accessToken` / `tokenType` /
// `expiresIn` et rien d'autre. Ajouter ici une logique de refresh
// enverrait le client vers une route inexistante (404) et donnerait
// l'illusion d'une session renouvelable. À l'expiration, on rejoue
// un login — c'est aussi ce que le serveur déciderait de toute façon.
//
// Également absent : le CSRF. Le pare-feu `main` est `stateless: true`,
// n'émet aucun cookie et ne lit aucune session ; le jeton voyage dans
// l'en-tête `Authorization`. Il n'y a donc rien à protéger contre un
// envoi forcé par un site tiers.
// ============================================================

import type { RequestConfig, ApiResponse, RequestInterceptor, ResponseInterceptor } from './api.types';

import { tokenStorage } from '../storage/storage.service';

// ─────────────────────────────────────────
// Gestionnaire d'intercepteurs générique
// ─────────────────────────────────────────

class InterceptorManager<T> {
    private handlers: Array<{ onFulfilled: (value: T) => T | Promise<T>; onRejected?: (error: unknown) => unknown } | null> = [];

    use(
        onFulfilled: (value: T) => T | Promise<T>,
        onRejected?: (error: unknown) => unknown,
    ): number {
        this.handlers.push({ onFulfilled, onRejected });
        return this.handlers.length - 1;
    }

    eject(id: number): void {
        if (this.handlers[id]) {
            this.handlers[id] = null;
        }
    }

    /** Applique tous les intercepteurs en séquence (pipeline). */
    async run(initialValue: T): Promise<T> {
        let value = initialValue;
        for (const handler of this.handlers) {
            if (!handler) continue;
            try {
                value = await handler.onFulfilled(value);
            } catch (error) {
                if (handler.onRejected) {
                    handler.onRejected(error);
                } else {
                    throw error;
                }
            }
        }
        return value;
    }

    /**
     * Pipeline d'erreur : ne fait passer que par les `onRejected`.
     *
     * `run()` ne reçoit que des valeurs de type `T` (une réponse), il
     * ne peut donc pas porter une `ApiError`. C'est ce chemin que
     * `authErrorInterceptor` utilise pour purger le jeton sur un 401.
     */
    async runRejected(error: unknown): Promise<void> {
        for (const handler of this.handlers) {
            if (!handler?.onRejected) continue;
            try {
                handler.onRejected(error);
            } catch {
                // Un `onRejected` qui redéclare une erreur ne doit pas
                // masquer celle qu'on traitait déjà.
            }
        }
    }
}

// ─────────────────────────────────────────
// Intercepteurs de requête
// ─────────────────────────────────────────

/**
 * Intercepteur : injecte le jeton JWT dans l'en-tête `Authorization`.
 *
 * Aucun appel réseau ici : il n'y a pas de refresh à tenter. Le client
 * envoie le jeton qu'il a, et laisse le serveur trancher (401 → session
 * terminée, cf. `authErrorInterceptor`).
 */
export const jwtInterceptor: RequestInterceptor = {
    onFulfilled: (config: RequestConfig): RequestConfig => {
        const token = tokenStorage.getAccessToken();
        if (!token) return config;

        return {
            ...config,
            headers: {
                ...config.headers,
                Authorization: `Bearer ${token}`,
            },
        };
    },
};

/**
 * Intercepteur : journalise les requêtes sortantes (dev uniquement).
 *
 * `import.meta.env.DEV` et non `process.env.NODE_ENV` : sous Vite, `process`
 * n'existe pas dans le bundle navigateur — `process.env.NODE_ENV` lèverait
 * une `ReferenceError` à l'exécution, pas à la compilation.
 */
export const loggingRequestInterceptor: RequestInterceptor = {
    onFulfilled: (config: RequestConfig): RequestConfig => {
        if (!import.meta.env.DEV) return config;
        console.groupCollapsed(`[API] → ${config.method ?? 'GET'} ${config.url}`);
        if (config.data) console.log('Body :', config.data);
        if (config.params) console.log('Params :', config.params);
        console.groupEnd();
        return config;
    },
};

// ─────────────────────────────────────────
// Intercepteurs de réponse
// ─────────────────────────────────────────

export const loggingResponseInterceptor: ResponseInterceptor = {
    onFulfilled: (response: ApiResponse): ApiResponse => {
        if (!import.meta.env.DEV) return response;
        console.groupCollapsed(`[API] ← ${response.status} ${response.config.url}`);
        console.log('Data :', response.data);
        console.groupEnd();
        return response;
    },
    onRejected: (error: unknown): never => {
        if (import.meta.env.DEV) {
            console.error('[API] Erreur :', error);
        }
        throw error;
    },
};

/**
 * Fin de session : purge le jeton et prévient l'application.
 *
 * Le client ne redirige pas lui-même. Une redirection codée en dur vers
 * `/login` supposerait un routeur et une page qui n'existent pas encore
 * dans ce projet (`AppRoutes.tsx` est un placeholder, `main.tsx` n'a pas
 * de routeur). L'`AuthProvider` s'abonne via `onSessionExpired` et décide
 * de ce que devient l'écran ; la couche HTTP se contente d'informer.
 */
export const SESSION_EXPIRED_EVENT = 'himmo:session-expired';

type SessionExpiredListener = () => void;

const sessionExpiredListeners = new Set<SessionExpiredListener>();

/** Abonne un callback à la fin de session. Retourne la fonction de désabonnement. */
export function onSessionExpired(listener: SessionExpiredListener): () => void {
    sessionExpiredListeners.add(listener);
    return () => {
        sessionExpiredListeners.delete(listener);
    };
}

function notifySessionExpired(): void {
    // Une exception dans un abonné ne doit pas empecher les autres
    // d'être appelés, ni remonter dans le pipeline de la requête.
    for (const listener of sessionExpiredListeners) {
        try {
            listener();
        } catch (error) {
            if (import.meta.env.DEV) {
                console.error('[API] Abonné `onSessionExpired` en erreur :', error);
            }
        }
    }
}

/** Endpoints d'authentification : un 401 y est une réponse attendue, pas une fin de session. */
function isAuthEndpoint(url: string): boolean {
    return url.startsWith('/auth/');
}

export const authErrorInterceptor: ResponseInterceptor = {
    onFulfilled: (response: ApiResponse) => response,
    onRejected: (error: unknown): never => {
        const apiError = error as { status?: number; isApiError?: boolean; config?: RequestConfig };

        if (apiError?.isApiError && apiError.status === 401) {
            const url = apiError.config?.url ?? '';

            // `POST /auth/login` renvoie 401 pour de mauvais identifiants :
            // le formulaire doit afficher l'erreur, pas purger la session.
            if (!isAuthEndpoint(url)) {
                tokenStorage.clearAll();
                notifySessionExpired();
            }
        }

        throw error;
    },
};

// ─────────────────────────────────────────
// Export du gestionnaire
// ─────────────────────────────────────────

export { InterceptorManager };
export type { RequestInterceptor, ResponseInterceptor };
