// ============================================================
// upload/services/api/client.ts
// Client HTTP basé sur Fetch — API Axios-like
// ============================================================

import type {
    RequestConfig,
    ApiResponse,
    ClientConfig,
    ApiErrorData,
    HttpMethod,
} from './api.types';
import { ApiError } from './api.types';
import {
    InterceptorManager,
    jwtInterceptor,
    loggingRequestInterceptor,
    loggingResponseInterceptor,
    authErrorInterceptor,
} from './interceptors';
import { isJsonContentType } from '../security/security.utils';

// ─────────────────────────────────────────
// Constantes
// ─────────────────────────────────────────

const DEFAULT_TIMEOUT    = 30_000; // 30 secondes
const DEFAULT_BASE_URL   = '/api';
const DEFAULT_RETRIES    = 2;
const DEFAULT_RETRY_DELAY = 300; // base du backoff exponentiel (ms)
const MAX_RETRY_DELAY    = 10_000; // plafond du backoff (ms)

// ─────────────────────────────────────────
// Classe principale
// ─────────────────────────────────────────

class HttpClient {
    private readonly config: Required<ClientConfig>;

    readonly interceptors = {
        request:  new InterceptorManager<RequestConfig>(),
        response: new InterceptorManager<ApiResponse>(),
    };

    constructor(config: ClientConfig) {
        this.config = {
            baseURL:        config.baseURL,
            timeout:        config.timeout        ?? DEFAULT_TIMEOUT,
            defaultHeaders: config.defaultHeaders ?? {},
            retries:        config.retries        ?? DEFAULT_RETRIES,
            retryDelay:     config.retryDelay     ?? DEFAULT_RETRY_DELAY,
        };

        // Enregistrement des intercepteurs par défaut.
        // Aucun intercepteur CSRF : le pare-feu est `stateless`, aucun
        // cookie n'est émis, donc un envoi forcé par un site tiers n'a
        // rien à protéger.
        this.interceptors.request.use(loggingRequestInterceptor.onFulfilled);
        this.interceptors.request.use(jwtInterceptor.onFulfilled);

        this.interceptors.response.use(
            loggingResponseInterceptor.onFulfilled!,
            loggingResponseInterceptor.onRejected,
        );
        this.interceptors.response.use(
            authErrorInterceptor.onFulfilled!,
            authErrorInterceptor.onRejected,
        );
    }

    // ── Construction de l'URL ────────────────────

    private buildUrl(config: RequestConfig): string {
        let url = config.url.startsWith('http')
            ? config.url
            : `${this.config.baseURL}${config.url}`;

        if (config.params) {
            const qs = new URLSearchParams();
            Object.entries(config.params).forEach(([k, v]) => {
                if (v !== null && v !== undefined) {
                    qs.append(k, String(v));
                }
            });
            const queryString = qs.toString();
            if (queryString) url += (url.includes('?') ? '&' : '?') + queryString;
        }
        return url;
    }

    // ── Construction des headers ─────────────────

    private buildHeaders(config: RequestConfig): HeadersInit {
        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
            Accept:         'application/json',
            ...this.config.defaultHeaders,
            ...config.headers,
        };
        return headers;
    }

    // ── Lecture de la réponse ────────────────────

    private async parseBody(response: Response): Promise<unknown> {
        const contentType = response.headers.get('Content-Type');
        if (isJsonContentType(contentType)) {
            try {
                return await response.json();
            } catch {
                return null;
            }
        }
        const text = await response.text();
        return text || null;
    }

    // ── Cœur de la requête ───────────────────────

    private async executeRequest<T>(config: RequestConfig, attempt = 0): Promise<ApiResponse<T>> {
        const url = this.buildUrl(config);

        // AbortController pour le timeout. Si l'appelant fournit son propre
        // `signal`, les deux sont écoutés : une annulation côté UI et
        // l'expiration du timeout restent distinguables.
        const timeoutController = new AbortController();
        const signal = config.signal
            ? AbortSignal.any([config.signal, timeoutController.signal])
            : timeoutController.signal;
        const timeoutId = setTimeout(
            () => timeoutController.abort(new DOMException('Request timeout', 'TimeoutError')),
            config.timeout ?? this.config.timeout,
        );

        try {
            const headers = this.buildHeaders(config);
            const fetchOptions: RequestInit = {
                method: config.method ?? 'GET',
                headers,
                signal,
            };

            if (config.data !== undefined && config.method !== undefined && config.method !== 'GET') {
                if (config.data instanceof FormData) {
                    fetchOptions.body = config.data;
                    // Supprimer Content-Type pour que le navigateur définisse
                    // lui-même la frontière multipart (boundary).
                    delete (headers as Record<string, string>)['Content-Type'];
                } else {
                    fetchOptions.body = JSON.stringify(config.data);
                }
            }

            const raw = await fetch(url, fetchOptions);
            clearTimeout(timeoutId);

            const body = await this.parseBody(raw);

            if (!raw.ok) {
                const apiError = new ApiError(
                    this.extractErrorMessage(body, raw.statusText),
                    raw.status,
                    raw.statusText,
                    (body as ApiErrorData) ?? {},
                    config,
                    raw.headers,
                );

                if (this.isRetryableError(apiError) && attempt < this.maxRetries(config)) {
                    const wait = this.resolveRetryDelay(apiError, attempt, config);
                    await this.delay(wait);
                    return this.executeRequest<T>(config, attempt + 1);
                }

                this.throwOfflineIfNeeded(apiError);
                throw apiError;
            }

            return {
                data: body as T,
                status: raw.status,
                statusText: raw.statusText,
                headers: raw.headers,
                config,
            };

        } catch (err) {
            clearTimeout(timeoutId);

            if (err instanceof ApiError) throw err;

            // Timeout explicite (n'attend pas la réponse du serveur)
            if (err instanceof DOMException && err.name === 'TimeoutError') {
                const timeoutError = new ApiError('La requête a expiré', 408, 'Request Timeout', {}, config);
                if (attempt < this.maxRetries(config)) {
                    await this.delay(this.backoff(attempt, config.retryDelay ?? this.config.retryDelay));
                    return this.executeRequest<T>(config, attempt + 1);
                }
                throw timeoutError;
            }

            if (err instanceof DOMException && err.name === 'AbortError') {
                throw new ApiError('Requête annulée', 0, 'Aborted', {}, config);
            }

            // Erreur réseau : retry seulement si le navigateur est en ligne
            const networkError = new ApiError(
                (err as Error)?.message ?? 'Erreur réseau inconnue',
                0,
                'Network Error',
                {},
                config,
            );
            if (this.isRetryableError(networkError) && attempt < this.maxRetries(config)) {
                await this.delay(this.backoff(attempt, config.retryDelay ?? this.config.retryDelay));
                return this.executeRequest<T>(config, attempt + 1);
            }
            this.throwOfflineIfNeeded(networkError);
            throw networkError;
        }
    }

    /**
     * Message d'erreur lisible, quelle que soit l'enveloppe reçue.
     *
     * L'API produit deux formes : `HttpErrorResponsePayload`
     * (`{ status, error, message, details }`) pour les erreurs HTTP, et
     * `Feedback` (`{ flush, flushDescription, errors }`) pour les échecs
     * applicatifs renvoyant 200/4xx. Sans cela, un 409 métier afficherait
     * « Conflict » au lieu du message réellement écrit par le service.
     */
    private extractErrorMessage(body: unknown, fallback: string): string {
        if (typeof body !== 'object' || body === null) {
            return typeof body === 'string' && body !== '' ? body : fallback;
        }

        const payload = body as ApiErrorData;

        if (typeof payload.message === 'string' && payload.message !== '') return payload.message;
        if (typeof payload.flushDescription === 'string' && payload.flushDescription !== '') {
            return payload.flushDescription;
        }

        const firstViolation = Object.values(payload.details?.violations ?? {})[0];
        if (firstViolation?.[0]) return firstViolation[0];

        const firstError = Object.values(payload.errors ?? {})[0];
        if (firstError) return firstError;

        return fallback;
    }

    /**
     * Délai avant la nouvelle tentative.
     *
     * Un 429 porte un `Retry-After` : le backend applique un limiteur
     * (`login_throttling`, 5 tentatives / 15 min). L'ignorer et réessayer
     * au bout de 300 ms ne fait que consommer de nouveau quota.
     */
    private resolveRetryDelay(error: ApiError, attempt: number, config: RequestConfig): number {
        const retryAfter = error.headers.get?.('Retry-After');
        if (retryAfter) {
            const seconds = Number(retryAfter);
            if (Number.isFinite(seconds) && seconds > 0) {
                return Math.min(seconds * 1000, MAX_RETRY_DELAY);
            }
        }
        return this.backoff(attempt, config.retryDelay ?? this.config.retryDelay);
    }

    private delay(ms: number): Promise<void> {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    // ── Helpers de retry ────────────────────────

    /**
     * Backoff exponentiel avec jitter (+/-50% du slot).
     * Ex. base 300 → ~300, ~600, ~1200, ... plafonné à 10s.
     */
    private backoff(attempt: number, base: number): number {
        const slot = Math.min(MAX_RETRY_DELAY, base * 2 ** attempt);
        return slot / 2 + Math.random() * slot;
    }

    /** Méthodes idempotentes : rejouées automatiquement sans risque. */
    private isIdempotent(method?: HttpMethod): boolean {
        return method === undefined || method === 'GET' || method === 'PUT' || method === 'DELETE';
    }

    /** Nombre maximal de tentatives pour une requête. */
    private maxRetries(config: RequestConfig): number {
        if (config.retries !== undefined) return config.retries;
        return this.isIdempotent(config.method) ? this.config.retries : 0;
    }

    /** Une erreur est-elle rejouable ? */
    private isRetryableError(error: ApiError): boolean {
        if (error.status >= 500) return true;          // erreur serveur temporaire
        if (error.status === 408) return true;         // timeout
        if (error.status === 429) return true;         // rate limit
        if (error.status === 0) return navigator.onLine !== false; // réseau : seulement si connecté
        return false;
    }

    /** Une erreur réseau est-elle un simple état hors-ligne ? */
    private throwOfflineIfNeeded(error: ApiError): never {
        if (error.isOffline) {
            throw new ApiError(
                'Vous êtes hors-ligne. Vérifiez votre connexion.',
                0,
                'Offline',
                {},
                error.config,
            );
        }
        throw error;
    }

    // ── Méthode principale ───────────────────────

    /**
     * Exécute une requête à travers les deux pipelines.
     *
     * Un 401 n'est pas rejoué : il n'y a pas de refresh à tenter. Le
     * pipeline de réponse (`authErrorInterceptor`) purge le jeton et
     * prévient l'application via `onSessionExpired`, qui décidera de
     * l'écran à afficher. Rejouer la requête ne changerait rien : le
     * serveur refuserait le même jeton, et cela créerait une boucle.
     */
    async request<T = unknown>(config: RequestConfig): Promise<ApiResponse<T>> {
        const finalConfig = await this.interceptors.request.run(config);

        let response: ApiResponse<T>;
        try {
            response = await this.executeRequest<T>(finalConfig);
        } catch (error) {
            // Le pipeline d'erreur purge le jeton sur un 401 et prévient
            // l'application. L'erreur originale est ensuite relancée à
            // l'identique : l'appelant doit recevoir la vraie cause.
            await this.interceptors.response.runRejected(error);
            throw error;
        }

        // Pipeline réponse (cast nécessaire pour le type générique)
        response = (await this.interceptors.response.run(response as ApiResponse)) as ApiResponse<T>;

        return response;
    }

    // ── Méthodes de commodité ────────────────────

    get<T = unknown>(url: string, config?: Omit<RequestConfig, 'url' | 'method'>): Promise<ApiResponse<T>> {
        return this.request<T>({ ...config, url, method: 'GET' });
    }

    post<T = unknown>(url: string, data?: unknown, config?: Omit<RequestConfig, 'url' | 'method' | 'data'>): Promise<ApiResponse<T>> {
        return this.request<T>({ ...config, url, method: 'POST', data });
    }

    put<T = unknown>(url: string, data?: unknown, config?: Omit<RequestConfig, 'url' | 'method' | 'data'>): Promise<ApiResponse<T>> {
        return this.request<T>({ ...config, url, method: 'PUT', data });
    }

    patch<T = unknown>(url: string, data?: unknown, config?: Omit<RequestConfig, 'url' | 'method' | 'data'>): Promise<ApiResponse<T>> {
        return this.request<T>({ ...config, url, method: 'PATCH', data });
    }

    delete<T = unknown>(url: string, config?: Omit<RequestConfig, 'url' | 'method'>): Promise<ApiResponse<T>> {
        return this.request<T>({ ...config, url, method: 'DELETE' });
    }
}

// ─────────────────────────────────────────
// Instance par défaut (singleton)
// ─────────────────────────────────────────

export const apiClient = new HttpClient({
    baseURL:  DEFAULT_BASE_URL,
    timeout:  DEFAULT_TIMEOUT,
    retries:  DEFAULT_RETRIES,
    defaultHeaders: {
        'X-Requested-With': 'XMLHttpRequest', // Symfony détecte les requêtes AJAX
    },
});

export { HttpClient };
export default apiClient;
