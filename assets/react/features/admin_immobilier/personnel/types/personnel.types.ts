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
    parcelId: string | null;
    buildingId: string | null;
    parcelIds: string[];
    buildingIds: string[];
    phone: string;
    email: string | null;
}

export interface PersonnelData {
    rows: PersonnelRow[];
    cities: CityItem[];
    parcels: ParcelItem[];
    buildings: BuildingItem[];
    units: UnitItem[];
    total: number;
    note: string | null;
}
import type { BuildingItem, CityItem, ParcelItem, UnitItem } from '../../shared/types/reference.types';
