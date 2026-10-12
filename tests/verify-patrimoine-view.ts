/**
 * Vérifie les helpers de présentation du module Patrimoine
 * (`patrimoine/components/patrimoineView.ts`) : filtrage par niveau, agrégation
 * d'occupation d'une ville et lecture du rapport.
 *
 * Module pur (ni React ni JSX, imports de types uniquement), exécutable par
 * Node (type stripping) :
 *
 *   node tests/verify-patrimoine-view.ts
 *
 * Aucune base de données : les données sont des objets en mémoire qui
 * reproduisent la forme réelle de `PatrimoineData`. Ce test garde les
 * indicateurs des vues de niveau (ville / parcelle / bâtiment) : une ville
 * dont l'occupation est la somme de ses parcelles, une parcelle sans unité qui
 * ne reçoit pas de taux fictif, l'autorité de `totalUnits` du rapport sur le
 * comptage du catalogue chargé.
 */

import {
    buildingOccupancy,
    buildingsOfCity,
    buildingsOfParcel,
    buildingTypeLabel,
    cityOccupancy,
    occupancyIndex,
    parcelCoordinates,
    parcelOccupancy,
    parcelsOfCity,
    summarizeOccupancy,
    unitCount,
    unitsOfBuilding,
    unitTypeLabel,
} from '../assets/react/features/admin_immobilier/patrimoine/components/patrimoineView.ts';
import type { PatrimoineData } from '../assets/react/features/admin_immobilier/patrimoine/types/patrimoine.types.ts';
import type { OccupancyItem } from '../assets/react/features/admin_immobilier/reports/types/index.ts';
import type { BuildingItem, CityItem, ParcelItem, UnitItem } from '../assets/react/features/admin_immobilier/shared/types/reference.types.ts';

let checks = 0;
const failures: string[] = [];

function check(label: string, ok: boolean, detail = ''): void {
    checks += 1;

    if (ok) {
        console.log(`  [OK]   ${label}`);

        return;
    }

    failures.push(label + (detail !== '' ? ` (${detail})` : ''));
    console.log(`  [FAIL] ${label}${detail !== '' ? ` -> ${detail}` : ''}`);
}

const city = (id: string, name: string): CityItem => ({ id, organizationId: 'org', name, code: id.toUpperCase(), province: null, country: null, status: 'active' });
const parcel = (id: string, cityId: string, extra: Partial<ParcelItem> = {}): ParcelItem => ({ id, cityId, reference: `REF-${id}`, titleNumber: null, name: `Parcelle ${id}`, address: '12 av. du Port', quarter: null, area: '100.00', latitude: null, longitude: null, description: null, ...extra });
const building = (id: string, parcelId: string, extra: Partial<BuildingItem> = {}): BuildingItem => ({ id, parcelId, reference: `BAT-${id}`, name: `Bâtiment ${id}`, type: 'apartment', numberOfFloors: null, description: null, photos: [], ...extra });
const unit = (id: string, buildingId: string, extra: Partial<UnitItem> = {}): UnitItem => ({ id, buildingId, reference: `U-${id}`, type: 'apartment', floor: 0, surface: '50.00', bedrooms: null, rooms: null, bathrooms: null, monthlyRent: '250.00', currency: 'USD', description: null, isPublished: false, ...extra });
const occ = (level: OccupancyItem['level'], levelUuid: string | null, totalUnits: number, occupiedUnits: number, availableUnits: number, occupancyRate: number): OccupancyItem => ({ level, levelUuid, label: levelUuid ?? level, totalUnits, occupiedUnits, availableUnits, occupancyRate });

const data: PatrimoineData = {
    rows: [],
    cities: [city('c1', 'Alpha'), city('c2', 'Beta')],
    parcels: [parcel('p1', 'c1'), parcel('p2', 'c1'), parcel('p3', 'c2')],
    buildings: [building('b1', 'p1'), building('b2', 'p1'), building('b3', 'p2'), building('b4', 'p3')],
    units: [unit('u1', 'b1'), unit('u2', 'b1'), unit('u3', 'b2'), unit('u4', 'b4')],
    occupancyByParcel: [occ('parcel', 'p1', 3, 2, 1, 66.67), occ('parcel', 'p2', 1, 1, 0, 100)],
    occupancyByBuilding: [occ('building', 'b1', 2, 2, 0, 100), occ('building', 'b3', 1, 0, 1, 0)],
    availableCities: ['Alpha', 'Beta'],
    totalUnits: 4,
    globalOccupancyRate: 75,
    note: null,
};

console.log('\n=== Filtrage par niveau ===\n');
check('parcelsOfCity(c1) renvoie les 2 parcelles de la ville', parcelsOfCity(data, 'c1').map((p) => p.id).join(',') === 'p1,p2');
check('parcelsOfCity(null) ne renvoie rien', parcelsOfCity(data, null).length === 0);
check('buildingsOfParcel(p1) renvoie b1 et b2', buildingsOfParcel(data, 'p1').map((b) => b.id).join(',') === 'b1,b2');
check('buildingsOfCity(c1) résout les immeubles via les parcelles (b1,b2,b3)', buildingsOfCity(data, 'c1').map((b) => b.id).join(',') === 'b1,b2,b3');
check('unitsOfBuilding(b1) renvoie u1 et u2', unitsOfBuilding(data, 'b1').map((u) => u.id).join(',') === 'u1,u2');
check('buildingsOfParcel(null) ne renvoie rien', buildingsOfParcel(data, null).length === 0);

console.log('\n=== Occupation depuis le rapport ===\n');
check('occupancyIndex ignore les lignes sans levelUuid', occupancyIndex([occ('parcel', 'p1', 1, 1, 0, 100), occ('parcel', null, 9, 9, 0, 100)]).size === 1);
check('parcelOccupancy(p1) lit le rapport', parcelOccupancy(data, 'p1')?.totalUnits === 3);
check('parcelOccupancy inconnue -> null (pas de taux fictif)', parcelOccupancy(data, 'p3') === null);
check('parcelOccupancy(null) -> null', parcelOccupancy(data, null) === null);
check('buildingOccupancy(b1) lit le rapport', buildingOccupancy(data, 'b1')?.occupiedUnits === 2);
check('buildingOccupancy inconnu -> null', buildingOccupancy(data, 'b2') === null);

console.log('\n=== Agrégation par ville ===\n');
const alpha = cityOccupancy(data, 'c1');
check('cityOccupancy(c1) totalise ses parcelles (3+1 unités)', alpha?.totalUnits === 4, String(alpha?.totalUnits));
check('cityOccupancy(c1) totalise les occupées (2+1)', alpha?.occupiedUnits === 3, String(alpha?.occupiedUnits));
check('cityOccupancy(c1) taux = 75 %', alpha?.occupancyRate === 75, String(alpha?.occupancyRate));
check('cityOccupancy sans parcelle -> null', cityOccupancy(data, 'c2') === null);
check('cityOccupancy(cityId=null) -> null', cityOccupancy(data, null) === null);
check('cityOccupancy(ville inconnue) -> null', cityOccupancy(data, 'zzz') === null);

console.log('\n=== Comptage d’unités : rapport prioritaire, catalogue en repli ===\n');
check('unitCount(cumul rapport, catalogue) suit le rapport', unitCount(buildingOccupancy(data, 'b1'), unitsOfBuilding(data, 'b1')) === 2);
check('unitCount(null, [u1]) retombe sur le catalogue', unitCount(null, [unit('u1', 'b1')]) === 1);
check('unitCount(null, []) vaut 0', unitCount(null, []) === 0);

console.log('\n=== Coordonnées et libellés ===\n');
check('parcelCoordinates(paire valide) renvoie les nombres', JSON.stringify(parcelCoordinates(parcel('pc', 'c1', { latitude: '-1.68', longitude: '29.22' }))) === JSON.stringify({ latitude: -1.68, longitude: 29.22 }));
check('parcelCoordinates(hors plage) -> null', parcelCoordinates(parcel('pb', 'c1', { latitude: '200', longitude: '29.22' })) === null);
check('parcelCoordinates(absent) -> null', parcelCoordinates(parcel('pn', 'c1')) === null);
check('buildingTypeLabel(connu) -> libellé français', buildingTypeLabel('apartment') === 'Immeuble à appartements');
check('buildingTypeLabel(inconnu) -> passthrough', buildingTypeLabel('weird') === 'weird');
check('unitTypeLabel(shop) -> Magasin', unitTypeLabel('shop') === 'Magasin');

console.log('\n=== summarizeOccupancy ===\n');
const empty = summarizeOccupancy([]);
check('summarizeOccupancy([]) : total 0', empty.totalUnits === 0);
check('summarizeOccupancy([]) : taux 0 (pas de division par zéro)', empty.occupancyRate === 0);
const mixed = summarizeOccupancy([occ('parcel', 'p1', 2, 1, 1, 50), occ('parcel', 'p2', 2, 2, 0, 100)]);
check('summarizeOccupancy totalise unités et occupées', mixed.totalUnits === 4 && mixed.occupiedUnits === 3);
check('summarizeOccupancy : taux = 3/4 = 75 %', mixed.occupancyRate === 75);

console.log('\n' + '-'.repeat(60) + '\n');

if (failures.length > 0) {
    console.log(`ECHEC : ${failures.length} / ${checks} contrôles en échec`);

    for (const failure of failures) {
        console.log(`  - ${failure}`);
    }

    process.exit(1);
}

console.log(`SUCCES : ${checks} contrôles passés`);
