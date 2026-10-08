import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

import { onSessionExpired } from '../../../services/api/interceptors';
import { ApiError, type SessionUserResponse } from '../../../services/api/api.types';
import { authService } from '../../features/auth/services/authService';
import { tokenStorage } from '../../../services/storage/storage.service';
import { isTokenExpired } from '../../../services/security/security.utils';

interface AuthContextValue {
    user: SessionUserResponse | null;
    isAuthenticated: boolean;
    isLoading: boolean;
    accessToken: string | null;
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
                if (!cancelled) setUser(data);
            } catch {
                // 401 ou réseau : le jeton n'est plus exploitable. Il est
                // purgé (le 401 l'a déjà fait via `authErrorInterceptor`).
                tokenStorage.clearAll();
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

    const login = useCallback(async (email: string, password: string) => {
        try {
            const { data } = await authService.login(email, password);

            // `expiresIn` sert de TTL côté client : le jeton disparaît du
            // stockage à l'heure où le serveur le considère expiré, donc il
            // ne peut plus être renvoyé par erreur.
            tokenStorage.setAccessToken(data.accessToken, data.expiresIn);
            setUser(data.user);
        } catch (error) {
            tokenStorage.clearAll();
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

    const value = useMemo<AuthContextValue>(() => ({
        user,
        isAuthenticated: user !== null,
        isLoading,
        accessToken: tokenStorage.getAccessToken(),
        login,
        logout,
    }), [user, isLoading, login, logout]);

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth doit être utilisé dans un <AuthProvider>.');
    }
    return context;
}
