// ============================================================
// upload/react/features/auth/types/resetPassword.types.ts
// Types du formulaire de réinitialisation du mot de passe.
//
// Le jeton ne vient pas du formulaire : il est lu dans l'URL (`?token=`),
// c'est pour cela qu'il n'apparaît pas dans `ResetPasswordFormValues`.
// ============================================================

/** Valeurs des deux champs (le jeton arrive via l'URL, pas ici). */
export interface ResetPasswordFormValues {
    password: string;
    confirmation: string;
}

export type ResetPasswordFormField = keyof ResetPasswordFormValues;

export type ResetPasswordFormErrors = Partial<Record<ResetPasswordFormField, string>>;

export type ResetPasswordFormTouched = Record<ResetPasswordFormField, boolean>;

/**
 * État complet exposé par `useResetPasswordForm`.
 *
 * `serverError` porte les échecs serveur (jeton invalide/expiré → 400/422,
 * réseau) ; `isDone` remplace le formulaire par l'écran de confirmation.
 */
export interface ResetPasswordFormState {
    values: ResetPasswordFormValues;
    errors: ResetPasswordFormErrors;
    touched: ResetPasswordFormTouched;
    isSubmitting: boolean;
    isDone: boolean;
    serverError: string | null;
}
