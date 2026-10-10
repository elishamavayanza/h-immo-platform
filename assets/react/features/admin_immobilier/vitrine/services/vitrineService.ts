/**
 * vitrineService — Page Vitrine branchée sur l'API réelle.
 *
 * Lecture : `units` joint côté client au catalogue patrimonial (immeuble →
 * parcelle → ville) et aux baux, pour connaître le libellé et l'occupation.
 * Écriture : `PATCH /v1/units/{uuid}/publish` (bascule `isPublished`), refusée
 * en 422 par le backend si un bail actif occupe l'unité.
 *          `PUT /v1/units/{uuid}` (modifier description et autres champs).
 *          `POST /v1/units/{uuid}/photos` (ajouter photo), `DELETE /v1/units/{uuid}/photos/{photoUuid}` (retirer photo).
 */
import { apiClient } from '../../../../../services/api/client';
import { REFERENCE_LIMIT, fetchLeases, fetchPropertyCatalog } from '../../shared/services/referenceService';
import type { Feedback } from '../../../../../services/api/api.types';
import type { UnitItem } from '../../shared/types/reference.types';
import type { VitrineData, VitrineRow } from '../types/vitrine.types';

const UNIT_TYPE_LABEL: Record<string, string> = {
    apartment: 'Appartement',
    house: 'Maison',
    shop: 'Commerce',
    office: 'Bureau',
    restaurant: 'Restaurant',
    other: 'Autre',
};

export function unitTypeLabel(type: string): string {
    return UNIT_TYPE_LABEL[type] ?? type;
}

export async function fetchVitrine(organizationUuid: string): Promise<VitrineData> {
    const [catalog, leases] = await Promise.all([
        fetchPropertyCatalog(organizationUuid),
        fetchLeases(organizationUuid),
    ]);

    const occupiedUnits = new Set(
        leases.items.filter((lease) => lease.status === 'active').map((lease) => lease.unitId),
    );

    const cityById = new Map(catalog.cities.items.map((city) => [city.id, city]));
    const parcelById = new Map(catalog.parcels.items.map((parcel) => [parcel.id, parcel]));
    const buildingById = new Map(catalog.buildings.items.map((building) => [building.id, building]));

    const rows: VitrineRow[] = catalog.units.items.map((unit) => {
        const building = buildingById.get(unit.buildingId);
        const parcel = building ? parcelById.get(building.parcelId) : undefined;
        const city = parcel ? cityById.get(parcel.cityId) : undefined;

        return {
            id: unit.id,
            reference: unit.reference,
            title: [building?.name, unit.reference].filter(Boolean).join(' · ') || unit.reference,
            city: city?.name ?? '—',
            type: unitTypeLabel(unit.type),
            rent: unit.monthlyRent,
            currency: unit.currency,
            isPublished: unit.isPublished,
            isOccupied: occupiedUnits.has(unit.id),
        };
    });

    const truncated = catalog.units.total > catalog.units.items.length;
    const note = truncated
        ? `Liste partielle : ${catalog.units.total} unités au total, les ${REFERENCE_LIMIT} premières sont affichées.`
        : null;

    return { rows, total: catalog.units.total, note };
}

/** Bascule la publication d'une unité ; renvoie le message de l'API. */
export async function setUnitPublished(unitUuid: string, isPublished: boolean): Promise<string> {
    const { data } = await apiClient.patch<Feedback<unknown>>(`/v1/units/${unitUuid}/publish`, { isPublished });

    return data.flushDescription ?? 'État de publication mis à jour.';
}

/** Payload pour modifier une unité (description, etc.). */
export interface UpdateUnitPayload {
    description?: string;
    monthlyRent?: string;
    currency?: 'USD' | 'CDF';
}

/** `PUT /api/v1/units/{uuid}` — modifier une unité. */
export async function updateUnit(unitUuid: string, payload: UpdateUnitPayload): Promise<UnitItem> {
    const { data } = await apiClient.put<Feedback<UnitItem>>(`/v1/units/${unitUuid}`, payload);
    return data.data;
}

/** `POST /api/v1/units/{uuid}/photos` — ajouter une photo à l'unité. */
export async function addUnitPhoto(unitUuid: string, file: File): Promise<string> {
    const formData = new FormData();
    formData.append('file', file);
    const { data } = await apiClient.post<Feedback<unknown>>(`/v1/units/${unitUuid}/photos`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return data.flushDescription ?? 'Photo ajoutée.';
}

/** `DELETE /api/v1/units/{uuid}/photos/{photoUuid}` — retirer une photo de l'unité. */
export async function removeUnitPhoto(unitUuid: string, photoUuid: string): Promise<string> {
    const { data } = await apiClient.delete<Feedback<unknown>>(`/v1/units/${unitUuid}/photos/${photoUuid}`);
    return data.flushDescription ?? 'Photo retirée.';
}
