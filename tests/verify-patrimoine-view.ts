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
    formatPeriod,
    formatShortDate,
    leaseStatusLabel,
    nextUnpaidRent,
    occupancyIndex,
    parcelCoordinates,
    parcelOccupancy,
    parcelsOfCity,
    pickActiveLease,
    relatedModuleUrl,
    rentStatusLabel,
    summarizeOccupancy,
    summarizeRents,
    unitCount,
    unitsOfBuilding,
    unitTypeLabel,
} from '../assets/react/features/admin_immobilier/patrimoine/components/patrimoineView.ts';
import { buildPatrimoinePath, resolvePatrimoineSelection } from '../assets/react/features/admin_immobilier/patrimoine/components/patrimoineRouting.ts';
import type { PatrimoineData } from '../assets/react/features/admin_immobilier/patrimoine/types/patrimoine.types.ts';
import type { OccupancyItem } from '../assets/react/features/admin_immobilier/reports/types/index.ts';
import type { BuildingItem, CityItem, LeaseItem, ParcelItem, RentItem, UnitItem } from '../assets/react/features/admin_immobilier/shared/types/reference.types.ts';

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

console.log('\n=== Liens filtrés vers les modules liés ===\n');
check('relatedModuleUrl sans contexte -> chemin seul', relatedModuleUrl('/app/depenses', null, null) === '/app/depenses');
check('relatedModuleUrl(parcelle) -> parcelUuid', relatedModuleUrl('/app/location/loyers', 'p1', null) === '/app/location/loyers?parcelUuid=p1');
check('relatedModuleUrl(bâtiment sans parcelle) -> buildingUuid', relatedModuleUrl('/app/depenses', null, 'b1') === '/app/depenses?buildingUuid=b1');
check('relatedModuleUrl(parcelle+bâtiment) -> les deux, dans l’ordre', relatedModuleUrl('/app/depenses', 'p1', 'b1') === '/app/depenses?parcelUuid=p1&buildingUuid=b1');

const lease = (id: string, unitId: string, status: string): LeaseItem => ({
    id, organizationId: 'org', tenantId: `t-${id}`, unitId, reference: `BAIL-${id}`,
    startDate: '2026-01-01T00:00:00Z', endDate: null, depositAmount: null, monthlyRent: '300.00',
    exchangeRate: null, referenceCurrency: null, currency: 'USD', status,
    terminationDate: null, terminationReason: null, notes: null, terms: null,
    createdAt: '2026-01-01T00:00:00Z', updatedAt: '2026-01-01T00:00:00Z',
});
const rent = (id: string, dueDate: string, status: string, isOverdue: boolean): RentItem => ({
    id, leaseId: 'l1', period: dueDate, dueDate, amount: '300.00', currency: 'USD',
    exchangeRate: null, originalAmount: null, originalCurrency: null, status, isOverdue,
    createdAt: dueDate, updatedAt: dueDate,
});

console.log('\n=== Routage : niveau unité et construction d’URL ===\n');
const fullPath = '/app/patrimoine/villes/c1/parcelles/p1/batiments/b1/unites/u1';
const fullSelection = resolvePatrimoineSelection(fullPath, false);
check('resolve(unites/u1) : niveau unites', fullSelection.level === 'unites');
check('resolve(unites/u1) : unitId capté', fullSelection.unitId === 'u1');
check('resolve(unites/u1) : contexte complet', fullSelection.cityId === 'c1' && fullSelection.parcelId === 'p1' && fullSelection.buildingId === 'b1');
check('resolve(batiments/b1/unites) : niveau unites sans unité', resolvePatrimoineSelection('/app/patrimoine/villes/c1/parcelles/p1/batiments/b1/unites', false).unitId === null);
check('resolve(villes/c1) : niveau parcelles', resolvePatrimoineSelection('/app/patrimoine/villes/c1', false).level === 'parcelles');
check('resolve(racine, admin ville) : parcelles', resolvePatrimoineSelection('/app/patrimoine', true).level === 'parcelles');
check('resolve(racine, admin immo) : overview', resolvePatrimoineSelection('/app/patrimoine', false).level === 'overview');

check('build(unites + unitId) : chemin unité complet', buildPatrimoinePath('unites', { cityId: 'c1', parcelId: 'p1', buildingId: 'b1', unitId: 'u1' }) === fullPath);
check('build(unites sans unitId) : chemin bâtiment', buildPatrimoinePath('unites', { cityId: 'c1', parcelId: 'p1', buildingId: 'b1' }) === '/app/patrimoine/villes/c1/parcelles/p1/batiments/b1/unites');
check('build(overview) : racine', buildPatrimoinePath('overview', { cityId: null, parcelId: null, buildingId: null }) === '/app/patrimoine');
check('aller-retour build -> resolve conserve l’unité', resolvePatrimoineSelection(buildPatrimoinePath('unites', { cityId: 'c1', parcelId: 'p1', buildingId: 'b1', unitId: 'u1' }), false).unitId === 'u1');
check('resolve(build(unites sans unité)).unitId reste null', resolvePatrimoineSelection(buildPatrimoinePath('unites', { cityId: 'c1', parcelId: 'p1', buildingId: 'b1' }), false).unitId === null);

console.log('\n=== Location : bail actif, échéances, libellés ===\n');
check('pickActiveLease retient le bail actif', pickActiveLease([lease('l0', 'u1', 'expired'), lease('l1', 'u1', 'active')])?.id === 'l1');
check('pickActiveLease sans actif -> null', pickActiveLease([lease('l0', 'u1', 'expired')]) === null);
check('pickActiveLease([]) -> null', pickActiveLease([]) === null);

const rents = [rent('r1', '2026-01-05T00:00:00Z', 'paid', false), rent('r2', '2026-02-05T00:00:00Z', 'pending', true), rent('r3', '2026-03-05T00:00:00Z', 'partially_paid', false)];
const summary = summarizeRents(rents);
check('summarizeRents : total', summary.total === 3);
check('summarizeRents : payées', summary.paid === 1);
check('summarizeRents : en retard (isOverdue)', summary.overdue === 1);
check('summarizeRents : en attente', summary.unpaid === 1);
check('nextUnpaidRent : la plus ancienne non soldée', nextUnpaidRent(rents)?.id === 'r2');
check('nextUnpaidRent(tout payé) -> null', nextUnpaidRent([rent('r1', '2026-01-05T00:00:00Z', 'paid', false)]) === null);

check('leaseStatusLabel(active) -> Actif', leaseStatusLabel('active') === 'Actif');
check('leaseStatusLabel(inconnu) -> passthrough', leaseStatusLabel('weird') === 'weird');
check('rentStatusLabel(partially_paid) -> libellé français', rentStatusLabel('partially_paid') === 'Partiellement payé');
check('formatShortDate(null) -> tiret', formatShortDate(null) === '—');
check('formatShortDate(invalide) -> tiret', formatShortDate('pas-une-date') === '—');
check('formatPeriod(null) -> tiret', formatPeriod(null) === '—');
check('formatShortDate(2026-03-05 midi) contient l’année', formatShortDate('2026-03-05T12:00:00Z').includes('2026'));
check('formatShortDate(minuit UTC) reste le 05 mars 2026 (pas de décalage)', formatShortDate('2026-03-05T00:00:00Z') === '05 mars 2026');
check('formatPeriod(1er mars UTC) -> mars 2026', formatPeriod('2026-03-01T00:00:00Z') === 'mars 2026');

console.log('\n' + '-'.repeat(60) + '\n');

if (failures.length > 0) {
    console.log(`ECHEC : ${failures.length} / ${checks} contrôles en échec`);

    for (const failure of failures) {
        console.log(`  - ${failure}`);
    }

    process.exit(1);
}

console.log(`SUCCES : ${checks} contrôles passés`);
