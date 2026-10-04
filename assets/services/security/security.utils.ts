// ============================================================
// upload/services/security/security.utils.ts
// Utilitaires de sécurité (sanitisation, validation, chiffrement léger)
// ============================================================

// ─────────────────────────────────────────
// Sanitisation XSS
// ─────────────────────────────────────────

/**
 * Échappe les caractères HTML dangereux pour prévenir les attaques XSS.
 * À utiliser avant d'injecter du contenu dans le DOM via innerHTML.
 */
export function escapeHtml(raw: string): string {
    return raw
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Supprime toutes les balises HTML d'une chaîne.
 */
export function stripHtml(html: string): string {
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.textContent ?? div.innerText ?? '';
}

// ─────────────────────────────────────────
// Validation des données
// ─────────────────────────────────────────

/** Vérifie qu'une chaîne est une URL valide (http/https). */
export function isValidUrl(value: string): boolean {
    try {
        const url = new URL(value);
        return url.protocol === 'http:' || url.protocol === 'https:';
    } catch {
        return false;
    }
}

/** Vérifie qu'une URL est sur la même origine que l'application. */
export function isSameOrigin(url: string): boolean {
    try {
        return new URL(url, window.location.href).origin === window.location.origin;
    } catch {
        return false;
    }
}

/** Vérifie qu'une chaîne est une adresse e-mail valide. */
export function isValidEmail(email: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// ─────────────────────────────────────────
// Gestion des tokens JWT
// ─────────────────────────────────────────

/**
 * Rôle métier d'un compte pour une Organization (`src/Enum/OrganizationRole.php`).
 * À ne jamais confondre avec `platformRole` : c'est le rôle qui porte
 * l'isolation multi-tenant, il est différent d'une Organization à l'autre.
 */
export type OrganizationRole = 'patron' | 'admin_immobilier' | 'admin_ville';

/** Rôle global sur la plateforme (`src/Enum/PlatformRole.php`). */
export type PlatformRole = 'super_admin';

/** Revendication `organizations` émise par `TokenManager::issue()`. */
export interface JwtOrganizationMembership {
    uuid: string;
    code: string;
    role: OrganizationRole;
}

/**
 * Claims réellement émis par `App\Service\Identity\TokenManager`.
 *
 * Aucun `permissions`, `role` simple, `locale`, `photoUrl` ni
 * `organization_id` : ces champs n'existent pas dans le contrat. Le rôle se lit
 * dans `platformRole` + `organizations[].role`, et l'identifiant d'une
 * Organization se lit dans `organizations[].uuid` (jamais un id interne).
 */
export interface JwtPayload {
    iss: 'himmo';
    aud: 'himmo-api';
    /** UUID public de l'utilisateur. */
    sub: string;
    /** Identifiant de révocation (`revoked_token`). */
    jti: string;
    iat: number;
    exp: number;
    email: string;
    fullName: string;
    platformRole: PlatformRole | null;
    roles: string[];
    cityScope: 'platform' | 'assigned' | 'none';
    isActive: boolean;
    organizations: JwtOrganizationMembership[];
}

/**
 * Décode le payload d'un JWT sans vérifier la signature.
 * ⚠️ La vérification de signature doit toujours être faite côté serveur
 * (`ApiTokenAuthenticator` la refait sur chaque requête). Ce décodage ne
 * sert qu'à l'affichage : un client qui altère sa copie locale ne
 * s'octroie aucun droit, seule l'API autorise.
 */
export function decodeJwtPayload(token: string): JwtPayload | null {
    try {
        const segments = token.split('.');
        if (segments.length !== 3) return null;
        const base64 = segments[1];
        if (!base64) return null;
        // base64url → base64, puis restoration du padding
        const padded = base64.replace(/-/g, '+').replace(/_/g, '/');
        const decoded = atob(padded.padEnd(padded.length + ((4 - (padded.length % 4)) % 4), '='));
        return JSON.parse(decoded) as JwtPayload;
    } catch {
        return null;
    }
}

/**
 * Vérifie si un token JWT est expiré (d'après le claim `exp`).
 *
 * Un jeton illisible ou sans `exp` est considéré comme expiré : il faut
 * rejouer un `POST /api/auth/login`, ce que le serveur déciderait de
 * toute façon en renvoyant 401.
 *
 * @param bufferSeconds Marge de sécurité en secondes (défaut : 60)
 */
export function isTokenExpired(token: string, bufferSeconds = 60): boolean {
    const payload = decodeJwtPayload(token);
    if (typeof payload?.exp !== 'number') return true;
    return Date.now() / 1000 >= payload.exp - bufferSeconds;
}

/**
 * Extrait les rôles Symfony depuis le payload JWT.
 */
export function extractRoles(token: string): string[] {
    const payload = decodeJwtPayload(token);
    return payload?.roles ?? [];
}

// ─────────────────────────────────────────
// Génération d'identifiants sécurisés
// ─────────────────────────────────────────

/**
 * Génère un UUID v4 aléatoire (crypto.randomUUID si disponible, sinon fallback).
 */
export function generateUUID(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    // Fallback pour les navigateurs anciens
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

/**
 * Génère une chaîne aléatoire sécurisée (pour nonces, états OAuth, etc.).
 * @param length Longueur en octets (défaut : 32)
 */
export function generateNonce(length = 32): string {
    const array = new Uint8Array(length);
    crypto.getRandomValues(array);
    return Array.from(array, (b) => b.toString(16).padStart(2, '0')).join('');
}

// ─────────────────────────────────────────
// Protection contre les attaques de timing
// ─────────────────────────────────────────

/**
 * Comparaison de chaînes en temps constant (prévient les timing attacks).
 * À utiliser pour comparer des tokens ou des hash côté client.
 */
export function timingSafeEqual(a: string, b: string): boolean {
    if (a.length !== b.length) return false;
    let result = 0;
    for (let i = 0; i < a.length; i++) {
        result |= a.charCodeAt(i) ^ b.charCodeAt(i);
    }
    return result === 0;
}

// ─────────────────────────────────────────
// Content-Security-Policy helpers
// ─────────────────────────────────────────

/**
 * Vérifie que le Content-Type d'une réponse est bien JSON.
 */
export function isJsonContentType(contentType: string | null): boolean {
    if (!contentType) return false;
    return contentType.includes('application/json') || contentType.includes('application/ld+json');
}

/**
 * Vérifie si un objet est une instance d'ApiError (duck typing).
 */
export function isApiError(error: unknown): error is { isApiError: true; status: number; message: string } {
    return (
        typeof error === 'object' &&
        error !== null &&
        'isApiError' in error &&
        (error as Record<string, unknown>).isApiError === true
    );
}
