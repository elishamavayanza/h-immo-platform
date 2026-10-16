/**
 * locataire.types.ts — Lignes du tableau Locataires (ADMIN_IMMOBILIER).
 *
 * Miroir du `TenantResponse` enrichi du bail en cours (étape assemblée
 * `tenants × leases`). Il n'y a pas de « solde » ni de « statut » locataire
 * dans le modèle : ces colonnes de maquette n'existent pas côté API et ne
 * sont pas réinventées. `type` reprend l'enum `TenantType`.
 */
import type { LeaseItem, TenantItem } from '../../shared/types/reference.types';

export type TenantKind = 'individual' | 'company';

export interface LocataireRow {
    id: string;
    cityUuid: string | null;
    /** Nom complet ou raison sociale. */
    name: string;
    /** Sous-titre : e-mail · téléphone. */
    sublabel: string;
    type: TenantKind;
    phone: string;
    email: string | null;
    notes: string | null;
    address: string;
    /** Référence de l'unité du bail en cours, `null` si aucun bail actif. */
    unitReference: string | null;
    /** Toutes les unités rattachées aux baux actifs/brouillons visibles. */
    unitReferences: string[];
    buildingIds: string[];
    /** Parcelles correspondantes, pour conserver le filtrage avec plusieurs baux. */
    parcelIds: string[];
    buildingId: string | null;
    parcelId: string | null;
    /** Fin du bail en cours (ISO), `null` si aucun bail actif. */
    leaseEnd: string | null;
    leaseUuid: string | null;
    leaseStatus: string | null;
    latestMovement: TenantItem['latestMovement'];
}

export interface LocatairesData {
    rows: LocataireRow[];
    /** Total serveur des locataires (non tronqué). */
    total: number;
    /** Baux `active` présents dans le jeu chargé. */
    activeLeases: number;
    leases: LeaseItem[];
    /** Note de troncature si une liste a été bornée à REFERENCE_LIMIT. */
    note: string | null;
    units: Array<{ id: string; buildingId: string; cityUuid: string | null; reference: string; monthlyRent: string; currency: string }>;
    buildings: Array<{ id: string; name: string }>;
    cities: Array<{ id: string; name: string }>;
}
