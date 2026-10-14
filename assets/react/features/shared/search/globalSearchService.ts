import { apiClient } from '../../../../services/api/client';
import type { Feedback } from '../../../../services/api/api.types';
import type { BuildingItem, CityItem, ListPage, ParcelItem, TenantItem, UnitItem } from '../../admin_immobilier/shared/types/reference.types';
import { buildPatrimoinePath } from '../../admin_immobilier/patrimoine/components/patrimoineRouting';

export interface GlobalSearchResult {
    id: string;
    type: 'Ville' | 'Parcelle' | 'Bâtiment' | 'Unité' | 'Locataire';
    label: string;
    detail: string;
    path: string;
}

/**
 * Les listes existantes appliquent toutes les permissions et le périmètre
 * UserCity côté API. La recherche globale compose ces mêmes endpoints :
 * aucune liste complète non filtrée ni nouvelle règle d'autorisation.
 */
export async function searchOrganization(organizationUuid: string, query: string): Promise<GlobalSearchResult[]> {
    const params = { organizationId: organizationUuid, search: query, page: 1, limit: 5 };
    const [citiesResponse, parcelsResponse, buildingsResponse, unitsResponse, tenantsResponse] = await Promise.all([
        apiClient.get<Feedback<ListPage<CityItem>>>('/v1/property/cities', { params }),
        apiClient.get<Feedback<ListPage<ParcelItem>>>('/v1/parcels', { params }),
        apiClient.get<Feedback<ListPage<BuildingItem>>>('/v1/property/buildings', { params }),
        apiClient.get<Feedback<ListPage<UnitItem>>>('/v1/units', { params }),
        apiClient.get<Feedback<ListPage<TenantItem>>>('/v1/tenants', { params }),
    ]);

    const cities = citiesResponse.data.data.items;
    const parcels = parcelsResponse.data.data.items;
    const buildings = buildingsResponse.data.data.items;
    const units = unitsResponse.data.data.items;
    const tenants = tenantsResponse.data.data.items;
    const parcelById = new Map(parcels.map((parcel) => [parcel.id, parcel]));
    const buildingById = new Map(buildings.map((building) => [building.id, building]));
    const cityById = new Map(cities.map((city) => [city.id, city]));

    const unknownBuildingIds = [...new Set(units.map((unit) => unit.buildingId).filter((id) => !buildingById.has(id)))];
    const linkedBuildings = await Promise.all(unknownBuildingIds.map(async (id) => {
        const response = await apiClient.get<Feedback<BuildingItem>>(`/v1/property/buildings/${id}`);
        return response.data.data;
    }));
    linkedBuildings.forEach((building) => buildingById.set(building.id, building));

    const unknownParcelIds = [...new Set([...buildingById.values()].map((building) => building.parcelId).filter((id) => !parcelById.has(id)))];
    const linkedParcels = await Promise.all(unknownParcelIds.map(async (id) => {
        const response = await apiClient.get<Feedback<ParcelItem>>(`/v1/parcels/${id}`);
        return response.data.data;
    }));
    linkedParcels.forEach((parcel) => parcelById.set(parcel.id, parcel));

    const unknownCityIds = [...new Set([...parcelById.values()].map((parcel) => parcel.cityId).filter((id) => !cityById.has(id)))];
    const linkedCities = await Promise.all(unknownCityIds.map(async (id) => {
        const response = await apiClient.get<Feedback<CityItem>>(`/v1/property/cities/${id}`);
        return response.data.data;
    }));
    linkedCities.forEach((city) => cityById.set(city.id, city));

    const cityForParcel = (parcelId: string) => cityById.get(parcelById.get(parcelId)?.cityId ?? '');
    const parcelForBuilding = (buildingId: string) => parcelById.get(buildingById.get(buildingId)?.parcelId ?? '');

    return [
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
            return {
                id: unit.id,
                type: 'Unité' as const,
                label: unit.reference,
                detail: [cityForParcel(building?.parcelId ?? '')?.name, parcel?.name, building?.name].filter(Boolean).join(' · '),
                path: buildPatrimoinePath('unites', { cityId: cityForParcel(building?.parcelId ?? '')?.id ?? null, parcelId: building?.parcelId ?? null, buildingId: unit.buildingId, unitId: unit.id }),
            };
        }),
        ...tenants.map((tenant) => ({
            id: tenant.id,
            type: 'Locataire' as const,
            label: tenant.fullName || tenant.companyName || 'Locataire',
            detail: [tenant.phone, tenant.email].filter(Boolean).join(' · '),
            path: `/app/location/locataires?search=${encodeURIComponent(query)}`,
        })),
    ];
}
