/**
 * Logique pure du formulaire « réinitialiser mon mot de passe ».
 *
 * Anciennement `upload/react/app/password-form.ts`, migré dans la feature
 * `auth`. Ces règles vivent dans un module sans React ni DOM pour être
 * testables directement (voir `tests/verify-reset-password-form.ts`). Les
 * contraintes de longueur doivent rester alignées sur celles du DTO côté
 * API (`App\Dto\Request\Auth\ResetPasswordRequest`) : le client valide pour
 * donner un retour immédiat, le serveur reste seul juge de la sécurité.
 */

/** Longueur minimale imposée par l'API. */
export const MIN_PASSWORD_LENGTH = 8;

/** Erreurs de validation, par champ. Une chaîne vide signifie « pas d'erreur ». */
export type PasswordFormErrors = {
    password?: string;
    confirmation?: string;
};

export type ValidationResult = {
    isValid: boolean;
    errors: PasswordFormErrors;
};

/**
 * Extrait le jeton du paramètre `token` de l'URL.
 *
 * Le jeton transite en query parameter car c'est ce que le lien de
 * l'email peut transporter ; il n'est jamais affiché ni journalisé.
 */
export function readTokenFromSearch(search: string): string {
    const raw = new URLSearchParams(search).get('token');

    return raw === null ? '' : raw.trim();
}

/**
 * Valide les deux champs saisis.
 *
 * La « blancheur » suit la même définition que le validateur `NotBlank` de
 * l'API : un champ composé uniquement d'espaces est considéré comme vide.
 * Sans cette harmonisation, le client autoriserait l'envoi d'un mot de
 * passe que le serveur refuserait ensuite par une 422.
 *
 * La longueur est en revanche mesurée sur la valeur brute, comme le
 * validateur `Length` du serveur.
 *
 * @param password        nouveau mot de passe
 * @param confirmation    confirmation saisie par l'utilisateur
 */
export function validatePasswordForm(password: string, confirmation: string): ValidationResult {
    const errors: PasswordFormErrors = {};
    const isPasswordBlank = password.trim() === '';
    const isConfirmationBlank = confirmation.trim() === '';

    if (isPasswordBlank) {
        errors.password = 'Veuillez saisir votre nouveau mot de passe.';
    } else if (password.length < MIN_PASSWORD_LENGTH) {
        errors.password = `Le mot de passe doit contenir au moins ${MIN_PASSWORD_LENGTH} caractères.`;
    }

    if (isConfirmationBlank) {
        errors.confirmation = 'Veuillez confirmer votre nouveau mot de passe.';
    } else if (!isPasswordBlank && password !== confirmation) {
        // Le message « ne correspondent pas » n'a de sens que si les deux
        // valeurs existent : sinon l'utilisateur verrait deux messages
        // contradictoires sur un formulaire qu'il n'a pas encore rempli.
        errors.confirmation = 'Les deux mots de passe ne correspondent pas.';
    }

    return { isValid: Object.keys(errors).length === 0, errors };
}
