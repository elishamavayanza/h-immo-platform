// ============================================================
// upload/react/features/auth/types/auth.types.ts
// Types du formulaire de connexion.
//
// Le contrat réseau (`LoginResponse`, `SessionUserResponse`,
// `Feedback`) vit dans `upload/services/api/api.types.ts` : seuls les
// types que le formulaire manipule sont définis ici. Le message d'erreur
// affiché vient de l'`ApiError` (401 générique du firewall, 429 du
// throttling) — le client n'invente jamais la cause.
// ============================================================

/** Valeurs des deux champs du formulaire. */
export interface LoginFormValues {
    email: string;
    password: string;
}

export type LoginFormField = keyof LoginFormValues;

/** Erreurs de validation locales, indexées par champ. */
export type LoginFormErrors = Partial<Record<LoginFormField, string>>;

/** Champs déjà « touchés » (l'erreur n'apparaît qu'après un blur). */
export type LoginFormTouched = Record<LoginFormField, boolean>;

/**
 * État complet exposé par `useLoginForm`.
 *
 * `serverError` est distinct des erreurs de champ : c'est la réponse de
 * l'API (identifiants refusés, quota dépassé, réseau), affichée dans une
 * alerte plutôt que sous un champ.
 */
export interface LoginFormState {
    values: LoginFormValues;
    errors: LoginFormErrors;
    touched: LoginFormTouched;
    isSubmitting: boolean;
    serverError: string | null;
}
