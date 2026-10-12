import type { BuildingItem, CityItem, ParcelItem, UnitItem } from '../../shared/types/reference.types';
import type { OccupancyItem } from '../../reports/types';
import type { PatrimoineData } from '../types/patrimoine.types';

/**
 * patrimoineView.ts — Helpers de présentation du module Patrimoine.
 *
 * Uniquement des fonctions pures et des libellés, sans appel API ni état :
 * les vues de niveau (ville, parcelle, bâtiment) et les formulaires d'édition
 * partagent une seule source de vérité pour les libellés, et les indicateurs
 * contextuels sont dérivés des listes déjà chargées (`PatrimoineData`), jamais
 * recalculés ni inventés.
 *
 * Les libellés et libellés d'options sont volontairement co-localisés ici :
 * définis une seule fois, ils alimentent aussi bien les `<Select>` de
 * formulaire que les cartes de synthèse.
 */

export const BUILDING_TYPE_OPTIONS = [
    { value: 'apartment', label: 'Immeuble à appartements' },
    { value: 'commercial', label: 'Commercial' },
    { value: 'office', label: 'Bureaux' },
    { value: 'restaurant', label: 'Restaurant' },
    { value: 'mixed', label: 'Mixte' },
];

export const BUILDING_TYPE_LABELS: Record<string, string> = Object.fromEntries(
    BUILDING_TYPE_OPTIONS.map((option) => [option.value, option.label]),
);

export const UNIT_TYPE_OPTIONS = [
    { value: 'apartment', label: 'Appartement' },
    { value: 'house', label: 'Maison' },
    { value: 'shop', label: 'Magasin' },
    { value: 'office', label: 'Bureau' },
    { value: 'restaurant', label: 'Restaurant' },
    { value: 'other', label: 'Autre' },
];

export const UNIT_TYPE_LABELS: Record<string, string> = Object.fromEntries(
    UNIT_TYPE_OPTIONS.map((option) => [option.value, option.label]),
);

export const CURRENCY_OPTIONS = [
    { value: 'USD', label: 'USD' },
    { value: 'CDF', label: 'CDF' },
];

export function buildingTypeLabel(type: string): string {
    return BUILDING_TYPE_LABELS[type] ?? type;
}

export function unitTypeLabel(type: string): string {
    return UNIT_TYPE_LABELS[type] ?? type;
}

/**
 * Coordonnées valides d'une parcelle, ou `null`.
 * Une paire indivisible : une seule borne hors plage invalide l'ensemble,
 * un bien sans coordonnées renvoie `null` (jamais `0`).
 */
export function parcelCoordinates(parcel: ParcelItem): { latitude: number; longitude: number } | null {
    if (!parcel.latitude || !parcel.longitude) return null;
    const latitude = Number(parcel.latitude);
    const longitude = Number(parcel.longitude);

    return Number.isFinite(latitude) && Number.isFinite(longitude)
        && latitude >= -90 && latitude <= 90 && longitude >= -180 && longitude <= 180
        ? { latitude, longitude }
        : null;
}

/** Occupations complètes d'un niveau, indexées par uuid de bien. */
export function occupancyIndex(items: OccupancyItem[]): Map<string, OccupancyItem> {
    return new Map(items.filter((item) => item.levelUuid).map((item) => [item.levelUuid as string, item]));
}

/** Agrégat d'occupation (unités totales / occupées / disponibles + taux 0-100). */
export interface OccupancySummary {
    totalUnits: number;
    occupiedUnits: number;
    availableUnits: number;
    occupancyRate: number;
}

/** Agrège plusieurs lignes d'occupation (ex. toutes les parcelles d'une ville). */
export function summarizeOccupancy(items: OccupancyItem[]): OccupancySummary {
    const totalUnits = items.reduce((total, item) => total + item.totalUnits, 0);
    const occupiedUnits = items.reduce((total, item) => total + item.occupiedUnits, 0);
    const availableUnits = items.reduce((total, item) => total + item.availableUnits, 0);

    return {
        totalUnits,
        occupiedUnits,
        availableUnits,
        occupancyRate: totalUnits > 0 ? (occupiedUnits / totalUnits) * 100 : 0,
    };
}

export function cityIndex(data: PatrimoineData | null): Map<string, CityItem> {
    return new Map((data?.cities ?? []).map((city) => [city.id, city]));
}

export function parcelIndex(data: PatrimoineData | null): Map<string, ParcelItem> {
    return new Map((data?.parcels ?? []).map((parcel) => [parcel.id, parcel]));
}

export function buildingIndex(data: PatrimoineData | null): Map<string, BuildingItem> {
    return new Map((data?.buildings ?? []).map((building) => [building.id, building]));
}

export function parcelsOfCity(data: PatrimoineData | null, cityId: string | null): ParcelItem[] {
    if (!cityId) return [];
    return (data?.parcels ?? []).filter((parcel) => parcel.cityId === cityId);
}

export function buildingsOfParcel(data: PatrimoineData | null, parcelId: string | null): BuildingItem[] {
    if (!parcelId) return [];
    return (data?.buildings ?? []).filter((building) => building.parcelId === parcelId);
}

export function unitsOfBuilding(data: PatrimoineData | null, buildingId: string | null): UnitItem[] {
    if (!buildingId) return [];
    return (data?.units ?? []).filter((unit) => unit.buildingId === buildingId);
}

/** Immeubles d'une ville, résolus via leurs parcelles. */
export function buildingsOfCity(data: PatrimoineData | null, cityId: string | null): BuildingItem[] {
    const parcelIds = new Set(parcelsOfCity(data, cityId).map((parcel) => parcel.id));
    return (data?.buildings ?? []).filter((building) => parcelIds.has(building.parcelId));
}

/** Occupation d'une parcelle telle que fournie par le rapport, ou `null`. */
export function parcelOccupancy(data: PatrimoineData | null, parcelId: string | null): OccupancyItem | null {
    if (!parcelId) return null;
    return occupancyIndex(data?.occupancyByParcel ?? []).get(parcelId) ?? null;
}

/** Occupation d'un immeuble telle que fournie par le rapport, ou `null`. */
export function buildingOccupancy(data: PatrimoineData | null, buildingId: string | null): OccupancyItem | null {
    if (!buildingId) return null;
    return occupancyIndex(data?.occupancyByBuilding ?? []).get(buildingId) ?? null;
}

/** Occupation agrégée d'une ville, à partir des parcelles qu'elle contient. */
export function cityOccupancy(data: PatrimoineData | null, cityId: string | null): OccupancySummary | null {
    const parcelIds = new Set(parcelsOfCity(data, cityId).map((parcel) => parcel.id));
    if (parcelIds.size === 0) return null;
    const items = (data?.occupancyByParcel ?? []).filter((item) => item.levelUuid && parcelIds.has(item.levelUuid));
    return items.length > 0 ? summarizeOccupancy(items) : null;
}

/**
 * Nombre d'unités d'un niveau : la valeur du rapport d'occupation est
 * autoritaire (non paginée) ; à défaut on compte le catalogue chargé.
 */
export function unitCount(occupancy: OccupancyItem | null, units: UnitItem[]): number {
    return occupancy ? occupancy.totalUnits : units.length;
}
