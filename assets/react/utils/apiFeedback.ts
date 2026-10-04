// ============================================================
// upload/react/utils/apiFeedback.ts
// Helpers de lecture de l'enveloppe `Feedback` (App\Dto\Feedback)
//
// Rappel du contrat : une réponse métier est
//   { status, flush, flushDescription, errors, warnings, data }
// et une erreur HTTP non rattrapée est
//   { status, error, message, details: { violations } }.
// Ces deux formes coexistent : les helpers les traitent toutes les deux.
// ============================================================

import type { Feedback, HttpErrorResponsePayload } from '../../services/api/api.types';

/** Enveloppe métier, telle que sérialisée par `App\Dto\Feedback`. */
export type ApiFeedback<T> = Feedback<T>;

/** Forme d'erreur produite par `ApiExceptionListener` (ex. 422). */
export type ApiHttpError = HttpErrorResponsePayload;

/** Vrai si l'enveloppe porte au moins une erreur applicative. */
export function hasApiErrors<T>(feedback: ApiFeedback<T>): boolean {
    if (feedback.errors && Object.keys(feedback.errors).length > 0) return true;
    return feedback.status >= 400;
}

/**
 * Premier message lisible d'une enveloppe `Feedback`.
 * `flushDescription` est prioritaire : c'est le texte rédigé par le
 * service pour expliquer l'échec, alors que `errors` ne contient que
 * des messages de validation par champ.
 */
export function getApiErrorMessage<T>(feedback: ApiFeedback<T>, fallback = 'Une erreur est survenue.'): string {
    if (feedback.flushDescription) return feedback.flushDescription;
    if (feedback.errors) {
        const first = Object.values(feedback.errors)[0];
        if (first) return first;
    }
    if (feedback.flush) return feedback.flush;
    return fallback;
}

/** Messages de validation par champ, à plat : `{ email: "Ce champ est requis." }`. */
export function getApiFieldErrors<T>(feedback: ApiFeedback<T>): Record<string, string> {
    return { ...(feedback.errors ?? {}) };
}

/**
 * Extrait `data` d'une enveloppe réussie.
 * Lève une `Error` porteuse du message du service plutôt que de renvoyer
 * un `undefined` que l'appelant oublierait de tester.
 */
export function unwrapApiData<T>(feedback: ApiFeedback<T>, fallback = 'Une erreur est survenue.'): T {
    if (hasApiErrors(feedback)) {
        throw new Error(getApiErrorMessage(feedback, fallback));
    }
    return feedback.data;
}

/**
 * Message lisible d'une erreur HTTP (`HttpErrorResponsePayload`).
 * Les violations de validation sont résumées en une seule phrase : le
 * détail par champ reste accessible via `getHttpViolations()`.
 */
export function getHttpErrorMessage(error: ApiHttpError, fallback = 'Une erreur est survenue.'): string {
    if (error.message) return error.message;
    const violations = error.details?.violations;
    if (violations) {
        const first = Object.values(violations)[0];
        if (first?.[0]) return first[0];
    }
    return fallback;
}

/** Violations de validation d'une 422, par champ. */
export function getHttpViolations(error: ApiHttpError): Record<string, string[]> {
    return error.details?.violations ?? {};
}
