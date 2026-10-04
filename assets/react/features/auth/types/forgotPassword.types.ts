// ============================================================
// upload/react/features/auth/types/forgotPassword.types.ts
// Types du formulaire « mot de passe oublié ».
//
// La réponse API est un `Feedback` toujours 200 : le message générique
// porté par `flushDescription` est le seul affichable, qu'il existe ou
// non un compte avec cet email (anti-énumération côté backend). Le
// formulaire ne prétend donc jamais qu'un email « existe ».
// ============================================================

/** Champ unique du formulaire. */
export interface ForgotPasswordFormValues {
    email: string;
}

export type ForgotPasswordField = keyof ForgotPasswordFormValues;

export type ForgotPasswordFormErrors = Partial<Record<ForgotPasswordField, string>>;

export type ForgotPasswordFormTouched = Record<ForgotPasswordField, boolean>;

/**
 * État complet exposé par `useForgotPasswordForm`.
 *
 * Une fois la demande acceptée (`isDone`), le formulaire est remplacé par
 * l'écran de confirmation : il n'y a plus rien à saisir.
 */
export interface ForgotPasswordFormState {
    values: ForgotPasswordFormValues;
    errors: ForgotPasswordFormErrors;
    touched: ForgotPasswordFormTouched;
    isSubmitting: boolean;
    isDone: boolean;
    successMessage: string | null;
    serverError: string | null;
}
