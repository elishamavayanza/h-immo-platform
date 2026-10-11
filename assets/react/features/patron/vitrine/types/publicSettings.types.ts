/**
 * publicSettings.types.ts — Réglages publics de la vitrine (espace PATRON).
 *
 * Miroir des champs publics de `OrganizationResponse` exposés par
 * `GET /api/v1/identity/organizations/{uuid}` et modifiables via
 * `PATCH /api/v1/identity/organizations/{uuid}/showcase`
 * (`OrganizationShowcaseRequest`). Le PATRON est le seul rôle habilité à
 * modifier la présentation publique de son organisation.
 */

/** Paramètres publics affichés et éditables par le Patron. */
export interface OrganizationPublicSettings {
    /** UUID public de l'organisation. */
    uuid: string;
    /** Nom de l'organisation. */
    name: string;
    /** Identifiant de la page publique (slug), `null` si jamais défini. */
    slug: string | null;
    /** Texte de présentation publique, `null` si jamais défini. */
    publicDescription: string | null;
    /** La vitrine est-elle listée sur le site public. */
    isPubliclyListed: boolean;
    /** Chemin/URL du logo courant, `null` si aucun. */
    logo: string | null;
}

/**
 * Corps de `PATCH …/showcase`. Un champ absent n'est PAS modifié : pour
 * effacer la description on envoie `""`. Le slug ne peut pas être effacé
 * (le backend n'accepte ni `""` ni `null` pour le vider) : on n'envoie donc
 * `slug` que lorsqu'il change réellement.
 */
export interface ShowcasePayload {
    slug?: string;
    publicDescription?: string;
    isPubliclyListed?: boolean;
}
