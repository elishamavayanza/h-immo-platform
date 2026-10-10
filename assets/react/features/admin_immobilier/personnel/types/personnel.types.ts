/**
 * personnel.types.ts — Lignes du tableau Personnel (ADMIN_IMMOBILIER).
 *
 * `WorkerResponse` ne porte ni fonction ni ville : ces informations viennent
 * de l'affectation la plus récente (`worker-assignments`, `startDate` DESC).
 * Un travailleur sans affectation s'affiche sans fonction ni ville. La
 * maquette affichait un statut « Actif / Suspendu » sans équivalent backend :
 * la colonne est supprimée.
 */
export interface PersonnelRow {
    /** UUID du travailleur. */
    id: string;
    name: string;
    /** Fonction issue de la dernière affectation, libellé français. */
    role: string;
    /** Ville de la dernière affectation. */
    city: string;
    /** Nombre d'affectations de ce travailleur. */
    assignments: number;
    phone: string;
    email: string | null;
}

export interface PersonnelData {
    rows: PersonnelRow[];
    total: number;
    note: string | null;
}
