// ============================================================
// assets/react/features/auth/hooks/useForgotPasswordForm.ts
// État et soumission du formulaire « mot de passe oublié ».
//
// Contrairement au login, l'API répond toujours 200 : le champ d'erreur
// serveur ne sert ici qu'aux pannes réseau. Le succès n'est pas « votre
// email existe » mais « si cette adresse existe, un lien a été envoyé »
// (message générique du backend, anti-énumération) : on affiche donc un
// écran neutre de confirmation dans tous les cas.
// ============================================================

import { useCallback, useState, type FormEvent } from 'react';

import { forgotPasswordService } from '../services/forgotPasswordService';
import { ApiError } from '../../../../services/api/api.types';
import type {
    ForgotPasswordFormErrors,
    ForgotPasswordField,
    ForgotPasswordFormState,
    ForgotPasswordFormTouched,
} from '../types/forgotPassword.types';

/** Format email minimal côté client (aussi contrôlé par `Assert\Email`). */
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** Message générique si le backend envoie un `flushDescription` vide. */
export const DEFAULT_FORGOT_RESET_MESSAGE =
    "Si cette adresse existe, un lien de réinitialisation a été envoyé.";

function validate(values: ForgotPasswordFormState['values']): ForgotPasswordFormErrors {
    const errors: ForgotPasswordFormErrors = {};

    const email = values.email.trim();
    if (email === '') {
        errors.email = "L'adresse email est requise.";
    } else if (!EMAIL_PATTERN.test(email)) {
        errors.email = "L'adresse email n'est pas valide.";
    }

    return errors;
}

export const INITIAL_FORGOT_TOUCHED: ForgotPasswordFormTouched = { email: false };

export interface UseForgotPasswordFormReturn extends ForgotPasswordFormState {
    handleChange: (field: ForgotPasswordField, value: string) => void;
    handleBlur: (field: ForgotPasswordField) => void;
    handleSubmit: (event: FormEvent<HTMLFormElement>) => Promise<void>;
}

export function useForgotPasswordForm(): UseForgotPasswordFormReturn {
    const [values, setValues] = useState<ForgotPasswordFormState['values']>({ email: '' });
    const [touched, setTouched] = useState<ForgotPasswordFormTouched>(INITIAL_FORGOT_TOUCHED);
    const [errors, setErrors] = useState<ForgotPasswordFormErrors>({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isDone, setIsDone] = useState(false);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);

    const handleChange = useCallback((field: ForgotPasswordField, value: string) => {
        setValues((previous) => ({ ...previous, [field]: value }));
        setErrors((previous) => (previous[field] ? { ...previous, [field]: undefined } : previous));
        setServerError(null);
    }, []);

    const handleBlur = useCallback((field: ForgotPasswordField) => {
        setTouched((previous) => ({ ...previous, [field]: true }));
    }, []);

    const handleSubmit = useCallback(async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        setTouched({ email: true });
        const nextErrors = validate(values);
        setErrors(nextErrors);

        if (nextErrors.email !== undefined) {
            return;
        }

        setIsSubmitting(true);
        setServerError(null);

        try {
            const { data } = await forgotPasswordService.requestReset(values.email.trim());
            // `flushDescription` porte le message générique anti-énumération.
            setSuccessMessage(data.flushDescription ?? DEFAULT_FORGOT_RESET_MESSAGE);
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
    }, [values]);

    return {
        values,
        errors,
        touched,
        isSubmitting,
        isDone,
        successMessage,
        serverError,
        handleChange,
        handleBlur,
        handleSubmit,
    };
}