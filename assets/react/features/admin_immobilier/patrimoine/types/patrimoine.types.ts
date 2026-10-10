/**
 * patrimoine.types.ts — Lignes du tableau Patrimoine (ADMIN_IMMOBILIER).
 *
 * Les lignes sont assemblées côté client à partir du catalogue
 * (parcelles + immeubles) et du rapport d'occupation, faute d'un endpoint
 * « liste du patrimoine » unique. `units` / `occupancyRate` proviennent du
 * rapport par `levelUuid` : une parcelle sans unité renvoie 0 / `null`,
 * pas un taux fictif.
 */
import type { BuildingItem, CityItem, ParcelItem, UnitItem } from '../../shared/types/reference.types';

export type PatrimoineKind = 'Parcelle' | 'Bâtiment';

export interface PatrimoineRow {
    id: string;
    name: string;
    /** Sous-titre : adresse / références du bien. */
    sublabel: string;
    city: string;
    kind: PatrimoineKind;
    units: number;
    /** 0-100, ou `null` lorsque le bien ne compte aucune unité. */
    occupancyRate: number | null;
}

export interface PatrimoineData {
    rows: PatrimoineRow[];
    /** Villes brutes de l'organisation (gestion du niveau Ville). */
    cities: CityItem[];
    /** Parcelles brutes (gestion du niveau Parcelle). */
    parcels: ParcelItem[];
    /** Immeubles bruts (gestion du niveau Bâtiment). */
    buildings: BuildingItem[];
    /** Unités brutes (gestion du niveau Unité). */
    units: UnitItem[];
    /** Villes présentes dans le jeu chargé (options du filtre). */
    availableCities: string[];
    /** Indicateurs issus du rapport (non paginés). */
    totalUnits: number;
    globalOccupancyRate: number;
    /** Note de troncature si le catalogue a été borné à REFERENCE_LIMIT. */
    note: string | null;
}
