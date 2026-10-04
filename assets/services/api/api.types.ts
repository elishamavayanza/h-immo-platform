// ============================================================
// upload/services/api/api.types.ts
// Types & interfaces partagés de la couche HTTP
//
// Les types marqués « contrat » reflètent exactement ce que le backend
// Symfony sérialise. Toute divergence ici est un bug d'intégration, pas
// une préférence de style : le client n'invente aucun champ.
// ============================================================

// ─────────────────────────────────────────
// Méthodes HTTP supportées
// ─────────────────────────────────────────
export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

// ─────────────────────────────────────────
// Configuration d'une requête
// ─────────────────────────────────────────
export interface RequestConfig {
    /** URL relative ou absolue */
    url: string;
    method?: HttpMethod;
    /** Corps de la requête (sérialisé automatiquement en JSON) */
    data?: unknown;
    /** Query params ajoutés à l'URL */
    params?: Record<string, string | number | boolean | null | undefined>;
    /** Headers supplémentaires */
    headers?: Record<string, string>;
    /** Timeout en millisecondes (défaut : 30 000) */
    timeout?: number;
    /** Signal d'annulation (AbortController) */
    signal?: AbortSignal;
    /**
     * Nombre de nouvelles tentatives pour CETTE requête.
     * Sans valeur : retry uniquement pour les requêtes idempotentes
     * (GET/PUT/DELETE). Un POST n'est jamais rejoué automatiquement,
     * sauf si l'appelant déclare son endpoint idempotent.
     */
    retries?: number;
    /** Délai de base du backoff exponentiel en ms (défaut : 300) */
    retryDelay?: number;
}

// ─────────────────────────────────────────
// Réponse normalisée
// ─────────────────────────────────────────
export interface ApiResponse<T = unknown> {
    data: T;
    status: number;
    statusText: string;
    headers: Headers;
    /** Config de la requête originale */
    config: RequestConfig;
}
// ─────────────────────────────────────────
// Erreur API normalisée
// ─────────────────────────────────────────

/**
 * Corps renvoyé par `ApiExceptionListener` pour toute exception non gérée.
 * Correspond à `App\Dto\Response\HttpErrorResponsePayload`.
 */
export interface HttpErrorResponsePayload {
    status: number;
    /** Libellé HTTP court, ex. "Not Found". */
    error: string;
    /** Message destiné à l'affichage. */
    message: string;
    /**
     * Détails complémentaires. Pour une 422, contient
     * `{ violations: { champ: string[] } }` (les violations de validation
     * sont volontairement exposées : elles guident la correction du
     * formulaire). Les stack traces n'apparaissent qu'en debug.
     */
    details?: {
        violations?: Record<string, string[]>;
        [key: string]: unknown;
    } | null;
}

/**
 * Données d'erreur vues par le client..Union des deux enveloppes
 * réellement produites par l'API : `HttpErrorResponsePayload` (erreurs
 * HTTP) et `Feedback` (enveloppe applicative).
 */
export interface ApiErrorData {
    status?: number;
    error?: string;
    message?: string;
    details?: HttpErrorResponsePayload['details'];
    /** `Feedback::addError(field, message)` → une message par champ. */
    errors?: Record<string, string>;
    warnings?: Record<string, string>;
    flush?: string | null;
    flushDescription?: string | null;
    [key: string]: unknown;
}

export class ApiError extends Error {
    public readonly status: number;
    public readonly statusText: string;
    public readonly data: ApiErrorData;
    public readonly config: RequestConfig;
    /** En-têtes de la réponse, pour exploiter `Retry-After` sur un 429. */
    public readonly headers: Headers;
    public readonly isApiError = true as const;

    constructor(
        message: string,
        status: number,
        statusText: string,
        data: ApiErrorData,
        config: RequestConfig,
        headers: Headers = new Headers(),
    ) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.statusText = statusText;
        this.data = data;
        this.config = config;
        this.headers = headers;
    }

    /** Vérifie si l'erreur est liée à l'authentification (401) */
    get isUnauthorized(): boolean {
        return this.status === 401;
    }

    /** Vérifie si l'erreur est une erreur de validation (422) */
    get isValidation(): boolean {
        return this.status === 422;
    }

    /**
     * Violations de validation par champ, si le serveur en a renvoyé.
     * L'API les place dans `details.violations` (un tableau de messages
     * par champ), et non à la racine.
     */
    get violations(): Record<string, string[]> {
        return this.data.details?.violations ?? {};
    }

    /** Vérifie si l'erreur est une erreur serveur (5xx) */
    get isServerError(): boolean {
        return this.status >= 500;
    }

    /** Vérifie si l'erreur est une erreur réseau (aucune réponse reçue) */
    get isNetworkError(): boolean {
        return this.status === 0;
    }

    /** Vérifie si l'erreur est un timeout */
    get isTimeout(): boolean {
        return this.status === 408;
    }

    /** Vérifie si la requête a échoué car le navigateur est hors-ligne */
    get isOffline(): boolean {
        return this.status === 0 && typeof navigator !== 'undefined' && navigator.onLine === false;
    }
}

// ─────────────────────────────────────────
// Interceptors (request / response)
// ─────────────────────────────────────────
export interface RequestInterceptor {
    onFulfilled: (config: RequestConfig) => RequestConfig | Promise<RequestConfig>;
    onRejected?: (error: unknown) => unknown;
}

export interface ResponseInterceptor<T = unknown> {
    onFulfilled: (response: ApiResponse<T>) => ApiResponse<T> | Promise<ApiResponse<T>>;
    onRejected?: (error: unknown) => unknown;
}

// ─────────────────────────────────────────
// Configuration du client HTTP
// ─────────────────────────────────────────
export interface ClientConfig {
    /** URL de base (ex: "/api") */
    baseURL: string;
    /** Timeout global en ms */
    timeout?: number;
    /** Headers par défaut */
    defaultHeaders?: Record<string, string>;
    /** Nombre de tentatives automatiques sur erreur réseau */
    retries?: number;
    /** Délai de base du backoff exponentiel en ms (défaut : 300) */
    retryDelay?: number;
}
// ─────────────────────────────────────────
// Contrat : pagination
// ─────────────────────────────────────────

/** Paramètres de pagination acceptés par `#[MapQueryString] PaginationQuery`. */
export interface PaginationParams {
    page?: number;
    limit?: number;
    sortBy?: string;
    sortOrder?: 'asc' | 'desc';
}

/**
 * Collection paginée renvoyée par les endpoints de liste.
 *
 * ⚠️ Le backend n'émet PAS `{ data, total, page, limit, totalPages }`.
 * Selon l'endpoint, la clé est `items` et le compteur de pages vaut
 * `pages` (`PublicShowcaseService`, `AuditLogService`) ou `totalItems` /
 * `totalPages` (`PaginatedResponse::create`). `pages` est le cas le plus
 * répandu : c'est celui des endpoints de liste ajoutés récemment.
 */
export interface PaginatedCollection<T> {
    items: T[];
    total: number;
    page: number;
    pages: number;
}

// ─────────────────────────────────────────
// Contrat : authentification
// ─────────────────────────────────────────

/** Rôle métier pour une Organization (`src/Enum/OrganizationRole.php`). */
export type OrganizationRole = 'patron' | 'admin_immobilier' | 'admin_ville';

/** Rôle plateforme (`src/Enum/PlatformRole.php`), `null` pour un compte métier. */
export type PlatformRole = 'super_admin';

/** Mode de résolution du périmètre villes (`src/Enum/CityAccessScope.php`). */
export type CityAccessScope = 'platform' | 'assigned' | 'none';

/**
 * Rôle métier du compte sur une Organization.
 * Correspond à `SessionOrganizationMembership` côté backend.
 */
export interface SessionOrganizationMembership {
    uuid: string;
    code: string;
    name: string;
    role: OrganizationRole;
}

/** Ville assignée au compte (pertinent pour un rôle `admin_ville`). */
export interface SessionCityAccess {
    uuid: string;
    code: string;
    name: string;
}

/**
 * Utilisateur de la session — `SessionUserResponse`.
 *
 * Renvoyé par `POST /api/auth/login` (sous la clé `user`) et par
 * `GET /api/auth/me` (directement, sans enveloppe `Feedback`).
 *
 * Ces données servent l'affichage des bons sélecteurs. Elles ne
 * confèrent aucun droit : l'API recalcule le périmètre à chaque requête.
 */
export interface SessionUserResponse {
    uuid: string;
    email: string;
    fullName: string;
    phone: string | null;
    profilePhoto: string | null;
    platformRole: PlatformRole | null;
    /** Rôles Symfony effectifs (`ROLE_USER`, `ROLE_SUPER_ADMIN`…). */
    roles: string[];
    organizations: SessionOrganizationMembership[];
    cities: SessionCityAccess[];
    cityScope: CityAccessScope;
    isActive: boolean;
    lastLoginAt: string | null;
}

/** Corps de `POST /api/auth/login` (`json_login`, champs `email`/`password`). */
export interface LoginRequest {
    email: string;
    password: string;
}

/**
 * Réponse de `POST /api/auth/login`.
 *
 * Il n'existe pas de refresh token : `accessToken` est le seul jeton
 * délivré, valable `expiresIn` secondes. Passer `expiresIn` à
 * `tokenStorage.setAccessToken()` pour que le client l'efface à temps.
 */
export interface LoginResponse {
    accessToken: string;
    tokenType: 'Bearer';
    expiresIn: number;
    user: SessionUserResponse;
}

/** Réponse de `POST /api/auth/logout`. */
export interface LogoutResponse {
    message: string;
    /** `false` si le `jti` était absent, expiré ou déjà révoqué. */
    tokenRevoked: boolean;
}

// ─────────────────────────────────────────
// Contrat : enveloppe Feedback
// ─────────────────────────────────────────

/**
 * Enveloppe standard de toute réponse applicative (`App\Dto\Feedback`).
 * Les contrôleurs répondent `json($feedback, $feedback->getStatus())`.
 */
export interface Feedback<T = unknown> {
    status: number;
    flush: string | null;
    flushDescription: string | null;
    errors: Record<string, string>;
    warnings: Record<string, string>;
    data: T;
}
