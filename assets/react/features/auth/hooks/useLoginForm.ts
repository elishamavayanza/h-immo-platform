// ============================================================
// assets/react/features/auth/hooks/useLoginForm.ts
// État et soumission du formulaire de connexion.
//
// La validation locale n'est qu'un confort d'affichage : les mêmes
// contraintes existent côté API (`Assert`). La soumission passe par
// `AuthProvider.login` et JAMAIS par `authService` en direct — sinon le
// jeton renvoyé ne serait ni stocké ni associé à la session. Le message
// du catch est celui de l'`ApiError` (401 générique, 429 throttling,
// réseau), car l'API ne distingue pas « email inconnu » de « mot de
// passe faux ».
// ============================================================

import { useCallback, useState, type FormEvent } from 'react';

import { useAuth } from '../../../app/providers/AuthProvider';
import { ApiError } from '../../../../services/api/api.types';
import type {
    LoginFormErrors,
    LoginFormField,
    LoginFormState,
    LoginFormTouched,
} from '../types/auth.types';

/** Format email minimal côté client (aussi contrôlé par `Assert\Email`). */
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function validate(values: LoginFormState['values']): LoginFormErrors {
    const errors: LoginFormErrors = {};

    const email = values.email.trim();
    if (email === '') {
        errors.email = "L'adresse email est requise.";
    } else if (!EMAIL_PATTERN.test(email)) {
        errors.email = "L'adresse email n'est pas valide.";
    }

    if (values.password === '') {
        errors.password = 'Le mot de passe est requis.';
    }

    return errors;
}

export const INITIAL_LOGIN_TOUCHED: LoginFormTouched = { email: false, password: false };

export interface UseLoginFormReturn extends LoginFormState {
    handleChange: (field: LoginFormField, value: string) => void;
    handleBlur: (field: LoginFormField) => void;
    handleSubmit: (event: FormEvent<HTMLFormElement>) => Promise<void>;
}

/** @param onSuccess Écran déclenché après un login réussi (navigation). */
export function useLoginForm(onSuccess: () => void): UseLoginFormReturn {
    const { login } = useAuth();

    const [values, setValues] = useState<LoginFormState['values']>({ email: '', password: '' });
    const [touched, setTouched] = useState<LoginFormTouched>(INITIAL_LOGIN_TOUCHED);
    const [errors, setErrors] = useState<LoginFormErrors>({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [serverError, setServerError] = useState<string | null>(null);

    const handleChange = useCallback((field: LoginFormField, value: string) => {
        setValues((previous) => ({ ...previous, [field]: value }));
        // Une frappe nouvelle efface l'erreur de ce champ et le message
        // serveur : on considère qu'il s'agit d'une nouvelle tentative.
        setErrors((previous) => (previous[field] ? { ...previous, [field]: undefined } : previous));
        setServerError(null);
    }, []);

    const handleBlur = useCallback((field: LoginFormField) => {
        setTouched((previous) => ({ ...previous, [field]: true }));
    }, []);

    const handleSubmit = useCallback(async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        setTouched({ email: true, password: true });
        const nextErrors = validate(values);
        setErrors(nextErrors);

        // Les deux erreurs de champ empêchent l'appel réseau : pas la peine
        // de consommer du quota de `login_throttling` pour un formulaire vide.
        if (nextErrors.email !== undefined || nextErrors.password !== undefined) {
            return;
        }

        setIsSubmitting(true);
        setServerError(null);

        try {
            await login(values.email.trim(), values.password);
            onSuccess();
        } catch (error) {
            setServerError(
                error instanceof ApiError
                    ? error.message
                    : 'Impossible de joindre le serveur. Vérifiez votre connexion puis réessayez.',
            );
        } finally {
            setIsSubmitting(false);
        }
    }, [values, login, onSuccess]);

    return {
        values,
        errors,
        touched,
        isSubmitting,
        serverError,
        handleChange,
        handleBlur,
        handleSubmit,
    };
}