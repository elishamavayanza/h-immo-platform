import { ApiError } from '../../../services/api/api.types';

/**
 * actionErrors.ts — Traduction homogène des erreurs d'action (création,
 * modification, transition) partagée par les hooks de l'espace métier.
 *
 * Deux canaux distincts :
 *  - 422 → erreurs par champ, affichées sous le champ du formulaire ;
 *  - tout le reste (403, 404, 409, 500, réseau) → message global en toast.
 * Le message d'échec est émis par le hook, jamais par la page.
 */

/** Message global d'une erreur d'action, pour un toast. */
export function actionErrorMessage(cause: unknown): string {
    return cause instanceof ApiError ? cause.message : 'Une erreur inattendue est survenue. Réessayez.';
}

/** Vrai si l'erreur porte des violations de validation par champ (HTTP 422). */
export function isValidationError(cause: unknown): boolean {
    return cause instanceof ApiError && cause.status === 422;
}

/**
 * Violations indexées par champ. L'API les expose dans
 * `details.violations` (`{ champ: string[] }`) pour une 422, et dans
 * `errors` (`{ champ: message }`) pour une enveloppe `Feedback`. On ne garde
 * que le premier message par champ : `FormField` affiche une chaîne, pas une
 * liste.
 */
export function fieldErrorMap(cause: unknown): Record<string, string> {
    if (!(cause instanceof ApiError)) return {};

    const map: Record<string, string> = {};

    for (const [field, messages] of Object.entries(cause.violations)) {
        if (messages.length > 0) map[field] = messages[0];
    }

    for (const [field, message] of Object.entries(cause.data.errors ?? {})) {
        const key = field === '' ? '_global' : field;
        if (!map[key]) map[key] = message;
    }

    return map;
}
