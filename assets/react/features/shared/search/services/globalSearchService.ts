import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';
import type { BuildingItem, CityItem, ListPage, ParcelItem, TenantItem, UnitItem } from '../../../admin_immobilier/shared/types/reference.types';
import { buildPatrimoinePath } from '../../../admin_immobilier/patrimoine/components/patrimoineRouting';
import type { GlobalSearchResult } from '../types/globalSearch.types';

export interface GlobalSearchResponse {
    items: GlobalSearchResult[];
    partial: boolean;
}

function normalize(value: string): string {
    return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('fr').trim();
}

function uniqueById<T extends { id: string }>(items: T[]): T[] {
    return [...new Map(items.map((item) => [item.id, item])).values()];
}

/**
 * La recherche réutilise les listes métier existantes : leur autorisation,
 * leur filtre d'organisation et le périmètre UserCity restent la référence.
 * Les termes sont cherchés séparément pour tolérer les noms composés, puis
 * les résultats sont réunis, filtrés et classés localement.
 */
export async function searchOrganization(organizationUuid: string, query: string, signal?: AbortSignal): Promise<GlobalSearchResponse> {
    // Deux mots suffisent pour chercher une identité composée sans multiplier
    // les appels réseau à chaque frappe.
    const terms = [...new Set(query.split(/\s+/).map((term) => term.trim()).filter((term) => term.length >= 2))].slice(0, 2);
    if (terms.length === 0) return { items: [], partial: false };

    let partial = false;
    const groupResults = await Promise.allSettled(terms.map(async (term) => {
        const params = { organizationId: organizationUuid, search: term, page: 1, limit: 5 };
        const paths = ['/v1/property/cities', '/v1/parcels', '/v1/property/buildings', '/v1/units', '/v1/tenants'] as const;
        const settled = await Promise.allSettled(paths.map((path) => apiClient.get<Feedback<ListPage<CityItem | ParcelItem | BuildingItem | UnitItem | TenantItem>>>(path, { params, signal })));
        if (signal?.aborted) throw new DOMException('Recherche annulée', 'AbortError');

        const successful = settled.flatMap((result) => result.status === 'fulfilled' ? [result.value.data.data.items] : []);
        if (successful.length === 0) {
            const firstFailure = settled.find((result) => result.status === 'rejected');
            throw firstFailure?.status === 'rejected' ? firstFailure.reason : new Error('Recherche indisponible');
        }
        if (successful.length !== paths.length) partial = true;

        return {
            cities: successful.flatMap((items) => items.filter((item): item is CityItem => 'code' in item)),
            parcels: successful.flatMap((items) => items.filter((item): item is ParcelItem => 'cityId' in item && 'reference' in item && 'name' in item && 'address' in item)),
            buildings: successful.flatMap((items) => items.filter((item): item is BuildingItem => 'parcelId' in item && 'numberOfFloors' in item)),
            units: successful.flatMap((items) => items.filter((item): item is UnitItem => 'buildingId' in item && 'operationalStatus' in item)),
            tenants: successful.flatMap((items) => items.filter((item): item is TenantItem => 'organizationId' in item && 'phone' in item)),
        };
    }));
    const groups = groupResults.flatMap((result) => result.status === 'fulfilled' ? [result.value] : []);
    if (groups.length === 0) {
        const firstFailure = groupResults.find((result) => result.status === 'rejected');
        throw firstFailure?.status === 'rejected' ? firstFailure.reason : new Error('Recherche indisponible');
    }
    if (groups.length !== groupResults.length) partial = true;

    const cities = uniqueById(groups.flatMap((group) => group.cities));
    const parcels = uniqueById(groups.flatMap((group) => group.parcels));
    const buildings = uniqueById(groups.flatMap((group) => group.buildings));
    const units = uniqueById(groups.flatMap((group) => group.units));
    const tenants = uniqueById(groups.flatMap((group) => group.tenants));
    const cityById = new Map(cities.map((city) => [city.id, city]));
    const parcelById = new Map(parcels.map((parcel) => [parcel.id, parcel]));
    const buildingById = new Map(buildings.map((building) => [building.id, building]));

    const linkedBuildings = await Promise.allSettled(uniqueById(units)
        .map((unit) => unit.buildingId)
        .filter((id) => !buildingById.has(id))
        .map((id) => apiClient.get<Feedback<BuildingItem>>(`/v1/property/buildings/${id}`, { signal })));
    if (signal?.aborted) throw new DOMException('Recherche annulée', 'AbortError');
    linkedBuildings.forEach((result) => {
        if (result.status === 'fulfilled') buildingById.set(result.value.data.data.id, result.value.data.data);
        else partial = true;
    });

    const linkedParcels = await Promise.allSettled([...buildingById.values()]
        .map((building) => building.parcelId)
        .filter((id) => !parcelById.has(id))
        .map((id) => apiClient.get<Feedback<ParcelItem>>(`/v1/parcels/${id}`, { signal })));
    if (signal?.aborted) throw new DOMException('Recherche annulée', 'AbortError');
    linkedParcels.forEach((result) => {
        if (result.status === 'fulfilled') parcelById.set(result.value.data.data.id, result.value.data.data);
        else partial = true;
    });

    const neededCityIds = uniqueById([
        ...parcels,
        ...[...buildingById.values()].flatMap((building) => parcelById.get(building.parcelId) ?? []),
    ]).map((parcel) => parcel.cityId).filter((id) => !cityById.has(id));
    const linkedCities = await Promise.allSettled(neededCityIds.map((id) => apiClient.get<Feedback<CityItem>>(`/v1/property/cities/${id}`, { signal })));
    if (signal?.aborted) throw new DOMException('Recherche annulée', 'AbortError');
    linkedCities.forEach((result) => {
        if (result.status === 'fulfilled') cityById.set(result.value.data.data.id, result.value.data.data);
        else partial = true;
    });

    const cityForParcel = (parcelId: string) => cityById.get(parcelById.get(parcelId)?.cityId ?? '');
    const parcelForBuilding = (buildingId: string) => parcelById.get(buildingById.get(buildingId)?.parcelId ?? '');
    const results: GlobalSearchResult[] = [
        ...cities.map((city) => ({ id: city.id, type: 'Ville' as const, label: city.name, detail: city.code, path: buildPatrimoinePath('parcelles', { cityId: city.id, parcelId: null, buildingId: null }) })),
        ...parcels.map((parcel) => ({
            id: parcel.id,
            type: 'Parcelle' as const,
            label: parcel.name,
            detail: [cityById.get(parcel.cityId)?.name, parcel.reference, parcel.address].filter(Boolean).join(' · '),
            path: buildPatrimoinePath('batiments', { cityId: cityForParcel(parcel.id)?.id ?? parcel.cityId, parcelId: parcel.id, buildingId: null }),
        })),
        ...buildings.map((building) => {
            const parcel = parcelForBuilding(building.id);
            return {
                id: building.id,
                type: 'Bâtiment' as const,
                label: building.name,
                detail: [cityForParcel(building.parcelId)?.name, parcel?.name, building.reference].filter(Boolean).join(' · '),
                path: buildPatrimoinePath('unites', { cityId: cityForParcel(building.parcelId)?.id ?? parcel?.cityId ?? null, parcelId: building.parcelId, buildingId: building.id }),
            };
        }),
        ...units.map((unit) => {
            const building = buildingById.get(unit.buildingId);
            const parcel = building ? parcelForBuilding(building.id) : undefined;
            const city = building ? cityForParcel(building.parcelId) : undefined;
            return {
                id: unit.id,
                type: 'Unité' as const,
                label: unit.reference,
                detail: [city?.name, parcel?.name, building?.name].filter(Boolean).join(' · '),
                path: buildPatrimoinePath('unites', { cityId: city?.id ?? null, parcelId: building?.parcelId ?? null, buildingId: unit.buildingId, unitId: unit.id }),
            };
        }),
        ...tenants.map((tenant) => {
            const name = tenant.fullName || tenant.companyName || 'Locataire';
            const searchableFields = [tenant.fullName, tenant.companyName, tenant.phone, tenant.email].filter((value): value is string => Boolean(value));
            const target = searchableFields.find((field) => terms.every((term) => normalize(field).includes(normalize(term)))) ?? name;
            return {
                id: tenant.id,
                type: 'Locataire' as const,
                label: name,
                detail: [tenant.phone, tenant.email].filter(Boolean).join(' · '),
                path: `/app/location/locataires?search=${encodeURIComponent(target)}`,
            };
        }),
    ];

    const tokens = terms.map(normalize);
    const matching = results.filter((item) => {
        const haystack = normalize(`${item.label} ${item.detail} ${item.type}`);
        return tokens.every((term) => haystack.includes(term));
    });
    const score = (item: GlobalSearchResult) => {
        const label = normalize(item.label);
        const term = normalize(query);
        if (label === term) return 0;
        if (label.startsWith(term)) return 1;
        if (tokens.every((token) => label.includes(token))) return 2;
        return 3;
    };

    return { items: matching.sort((left, right) => score(left) - score(right) || left.label.localeCompare(right.label, 'fr')).slice(0, 15), partial };
}
