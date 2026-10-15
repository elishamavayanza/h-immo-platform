import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

import { onSessionExpired, SESSION_REAUTH_NOTICE_KEY } from '../../../services/api/interceptors';
import { ApiError, type SessionUserResponse } from '../../../services/api/api.types';
import { authService } from '../../features/auth/services/authService';
import { tokenStorage } from '../../../services/storage/storage.service';
import { isTokenExpired } from '../../../services/security/security.utils';
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

    // Restauration de la session au chargement.
    //
    // Le jeton est décodé pour vérifier son expiration, mais l'identité
    // affichée vient TOUJOURS de `GET /api/auth/me` : le contenu du jeton
    // n'est pas une autorité (cf. `TokenManager`), il peut être périmé
    // côté droits alors que le compte, lui, a changé.
    useEffect(() => {
        let cancelled = false;

        const restoreSession = async () => {
            const token = tokenStorage.getAccessToken();

            if (!token || isTokenExpired(token)) {
                tokenStorage.clearAll();
                if (!cancelled) setIsLoading(false);
                return;
            }

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
                if (!isTransientApiFailure(cause)) tokenStorage.clearAll();
                if (!cancelled) setUser(null);
            } finally {
                if (!cancelled) setIsLoading(false);
            }
        };

        void restoreSession();

        return () => {
            cancelled = true;
        };
    }, []);

    // Fin de session décidée par la couche HTTP (401 sur une requête métier) :
    // on aligne l'état local, sans rejouer `/auth/me`.
    useEffect(() => onSessionExpired(() => {
        setUser(null);
        setIsLoading(false);
    }), []);

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
            setUser(data.user);
            void flushPendingClientErrors();
        } catch (error) {
            // Ne pas détruire une session conservée si l'API est momentanément
            // inaccessible pendant la tentative de connexion.
            if (!isTransientApiFailure(error)) tokenStorage.clearAll();
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
        accessToken: tokenStorage.getAccessToken(),
        login,
        logout,
        updateUser,
        refreshUser,
    }), [user, isLoading, login, logout, updateUser]);

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth doit être utilisé dans un <AuthProvider>.');
    }
    return context;
}
