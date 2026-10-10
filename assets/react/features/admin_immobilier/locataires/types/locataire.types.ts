/**
 * locataire.types.ts — Lignes du tableau Locataires (ADMIN_IMMOBILIER).
 *
 * Miroir du `TenantResponse` enrichi du bail en cours (étape assemblée
 * `tenants × leases`). Il n'y a pas de « solde » ni de « statut » locataire
 * dans le modèle : ces colonnes de maquette n'existent pas côté API et ne
 * sont pas réinventées. `type` reprend l'enum `TenantType`.
 */
export type TenantKind = 'individual' | 'company';

export interface LocataireRow {
    id: string;
    /** Nom complet ou raison sociale. */
    name: string;
    /** Sous-titre : e-mail · téléphone. */
    sublabel: string;
    type: TenantKind;
    phone: string;
    address: string;
    /** Référence de l'unité du bail en cours, `null` si aucun bail actif. */
    unitReference: string | null;
    /** Fin du bail en cours (ISO), `null` si aucun bail actif. */
    leaseEnd: string | null;
}

export interface LocatairesData {
    rows: LocataireRow[];
    /** Total serveur des locataires (non tronqué). */
    total: number;
    /** Baux `active` présents dans le jeu chargé. */
    activeLeases: number;
    /** Note de troncature si une liste a été bornée à REFERENCE_LIMIT. */
    note: string | null;
}
