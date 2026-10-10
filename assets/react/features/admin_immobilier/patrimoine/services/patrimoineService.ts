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
import { REFERENCE_LIMIT, fetchPropertyCatalog } from '../../shared/services/referenceService';
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
        availableCities: [...new Set(rows.map((row) => row.city))].filter((city) => city !== '—').sort(),
        totalUnits: report.totalUnits,
        globalOccupancyRate: report.globalOccupancyRate,
        note,
    };
}
