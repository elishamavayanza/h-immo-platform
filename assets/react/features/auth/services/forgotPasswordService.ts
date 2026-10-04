// ============================================================
// upload/react/features/auth/services/forgotPasswordService.ts
// Description typée de `POST /api/auth/forgot-password`.
//
// Le backend répond TOUJOURS 200 avec le même `Feedback` générique, que
// l'email existe ou non : la réponse ne distingue donc jamais les deux
// cas (ce message est celui d'un `PasswordResetToken` jamais créé). Le
// client affiche `flushDescription` sans chercher à savoir si un compte
// existe.
// ============================================================

import { apiClient } from '../../../../services/api/client';
import type { Feedback } from '../../../../services/api/api.types';

/** Corps attendu par `App\Dto\Request\Auth\ForgotPasswordRequest`. */
export interface ForgotPasswordRequest {
    email: string;
}

export const forgotPasswordService = {
    requestReset(email: string) {
        const request: ForgotPasswordRequest = { email };
        return apiClient.post<Feedback<null>>('/auth/forgot-password', request);
    },
};
