// ============================================================
// upload/services/security/csrf.ts
// Lecture d'un token CSRF dans le DOM.
//
// ⚠️ NON UTILISÉ PAR LE CLIENT HTTP, ET C'EST VOLONTAIRE.
//
// Le pare-feu `main` est `stateless: true` : `security.yaml` précise
// qu'aucune session PHP n'est créée ni lue et qu'aucun cookie n'est
// émis. L'authentification passe par l'en-tête `Authorization: Bearer`,
// que le navigateur n'envoie pas tout seul. Un site tiers peut donc
// forcer une requête cross-site, mais elle arrivera sans jeton et
// recevra un 401 : il n'y a aucune action à usurper.
//
// Un token CSRF ne protège que ce qui s'appuie sur une authentification
// ambiguë côté navigateur (cookie de session). Ce projet n'en a pas.
// `index.html` ne contient d'ailleurs aucune balise
// `<meta name="csrf-token">`, et aucun cookie `XSRF-TOKEN` n'est posé :
// `getCsrfHeaders()` renverrait toujours `{}`.
//
// Ce module reste disponible pour un éventuel formulaire Twig rendu
// côté serveur, mais il ne doit pas être branché sur `apiClient` tant
// que l'authentification reste par jeton.
// ============================================================

const CSRF_META_NAME = 'csrf-token';
const CSRF_HEADER    = 'X-CSRF-Token';
const CSRF_COOKIE_RE = /XSRF-TOKEN=([^;]+)/;

// ─────────────────────────────────────────
// Lecture du token depuis différentes sources Symfony
// ─────────────────────────────────────────

/**
 * Lit le token CSRF depuis la balise <meta name="csrf-token" content="...">
 * générée par Twig : {{ csrf_token('authenticate') }}
 */
function fromMeta(): string | null {
    const el = document.querySelector<HTMLMetaElement>(`meta[name="${CSRF_META_NAME}"]`);
    return el?.content ?? null;
}

/**
 * Lit le token depuis le cookie XSRF-TOKEN (compatible avec les stacks
 * qui envoient le cookie côté serveur, ex. Symfony avec NelmioApiDocBundle).
 */
function fromCookie(): string | null {
    const match = document.cookie.match(CSRF_COOKIE_RE);
    return match ? decodeURIComponent(match[1]) : null;
}

/**
 * Lit le token depuis un champ caché <input type="hidden" name="_token" ...>
 * dans les formulaires Symfony classiques.
 */
function fromHiddenInput(formSelector = 'form'): string | null {
    const input = document.querySelector<HTMLInputElement>(
        `${formSelector} input[name="_token"]`,
    );
    return input?.value ?? null;
}

// ─────────────────────────────────────────
// API publique
// ─────────────────────────────────────────

/**
 * Retourne le token CSRF en cherchant dans l'ordre :
 * 1. <meta name="csrf-token">
 * 2. Cookie XSRF-TOKEN
 * 3. Input hidden _token
 */
export function getCsrfToken(): string | null {
    return fromMeta() ?? fromCookie() ?? fromHiddenInput() ?? null;
}

/**
 * Retourne le header CSRF à injecter dans les requêtes mutantes.
 * Retourne un objet vide si aucun token n'est trouvé.
 */
export function getCsrfHeaders(): Record<string, string> {
    const token = getCsrfToken();
    if (!token) return {};
    return { [CSRF_HEADER]: token };
}

/**
 * Indique si la méthode HTTP nécessite une protection CSRF.
 * GET, HEAD et OPTIONS sont "safe" selon RFC 7231.
 */
export function requiresCsrf(method: string): boolean {
    return !['GET', 'HEAD', 'OPTIONS'].includes(method.toUpperCase());
}

/**
 * Met à jour le contenu de la balise <meta> CSRF avec un nouveau token
 * (utile après une réponse de renouvellement de token côté Symfony).
 */
export function updateCsrfMeta(newToken: string): void {
    let el = document.querySelector<HTMLMetaElement>(`meta[name="${CSRF_META_NAME}"]`);
    if (!el) {
        el = document.createElement('meta');
        el.name = CSRF_META_NAME;
        document.head.appendChild(el);
    }
    el.content = newToken;
}
