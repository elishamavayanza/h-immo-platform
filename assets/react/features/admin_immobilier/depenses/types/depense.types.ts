/**
 * depense.types.ts — Lignes du tableau Dépenses (ADMIN_IMMOBILIER).
 *
 * `ExpenseResponse` ne porte que des UUID (`cityId`, `parcelId`,
 * `buildingId`, `unitId`, `workerId`) : le libellé « Bien concerné » est
 * résolu par jointure client sur le catalogue patrimonial. Aucun champ
 * `status` n'existe côté backend : une dépense enregistrée se corrige par une
 * contre-écriture, pas par un statut mutable — la colonne « statut » de la
 * maquette a donc été supprimée.
 */
export type ExpenseCategoryCode =
    | 'salary'
    | 'tax'
    | 'maintenance'
    | 'utility'
    | 'insurance'
    | 'management_fee'
    | 'supply'
    | 'cleaning'
    | 'security'
    | 'public_service'
    | 'notary_fee'
    | 'other';

export interface DepenseRow {
    id: string;
    /** Date ISO de la dépense. */
    date: string;
    /** Ville d'imputation. */
    city: string;
    categoryCode: string;
    /** Libellé français de la catégorie. */
    category: string;
    /** Bien concerné : « Immeuble · Référence » ou niveau le plus précis. */
    property: string;
    description: string;
    amount: string;
    currency: string;
}

export interface DepensesData {
    rows: DepenseRow[];
    /** Total serveur des dépenses accessibles sur le périmètre. */
    total: number;
    /** Note de troncature si la liste a été bornée à REFERENCE_LIMIT. */
    note: string | null;
}
