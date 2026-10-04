// ============================================================
// upload/react/features/auth/hooks/useResetPasswordForm.ts
// État et soumission du formulaire de réinitialisation.
//
// La validation locale est déléguée au module pur `resetPasswordForm.ts`
// (testé par `tests/verify-reset-password-form.ts`) ; le serveur reste
// seul juge final (jeton valide ?, expiré ?, déjà utilisé ?). La soumission
// passe par `resetPasswordService` : la route est publique dans
// `security.yaml`, aucun jeton d'accès n'est donc requis ici.
// ============================================================

import { useCallback, useState, type FormEvent } from 'react';

import { ApiError } from '../../../../services/api/api.types';
import { validatePasswordForm } from '../resetPasswordForm';
import { resetPasswordService } from '../services/resetPasswordService';
import type {
    ResetPasswordFormErrors,
    ResetPasswordFormField,
    ResetPasswordFormState,
    ResetPasswordFormTouched,
} from '../types/resetPassword.types';

/** Message affiché une fois le mot de passe enregistré côté serveur. */
export const RESET_SUCCESS_MESSAGE =
    'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.';

export const INITIAL_RESET_TOUCHED: ResetPasswordFormTouched = { password: false, confirmation: false };

export interface UseResetPasswordFormReturn extends ResetPasswordFormState {
    handleChange: (field: ResetPasswordFormField, value: string) => void;
    handleBlur: (field: ResetPasswordFormField) => void;
    handleSubmit: (event: FormEvent<HTMLFormElement>) => Promise<void>;
}

/** @param token Jeton lu dans l'URL par la page — le formulaire le transmet tel quel. */
export function useResetPasswordForm(token: string): UseResetPasswordFormReturn {
    const [values, setValues] = useState<ResetPasswordFormState['values']>({ password: '', confirmation: '' });
    const [touched, setTouched] = useState<ResetPasswordFormTouched>(INITIAL_RESET_TOUCHED);
    const [errors, setErrors] = useState<ResetPasswordFormErrors>({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isDone, setIsDone] = useState(false);
    const [serverError, setServerError] = useState<string | null>(null);

    const handleChange = useCallback((field: ResetPasswordFormField, value: string) => {
        setValues((previous) => ({ ...previous, [field]: value }));
        setErrors((previous) => (previous[field] ? { ...previous, [field]: undefined } : previous));
        setServerError(null);
    }, []);

    const handleBlur = useCallback((field: ResetPasswordFormField) => {
        setTouched((previous) => ({ ...previous, [field]: true }));
    }, []);

    const handleSubmit = useCallback(async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        setTouched({ password: true, confirmation: true });
        const result = validatePasswordForm(values.password, values.confirmation);
        setErrors(result.errors);

        if (!result.isValid) {
            return;
        }

        setIsSubmitting(true);
        setServerError(null);

        try {
            await resetPasswordService.submit(token, values.password);
            setIsDone(true);
        } catch (error) {
            setServerError(
                error instanceof ApiError
                    ? error.message
                    : 'Impossible de joindre le serveur. Vérifiez votre connexion puis réessayez.',
            );
        } finally {
            setIsSubmitting(false);
        }
    }, [values, token]);

    return {
        values,
        errors,
        touched,
        isSubmitting,
        isDone,
        serverError,
        handleChange,
        handleBlur,
        handleSubmit,
    };
}
