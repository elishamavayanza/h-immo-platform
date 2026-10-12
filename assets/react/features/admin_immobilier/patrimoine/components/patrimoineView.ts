import type { BuildingItem, CityItem, LeaseItem, ParcelItem, RentItem, UnitItem } from '../../shared/types/reference.types';
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

/**
 * URL d'un module métier filtré sur un contexte de patrimoine.
 *
 * Les pages Dépenses, Loyers, Locataires et Personnel ne lisent que
 * `parcelUuid` et `buildingUuid` en query string ; tout autre paramètre serait
 * ignoré en silence. La fonction ne construit donc qu'une URL, jamais un filtre
 * serveur : c'est l'appelé qui reste autoritaire sur le périmètre.
 */
export function relatedModuleUrl(path: string, parcelId: string | null, buildingId: string | null): string {
    const params = new URLSearchParams();
    if (parcelId) params.set('parcelUuid', parcelId);
    if (buildingId) params.set('buildingUuid', buildingId);
    const query = params.toString();
    return query ? `${path}?${query}` : path;
}

export const LEASE_STATUS_LABELS: Record<string, string> = {
    draft: 'Brouillon',
    active: 'Actif',
    expired: 'Expiré',
    terminated: 'Résilié',
    cancelled: 'Annulé',
};

export function leaseStatusLabel(status: string): string {
    return LEASE_STATUS_LABELS[status] ?? status;
}

export const RENT_STATUS_LABELS: Record<string, string> = {
    pending: 'En attente',
    partially_paid: 'Partiellement payé',
    paid: 'Payé',
    overdue: 'En retard',
};

export function rentStatusLabel(status: string): string {
    return RENT_STATUS_LABELS[status] ?? status;
}

/** Bail actif d'une unité (« le », il ne peut y en avoir qu'un), sinon `null`. */
export function pickActiveLease(leases: LeaseItem[]): LeaseItem | null {
    return leases.find((lease) => lease.status === 'active') ?? null;
}

export interface RentStatusSummary {
    total: number;
    paid: number;
    unpaid: number;
    overdue: number;
}

/** Décompte des échéances par état, tel que fourni par le backend (aucun recalcul de montant). */
export function summarizeRents(rents: RentItem[]): RentStatusSummary {
    return rents.reduce<RentStatusSummary>((summary, rent) => {
        summary.total += 1;
        if (rent.status === 'paid') summary.paid += 1;
        else if (rent.isOverdue) summary.overdue += 1;
        else summary.unpaid += 1;
        return summary;
    }, { total: 0, paid: 0, unpaid: 0, overdue: 0 });
}

/**
 * Échéance non soldée la plus proche (date d'échéance croissante).
 * `null` si toutes les échéances chargées sont payées — on ne déduit pas
 * « à jour » d'autre chose que des lignes réellement reçues.
 */
export function nextUnpaidRent(rents: RentItem[]): RentItem | null {
    return rents
        .filter((rent) => rent.status !== 'paid')
        .slice()
        .sort((a, b) => a.dueDate.localeCompare(b.dueDate))[0] ?? null;
}

/**
 * Rendu des dates API en **UTC**.
 *
 * Les colonnes `DATE`/`DATE_IMMUTABLE` sont sérialisées à minuit UTC : les
 * formater dans le fuseau local d'un utilisateur à l'ouest de Greenwich
 * décale le jour (voire le mois) d'une unité. On formate donc en UTC, comme
 * `loyersService` le fait déjà pour les périodes d'échéance.
 */
const SHORT_DATE_FORMATTER = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'long', year: 'numeric', timeZone: 'UTC' });
const PERIOD_FORMATTER = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric', timeZone: 'UTC' });

/** Date ISO → libellé court français (ex. « 05 mars 2026 ») ; `—` si absente/invalide. */
export function formatShortDate(value: string | null | undefined): string {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : SHORT_DATE_FORMATTER.format(date);
}

/** Période d'une échéance → « mars 2026 » ; `—` si absente/invalide. */
export function formatPeriod(value: string | null | undefined): string {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : PERIOD_FORMATTER.format(date);
}
