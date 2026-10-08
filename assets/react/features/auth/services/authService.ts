// ============================================================
// upload/react/features/auth/services/authService.ts
// Description typée des endpoints d'authentification.
//
// Ce module est un contrat HTTP, PAS une autorité de session : le
// stockage du jeton et la restauration de l'identité restent centralisés
// dans `AuthProvider`, seul consommateur de ces appels. Un composant qui
// accède à `useAuth()` n'a jamais à connaître les routes ici.
// ============================================================

import { apiClient } from '../../../../services/api/client';
import type {
    LoginRequest,
    LoginResponse,
    LogoutResponse,
    Feedback,
    PlatformRole,
    SessionUserResponse,
} from '../../../../services/api/api.types';

export interface UserUpdateResponse {
    id: string;
    email: string;
    fullName: string;
    phone: string | null;
    profilePhoto: string | null;
    platformRole: PlatformRole | null;
    isActive: boolean;
    lastLoginAt: string | null;
}

export const authService = {
    /** `POST /api/auth/login` — firewall `json_login`, 401 générique, 429 throttling. */
    login(email: string, password: string) {
        const request: LoginRequest = { email, password };
        return apiClient.post<LoginResponse>('/auth/login', request);
    },

    /** `GET /api/auth/me` — identité re-lue en base, sans enveloppe `Feedback`. */
    me() {
        return apiClient.get<SessionUserResponse>('/auth/me');
    },

    /** `POST /api/auth/logout` — révoque le `jti` du jeton côté serveur. */
    logout() {
        return apiClient.post<LogoutResponse>('/auth/logout');
    },

    /**
     * `PUT /api/v1/identity/users/{uuid}` — met à jour le profil de l'utilisateur courant.
     * Accepte : firstName, lastName, phone, profilePhoto (URL), email (si autorisé).
     */
    async updateProfile(uuid: string, payload: Partial<{
        firstName: string;
        lastName: string;
        phone: string;
        profilePhoto: string;
        email: string;
    }>): Promise<Feedback<UserUpdateResponse>> {
        const { data } = await apiClient.put<Feedback<UserUpdateResponse>>(`/v1/identity/users/${uuid}`, payload);
        return data;
    },
};
