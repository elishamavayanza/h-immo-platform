import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';

import { onSessionExpired, SESSION_REAUTH_NOTICE_KEY } from '../../../services/api/interceptors';
import { ApiError, type SessionUserResponse } from '../../../services/api/api.types';
import { authService } from '../../features/auth/services/authService';
import { tokenStorage } from '../../../services/storage/storage.service';
import { decodeJwtPayload, isTokenExpired } from '../../../services/security/security.utils';
import {
    clearPendingLoginTheme,
    readPendingLoginTheme,
    saveUserPreferences,
} from '../../services/userPreferences';
import { settingsService } from '../../features/shared/account/settings/services/settingsService';
import { flushPendingClientErrors } from '../../services/clientErrorReporting';

interface AuthContextValue {
    user: SessionUserResponse | null;
    isAuthenticated: boolean;
    isLoading: boolean;
    accessToken: string | null;
    updateUser: (updates: Partial<SessionUserResponse>) => void;
    refreshUser: () => Promise<void>;
    /**
     * `POST /api/auth/login` (firewall `json_login`, champs `email`/`password`).
     * Lève une `ApiError` 401 ou 429 : le message doit être affiché tel
     * quel, il est volontairement générique pour ne pas révéler si l'email
     * existe. 429 = trop de tentatives (`login_throttling`).
     */
    login: (email: string, password: string) => Promise<void>;
    /**
     * `POST /api/auth/logout` : révoque le `jti` du jeton côté serveur.
     * L'état local est purgé même si l'appel échoue — un 401 signifie
     * déjà que le jeton n'est plus valable, et garder un jeton invalide
     * en mémoire n'apporte rien.
     */
    logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);
const REFRESH_TOKEN_TTL_SECONDS = 30 * 24 * 60 * 60;

/**
 * Une panne transitoire ne rend pas le JWT invalide : le serveur n'a simplement
 * pas pu le vérifier. Conserver le jeton permet de restaurer la session après
 * le rétablissement de l'API, au lieu de forcer une reconnexion inutile.
 */
function isTransientApiFailure(cause: unknown): boolean {
    return cause instanceof ApiError
        && (cause.status === 0 || cause.status === 408 || cause.status === 429 || cause.status >= 500);
}

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<SessionUserResponse | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [accessToken, setAccessToken] = useState<string | null>(() => tokenStorage.getAccessToken());
    const refreshInFlight = useRef<Promise<string | null> | null>(null);

    const refreshAccessToken = useCallback(async (): Promise<string | null> => {
        const currentRefreshToken = tokenStorage.getRefreshToken() ?? tokenStorage.getAccessToken();
        if (!currentRefreshToken) return null;
        if (refreshInFlight.current) return refreshInFlight.current;

        const operation = (async () => {
            const { data } = await authService.refresh(currentRefreshToken);
            // Une connexion/déconnexion peut avoir remplacé le jeton pendant
            // l'appel réseau : ne jamais écraser la session plus récente.
            if (tokenStorage.getRefreshToken() !== currentRefreshToken
                && tokenStorage.getAccessToken() !== currentRefreshToken) {
                return tokenStorage.getAccessToken();
            }
            tokenStorage.setAccessToken(data.accessToken, data.expiresIn);
            tokenStorage.setRefreshToken(data.refreshToken, REFRESH_TOKEN_TTL_SECONDS);
            setAccessToken(data.accessToken);
            return data.accessToken;
        })();

        refreshInFlight.current = operation;
        try {
            return await operation;
        } finally {
            if (refreshInFlight.current === operation) refreshInFlight.current = null;
        }
    }, []);

    // Restauration de la session au chargement.
    //
    // Le jeton est décodé pour vérifier son expiration, mais l'identité
    // affichée vient TOUJOURS de `GET /api/auth/me` : le contenu du jeton
    // n'est pas une autorité (cf. `TokenManager`), il peut être périmé
    // côté droits alors que le compte, lui, a changé.
    useEffect(() => {
        let cancelled = false;

        const restoreSession = async () => {
            let token = tokenStorage.getAccessToken();
            if (!token || isTokenExpired(token)) {
                try {
                    token = await refreshAccessToken();
                } catch (cause) {
                    // Une panne transitoire n'invalide pas le jeton de
                    // renouvellement : le contrôle de visibilité réessaiera.
                    if (isTransientApiFailure(cause)) {
                        if (!cancelled) setIsLoading(false);
                        return;
                    }
                    token = null;
                }
            }

            if (!token) {
                tokenStorage.clearAll();
                if (!cancelled) setAccessToken(null);
                if (!cancelled) setIsLoading(false);
                return;
            }

            if (!cancelled) setAccessToken(token);

            try {
                const { data } = await authService.me();
                if (!cancelled) {
                    setUser(data);
                    void flushPendingClientErrors();
                }
            } catch (cause) {
                // Une panne réseau ou serveur ne prouve pas que le JWT est
                // invalide : on le conserve pour la prochaine restauration.
                if (cause instanceof ApiError && cause.status === 401) {
                    try { window.sessionStorage.setItem(SESSION_REAUTH_NOTICE_KEY, 'expired'); } catch { /* stockage désactivé */ }
                }
                if (!isTransientApiFailure(cause)) {
                    tokenStorage.clearAll();
                    if (!cancelled) setAccessToken(null);
                }
                if (!cancelled) setUser(null);
            } finally {
                if (!cancelled) setIsLoading(false);
            }
        };

        void restoreSession();

        return () => {
            cancelled = true;
        };
    }, [refreshAccessToken]);

    // Fin de session décidée par la couche HTTP (401 sur une requête métier) :
    // on aligne l'état local, sans rejouer `/auth/me`.
    useEffect(() => onSessionExpired(() => {
        setAccessToken(null);
        setUser(null);
        setIsLoading(false);
    }), []);

    // Renouvelle l'accès cinq minutes avant son expiration. Le jeton rotatif
    // permet aussi de reprendre après une longue veille ou une panne réseau.
    useEffect(() => {
        if (!user || !accessToken) return;

        const renewalLeadMs = 5 * 60 * 1000;
        let timer: number | undefined;
        let disposed = false;

        const schedule = (delayMs: number) => {
            if (timer !== undefined) window.clearTimeout(timer);
            timer = window.setTimeout(() => { void renewIfNeeded(); }, Math.max(0, delayMs));
        };

        const renewIfNeeded = async () => {
            if (disposed) return;
            const currentToken = tokenStorage.getAccessToken();
            const currentRefreshToken = tokenStorage.getRefreshToken();
            const expiry = currentToken ? decodeJwtPayload(currentToken)?.exp : undefined;
            if (!currentToken || typeof expiry !== 'number') {
                if (!currentRefreshToken) {
                    try { window.sessionStorage.setItem(SESSION_REAUTH_NOTICE_KEY, 'expired'); } catch { /* stockage désactivé */ }
                    tokenStorage.clearAll();
                    setAccessToken(null);
                    setUser(null);
                    return;
                }
                try {
                    await refreshAccessToken();
                } catch (cause) {
                    if (disposed) return;
                    if (cause instanceof ApiError && cause.status === 401) {
                        try { window.sessionStorage.setItem(SESSION_REAUTH_NOTICE_KEY, 'expired'); } catch { /* stockage désactivé */ }
                        tokenStorage.clearAll();
                        setAccessToken(null);
                        setUser(null);
                    } else if (isTransientApiFailure(cause)) {
                        schedule(30_000);
                    }
                }
                return;
            }

            const remainingMs = expiry * 1000 - Date.now();
            if (remainingMs > renewalLeadMs) {
                schedule(remainingMs - renewalLeadMs);
                return;
            }

            try {
                await refreshAccessToken();
            } catch (cause) {
                if (disposed || tokenStorage.getAccessToken() !== currentToken) return;

                if (cause instanceof ApiError && cause.status === 401) {
                    try { window.sessionStorage.setItem(SESSION_REAUTH_NOTICE_KEY, 'expired'); } catch { /* stockage désactivé */ }
                    tokenStorage.clearAll();
                    setAccessToken(null);
                    setUser(null);
                    return;
                }

                if (!isTransientApiFailure(cause)) {
                    tokenStorage.clearAll();
                    setAccessToken(null);
                    setUser(null);
                    return;
                }

                // La durée de vie du jeton de renouvellement est indépendante
                // du JWT d'accès, donc une API temporairement indisponible ne
                // termine pas la session.
                schedule(30_000);
            }
        };

        const onVisibilityChange = () => {
            if (document.visibilityState === 'visible') void renewIfNeeded();
        };

        void renewIfNeeded();
        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('focus', onVisibilityChange);

        return () => {
            disposed = true;
            if (timer !== undefined) window.clearTimeout(timer);
            document.removeEventListener('visibilitychange', onVisibilityChange);
            window.removeEventListener('focus', onVisibilityChange);
        };
    }, [user?.uuid, accessToken, refreshAccessToken]);

    useEffect(() => {
        if (!user) return;
        let cancelled = false;
        void settingsService.getMySettings().then(async (settings) => {
            // La réponse d'un compte précédent ne doit jamais repeindre une
            // session ouverte ensuite par un autre utilisateur.
            if (cancelled) return;

            const pendingLoginTheme = readPendingLoginTheme();
            if (!pendingLoginTheme) {
                saveUserPreferences(settings);
                return;
            }

            try {
                const updatedSettings = await settingsService.updateMySettings({ theme: pendingLoginTheme });
                if (!cancelled) saveUserPreferences(updatedSettings);
            } catch {
                // Le choix local reste actif si l'enregistrement côté compte échoue.
                if (!cancelled) saveUserPreferences({ ...settings, theme: pendingLoginTheme });
            } finally {
                clearPendingLoginTheme();
            }
        }).catch(() => {
            // Les préférences locales restent actives si l'API est indisponible.
            clearPendingLoginTheme();
        });
        return () => { cancelled = true; };
    }, [user?.uuid]);

    const login = useCallback(async (email: string, password: string) => {
        try {
            const { data } = await authService.login(email, password);

            // `expiresIn` sert de TTL côté client : le jeton disparaît du
            // stockage à l'heure où le serveur le considère expiré, donc il
            // ne peut plus être renvoyé par erreur.
            tokenStorage.setAccessToken(data.accessToken, data.expiresIn);
            tokenStorage.setRefreshToken(data.refreshToken, REFRESH_TOKEN_TTL_SECONDS);
            setAccessToken(data.accessToken);
            setUser(data.user);
            void flushPendingClientErrors();
        } catch (error) {
            // Ne pas détruire une session conservée si l'API est momentanément
            // inaccessible pendant la tentative de connexion.
            if (!isTransientApiFailure(error)) {
                tokenStorage.clearAll();
                setAccessToken(null);
            }
            setUser(null);
            throw error;
        }
    }, []);

    const logout = useCallback(async () => {
        try {
            // La révocation serveur est ce qui rend la déconnexion réelle :
            // sans elle, un jeton intercepté resterait valable jusqu'à son
            // expiration.
            await authService.logout();
        } catch (error) {
            // Une déconnexion ne doit jamais rester bloquée sur le réseau.
            if (import.meta.env.DEV && !(error instanceof ApiError && error.status === 401)) {
                console.warn('[Auth] Déconnexion : la révocation du jeton a échoué.', error);
            }
        } finally {
            tokenStorage.clearAll();
            setAccessToken(null);
            setUser(null);
        }
    }, []);

    const updateUser = useCallback((updates: Partial<SessionUserResponse>) => {
        setUser((current) => current ? { ...current, ...updates } : current);
    }, []);

    const refreshUser = useCallback(async () => {
        const { data } = await authService.me();
        setUser(data);
    }, []);

    const value = useMemo<AuthContextValue>(() => ({
        user,
        isAuthenticated: user !== null,
        isLoading,
        accessToken,
        login,
        logout,
        updateUser,
        refreshUser,
    }), [user, isLoading, accessToken, login, logout, updateUser]);

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth doit être utilisé dans un <AuthProvider>.');
    }
    return context;
}
