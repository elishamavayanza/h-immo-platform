// ============================================================
// assets/react/features/auth/services/resetPasswordService.ts
// Description typée de `POST /api/auth/reset-password`.
//
// Le jeton vient UNIQUEMENT du lien de l'email (`?token=...`), jamais
// saisi par l'utilisateur : il transite dans le corps de la requête mais
// n'est ni affiché ni journalisé. Les échecs (jeton absent, expiré, déjà
// utilisé, mot de passe trop court) remontent en `ApiError`.
// ============================================================

import { apiClient } from '../../../../services/api/client';
import type { Feedback } from '../../../../services/api/api.types';

/** Corps attendu par `App\Dto\Request\Auth\ResetPasswordRequest`. */
export interface ResetPasswordRequest {
    token: string;
    newPassword: string;
}

export const resetPasswordService = {
    submit(token: string, newPassword: string) {
        const request: ResetPasswordRequest = { token, newPassword };
        return apiClient.post<Feedback<null>>('/auth/reset-password', request);
    },
};