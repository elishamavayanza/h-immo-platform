// ============================================================
// assets/react/utils/authUtils.ts
// Lecture du jeton pour l'affichage uniquement.
//
// Rappel : décoder un JWT côté client ne prouve rien. Le contenu du
// jeton sert à choisir le bon sélecteur d'Organization ou à pré-remplir
// un écran ; l'autorisation, elle, est recalculée par `SecurityService`
// à chaque requête. Un utilisateur qui altère son stockage local ne
// s'octroie aucun accès.
//
// ⚠️ L'identifiant d'une Organization se lit dans
// `organizations[].uuid` (jamais un id numérique interne), et le rôle se
// lit dans `organizations[].role`.
// ============================================================

import { tokenStorage } from '../../services/storage/storage.service';
import { decodeJwtPayload, type JwtPayload, type JwtOrganizationMembership } from '../../services/security/security.utils';

/** Payload décodé, ou `null` si aucun jeton n'est stocké ou illisible. */
function getPayload(): JwtPayload | null {
    const token = tokenStorage.getAccessToken();
    if (!token) return null;
    return decodeJwtPayload(token);
}

/**
 * UUID de la première Organization dont le compte est membre.
 *
 * À défaut de meilleure source, c'est un simple raccourci d'affichage :
 * un compte membre de plusieurs Organizations doit laisser le choix à
 * l'utilisateur plutôt que d'être tacitement rattaché à la première. Pour
 * un choix explicite, lire la liste via `GET /api/auth/me` et conserver
 * l'UUID sélectionné.
 */
export function getOrganizationIdFromToken(): string | null {
    return getPayload()?.organizations[0]?.uuid ?? null;
}

/** Liste complète des Organizations du compte, avec son rôle dans chacune. */
export function getOrganizationsFromToken(): JwtOrganizationMembership[] {
    return getPayload()?.organizations ?? [];
}

/** UUID public de l'utilisateur (`sub`). */
export function getCurrentUserIdFromToken(): string | null {
    const payload = getPayload();
    return typeof payload?.sub === 'string' && payload.sub !== '' ? payload.sub : null;
}

/**
 * Rôle du compte dans une Organization donnée, ou `null` s'il n'en est
 * pas membre. Ne remplace pas une vérification serveur : un `PATRON`
 * affiché côté client ne donne pas le moindre droit supplémentaire.
 */
export function getRoleForOrganization(organizationUuid: string): JwtOrganizationMembership['role'] | null {
    const membership = getOrganizationsFromToken().find((org) => org.uuid === organizationUuid);
    return membership?.role ?? null;
}

/** Vrai si le compte est `SUPER_ADMIN` au niveau plateforme. */
export function isSuperAdmin(): boolean {
    return getPayload()?.platformRole === 'super_admin';
}
