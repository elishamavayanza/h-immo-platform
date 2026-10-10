/**
 * patrimoineService — Page Patrimoine branchée sur l'API réelle.
 *
 * Deux lectures complémentaires, exécutées en parallèle :
 *  - le catalogue (`fetchPropertyCatalog`) : parcelles + immeubles (lignes
 *    du tableau) et villes / unités (libellés et indicateurs) ;
 *  - le rapport ADMIN_IMMOBILIER : occupation par niveau (via `levelUuid`)
 *    et indicateurs globaux, non paginés.
 *
 * Il n'existe pas d'endpoint « liste du patrimoine » unique côté backend :
 * l'assemblage se fait donc ici, une seule fois par chargement. Le rapport
 * sert aussi d'autorité pour `totalUnits` / `globalOccupancyRate`, évitant
 * de recalculer un taux sur un jeu borné.
 */
import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';
import { REFERENCE_LIMIT, fetchPropertyCatalog } from '../../shared/services/referenceService';
import type { BuildingItem, CityItem, ParcelItem, UnitItem } from '../../shared/types/reference.types';
import { fetchAdminImmobilierReport } from '../../reports/services/reportsService';
import type { AdminImmobilierReport, OccupancyItem } from '../../reports/types';
import type { PatrimoineData, PatrimoineRow } from '../types/patrimoine.types';

/** Adresse/quarter d'une parcelle, avec repli sur sa référence. */
function parcelSublabel(parcel: { reference: string; address: string; quarter: string | null }): string {
    return [parcel.quarter, parcel.address].filter(Boolean).join(' · ') || parcel.reference;
}

export async function fetchPatrimoine(organizationUuid: string): Promise<PatrimoineData> {
    const [catalog, report]: [Awaited<ReturnType<typeof fetchPropertyCatalog>>, AdminImmobilierReport] = await Promise.all([
        fetchPropertyCatalog(organizationUuid),
        fetchAdminImmobilierReport(organizationUuid),
    ]);

    const cityById = new Map(catalog.cities.items.map((city) => [city.id, city.name]));
    const parcelById = new Map(catalog.parcels.items.map((parcel) => [parcel.id, parcel]));

    const occupancyByUuid = new Map<string, OccupancyItem>();
    [...report.occupancyByParcel, ...report.occupancyByBuilding].forEach((item) => {
        if (item.levelUuid) occupancyByUuid.set(item.levelUuid, item);
    });

    const rows: PatrimoineRow[] = [];

    catalog.parcels.items.forEach((parcel) => {
        const occupancy = occupancyByUuid.get(parcel.id);
        rows.push({
            id: parcel.id,
            name: parcel.name,
            sublabel: parcelSublabel(parcel),
            city: cityById.get(parcel.cityId) ?? '—',
            kind: 'Parcelle',
            units: occupancy?.totalUnits ?? 0,
            occupancyRate: occupancy && occupancy.totalUnits > 0 ? occupancy.occupancyRate : null,
        });
    });

    catalog.buildings.items.forEach((building) => {
        const parcel = parcelById.get(building.parcelId);
        const occupancy = occupancyByUuid.get(building.id);
        rows.push({
            id: building.id,
            name: building.name,
            sublabel: parcel
                ? [parcel.name, parcel.address].filter(Boolean).join(' · ')
                : building.reference,
            city: parcel ? cityById.get(parcel.cityId) ?? '—' : '—',
            kind: 'Bâtiment',
            units: occupancy?.totalUnits ?? 0,
            occupancyRate: occupancy && occupancy.totalUnits > 0 ? occupancy.occupancyRate : null,
        });
    });

    const truncated = catalog.parcels.total > catalog.parcels.items.length
        || catalog.buildings.total > catalog.buildings.items.length;
    const note = truncated
        ? `Liste partielle : ${catalog.parcels.total} parcelles et ${catalog.buildings.total} immeubles au total, les ${REFERENCE_LIMIT} premières entrées sont affichées (limite de la liste API).`
        : null;

    return {
        rows,
        cities: catalog.cities.items,
        parcels: catalog.parcels.items,
        buildings: catalog.buildings.items,
        units: catalog.units.items,
        availableCities: [...new Set(rows.map((row) => row.city))].filter((city) => city !== '—').sort(),
        totalUnits: report.totalUnits,
        globalOccupancyRate: report.globalOccupancyRate,
        note,
    };
}

/**
 * Payload d'écriture d'une ville (`CityRequest`).
 *
 * `organizationUuid` n'est envoyé qu'à la création (le rattachement d'une
 * ville existante n'est pas modifiable). `status` est toujours transmis :
 * `CityMapper::copyToEntity()` fait `setStatus($dto->status)` sans condition,
 * donc omettre le statut le réinitialiserait à `active`.
 */
export interface CityPayload {
    name: string;
    code: string;
    province: string | null;
    country: string | null;
    status: 'active' | 'inactive';
}

/** `POST /api/v1/property/cities` — 201, ou 409 si le code existe déjà. */
export async function createCity(organizationUuid: string, payload: CityPayload): Promise<CityItem> {
    const { data } = await apiClient.post<Feedback<CityItem>>('/v1/property/cities', {
        organizationUuid,
        ...payload,
    });

    return data.data;
}

/** `PUT /api/v1/property/cities/{uuid}` — 200. */
export async function updateCity(uuid: string, payload: CityPayload): Promise<CityItem> {
    const { data } = await apiClient.put<Feedback<CityItem>>(`/v1/property/cities/${uuid}`, payload);

    return data.data;
}

/** `DELETE /api/v1/property/cities/{uuid}` — suppression logique, 200. */
export async function deleteCity(uuid: string): Promise<void> {
    await apiClient.delete(`/v1/property/cities/${uuid}`);
}

/** Création d'un ADMIN_VILLE rattaché à la ville choisie. */
export async function createCityAdmin(payload: {
    organizationUuid: string;
    cityUuid: string;
    fullName: string;
    email: string;
    phone: string;
}): Promise<void> {
    await apiClient.post('/v1/identity/organization-users/create-admin', {
        organizationUuid: payload.organizationUuid,
        role: 'admin_ville',
        cityUuids: [payload.cityUuid],
        fullName: payload.fullName,
        email: payload.email,
        phone: payload.phone,
    });
}

/**
 * Payload d'écriture d'une parcelle (`ParcelRequest`).
 * `cityUuid` est requis à la création ; à la mise à jour il est renvoyé à
 * l'identique (le backend refuse un changement de ville).
 * `latitude`/`longitude` sont indivisibles : les deux, ou aucune.
 */
export interface ParcelPayload {
    cityUuid: string;
    reference: string;
    name: string;
    address: string;
    area: string;
    titleNumber: string | null;
    quarter: string | null;
    latitude: string | null;
    longitude: string | null;
    description: string | null;
}

/** `POST /api/v1/parcels` — 201, ou refus de référence dupliquée. */
export async function createParcel(payload: ParcelPayload): Promise<ParcelItem> {
    const { data } = await apiClient.post<Feedback<ParcelItem>>('/v1/parcels', payload);

    return data.data;
}

/** `PUT /api/v1/parcels/{uuid}` — 200. */
export async function updateParcel(uuid: string, payload: ParcelPayload): Promise<ParcelItem> {
    const { data } = await apiClient.put<Feedback<ParcelItem>>(`/v1/parcels/${uuid}`, payload);

    return data.data;
}

/** `DELETE /api/v1/parcels/{uuid}` — suppression logique, 200. */
export async function deleteParcel(uuid: string): Promise<void> {
    await apiClient.delete(`/v1/parcels/${uuid}`);
}

/** Payload d'écriture d'un bâtiment (`BuildingRequest`). */
export interface BuildingPayload {
    parcelUuid: string;
    reference: string;
    name: string;
    type: string;
    numberOfFloors: number | null;
    description: string | null;
}

/** `POST /api/v1/property/buildings` — 201. */
export async function createBuilding(payload: BuildingPayload): Promise<BuildingItem> {
    const { data } = await apiClient.post<Feedback<BuildingItem>>('/v1/property/buildings', payload);

    return data.data;
}

/** `PUT /api/v1/property/buildings/{uuid}` — 200. */
export async function updateBuilding(uuid: string, payload: BuildingPayload): Promise<BuildingItem> {
    const { data } = await apiClient.put<Feedback<BuildingItem>>(`/v1/property/buildings/${uuid}`, payload);

    return data.data;
}

/** `DELETE /api/v1/property/buildings/{uuid}` — suppression logique, 200. */
export async function deleteBuilding(uuid: string): Promise<void> {
    await apiClient.delete(`/v1/property/buildings/${uuid}`);
}

/** Payload d'écriture d'une unité locative (`UnitRequest`). */
export interface UnitPayload {
    buildingUuid: string;
    reference: string;
    type: string;
    floor: number;
    surface: string;
    bedrooms: number | null;
    rooms: number | null;
    bathrooms: number | null;
    monthlyRent: string;
    currency: string;
    description: string | null;
}

/** `POST /api/v1/units` — 201. */
export async function createUnit(payload: UnitPayload): Promise<UnitItem> {
    const { data } = await apiClient.post<Feedback<UnitItem>>('/v1/units', payload);

    return data.data;
}

/** `PUT /api/v1/units/{uuid}` — 200. */
export async function updateUnit(uuid: string, payload: UnitPayload): Promise<UnitItem> {
    const { data } = await apiClient.put<Feedback<UnitItem>>(`/v1/units/${uuid}`, payload);

    return data.data;
}

/** `DELETE /api/v1/units/{uuid}` — suppression logique, 200. */
export async function deleteUnit(uuid: string): Promise<void> {
    await apiClient.delete(`/v1/units/${uuid}`);
}

/**
 * `PATCH /api/v1/units/{uuid}/publish` — bascule l'annonce vitrine.
 * Publier une unité occupée est refusé (422) : le message est porté par le
 * `Feedback`.
 */
export async function publishUnit(uuid: string, isPublished: boolean): Promise<UnitItem> {
    const { data } = await apiClient.patch<Feedback<UnitItem>>(`/v1/units/${uuid}/publish`, { isPublished });

    return data.data;
}

/** `POST /api/v1/media/parcels/{uuid}/photos` — ajouter des photos à une parcelle. */
export async function addParcelPhotos(parcelUuid: string, files: File[]): Promise<string> {
    const formData = new FormData();
    files.forEach((file) => formData.append('files', file));
    const { data } = await apiClient.post<Feedback<unknown>>(`/v1/media/parcels/${parcelUuid}/photos`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return data.flushDescription ?? 'Photo(s) ajoutée(s).';
}

/** `DELETE /api/v1/media/parcels/{uuid}/photos/{filename}` — retirer une photo d'une parcelle. */
export async function deleteParcelPhoto(parcelUuid: string, filename: string): Promise<string> {
    const { data } = await apiClient.delete<Feedback<unknown>>(`/v1/media/parcels/${parcelUuid}/photos/${filename}`);
    return data.flushDescription ?? 'Photo retirée.';
}

/** `POST /api/v1/units/{uuid}/photos` — ajouter une photo à une unité. */
export async function addUnitPhoto(unitUuid: string, file: File): Promise<string> {
    const formData = new FormData();
    formData.append('file', file);
    const { data } = await apiClient.post<Feedback<unknown>>(`/v1/units/${unitUuid}/photos`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return data.flushDescription ?? 'Photo ajoutée.';
}

/** `DELETE /api/v1/units/{uuid}/photos/{photoUuid}` — retirer une photo d'une unité. */
export async function removeUnitPhoto(unitUuid: string, photoUuid: string): Promise<string> {
    const { data } = await apiClient.delete<Feedback<unknown>>(`/v1/units/${unitUuid}/photos/${photoUuid}`);
    return data.flushDescription ?? 'Photo retirée.';
}
