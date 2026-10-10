/**
 * vitrine.types.ts — Annonces vitrine (ADMIN_IMMOBILIER).
 *
 * Une « annonce » est une `Unit` dont `isPublished` indique la présence sur le
 * site public : il n'existe pas d'entité « annonce » distincte côté API. La
 * maquette affichait des « vues » et un statut « À compléter » sans équivalent
 * backend : ces colonnes sont supprimées plutôt que simulées.
 */
export interface VitrineRow {
    /** UUID de l'unité, clé de la bascule de publication. */
    id: string;
    /** Référence de l'unité. */
    reference: string;
    /** « Immeuble · Référence ». */
    title: string;
    city: string;
    /** Libellé français du type d'unité. */
    type: string;
    rent: string;
    currency: string;
    isPublished: boolean;
    /** Un bail actif rend la publication impossible (refus 422 côté serveur). */
    isOccupied: boolean;
}

export interface VitrineData {
    rows: VitrineRow[];
    total: number;
    note: string | null;
}
