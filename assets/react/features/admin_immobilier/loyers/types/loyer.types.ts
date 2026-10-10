/**
 * loyer.types.ts — Lignes du tableau Loyers (ADMIN_IMMOBILIER).
 *
 * `RentResponse` ne porte ni locataire ni unité : les libellés sont
 * résolus par jointure client `rent → lease → tenant/unit/building`. Le
 * statut est le statut CALCULÉ backend (`overdue` dérivé, jamais persisté) ;
 * aucune colonne « solde » n'existe, le montant payé n'étant pas exposé dans
 * la réponse de liste.
 */
export type RentStatusCode = 'pending' | 'partially_paid' | 'paid' | 'overdue';

export interface LoyerRow {
    id: string;
    tenant: string;
    /** Immeuble · référence d'unité. */
    unitLabel: string;
    /** Période lisible, ex. « Octobre 2026 ». */
    periodLabel: string;
    /** Échéance ISO. */
    dueDate: string;
    amount: string;
    currency: string;
    status: RentStatusCode;
}

export interface LoyersData {
    rows: LoyerRow[];
    /** Total serveur des échéances correspondant au filtre de statut. */
    total: number;
    /** Note de troncature si la liste a été bornée à REFERENCE_LIMIT. */
    note: string | null;
}
