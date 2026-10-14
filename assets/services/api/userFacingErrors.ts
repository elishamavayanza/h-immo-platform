import type { RequestConfig } from './api.types';

/** Messages sûrs à présenter à l’utilisateur, sans fuite de détails serveur. */
export function getHttpErrorMessage(status: number, config: RequestConfig, serverMessage: string): string {
    if (status === 401) {
        return config.url.startsWith('/auth/login')
            // Le backend ne renvoie ici qu'un message générique ou un motif
            // de suspension confirmé après vérification du mot de passe.
            ? (serverMessage || 'Adresse e-mail ou mot de passe incorrect.')
            : 'Votre session a expiré. Connectez-vous à nouveau pour continuer.';
    }
    if (status === 403) return 'Vous n’avez pas l’autorisation d’effectuer cette action.';
    if (status === 404) return 'La ressource demandée est introuvable ou n’est plus disponible.';
    if (status === 408) return 'Le serveur met trop de temps à répondre. Réessayez dans un instant.';
    if (status === 429) return 'Trop de demandes ont été envoyées. Patientez un instant avant de réessayer.';
    if (status >= 500) {
        return status === 503
            ? 'Le service est momentanément indisponible. Réessayez dans quelques instants.'
            : 'Une erreur interne est survenue. Vos données n’ont pas pu être traitées. Réessayez dans un instant.';
    }
    return serverMessage || 'Une erreur est survenue. Vérifiez les informations puis réessayez.';
}

export function getNetworkErrorMessage(): string {
    return typeof navigator !== 'undefined' && navigator.onLine === false
        ? 'Vous êtes hors connexion. Vérifiez votre accès à Internet puis réessayez.'
        : 'Le serveur ne répond pas. Votre connexion semble instable ; vérifiez-la puis réessayez.';
}

export function getTimeoutErrorMessage(): string {
    return 'Le serveur met trop de temps à répondre. Vérifiez votre connexion puis réessayez.';
}
