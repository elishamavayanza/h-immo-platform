/**
 * depensesService — Page Dépenses branchée sur l'API réelle.
 *
 * Lecture : `expenses` (`expenseDate` DESC, périmètre multi-organisation
 * appliqué côté serveur) joint côté client avec le catalogue patrimonial
 * (villes/parcelles/immeubles/unités) et les travailleurs, `ExpenseResponse`
 * n'exposant que des UUID. Le libellé « Bien concerné » descend au niveau le
 * plus précis renseigné (unité → immeuble → parcelle → ville).
 *
 * Écriture : créer une dépense (`POST /v1/expenses`), modifier une dépense
 * (`PUT /v1/expenses/{uuid}`), annuler une dépense
 * (`POST /v1/expenses/{uuid}/cancel` — contre-écriture).
 */
import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';
import { REFERENCE_LIMIT, fetchExpenses, fetchPropertyCatalog, fetchWorkers } from '../../shared/services/referenceService';
import type { BuildingItem, CityItem, ExpenseItem, ParcelItem, UnitItem, WorkerItem } from '../../shared/types/reference.types';
import type { DepenseRow, DepensesData } from '../types/depense.types';

const CATEGORY_LABEL: Record<string, string> = {
    salary: 'Personnel',
    tax: 'Taxes',
    maintenance: 'Entretien',
    utility: 'Eau et électricité',
    insurance: 'Assurance',
    management_fee: 'Frais de gestion',
    supply: 'Fournitures',
    cleaning: 'Nettoyage',
    security: 'Gardiennage',
    public_service: 'Service public',
    notary_fee: 'Frais notariés',
    other: 'Autre',
};

export function categoryLabel(code: string): string {
    return CATEGORY_LABEL[code] ?? 'Autre';
}

function propertyLabel(
    expense: ExpenseItem,
    cities: Map<string, CityItem>,
    parcels: Map<string, ParcelItem>,
    buildings: Map<string, BuildingItem>,
    units: Map<string, UnitItem>,
): string {
    if (expense.unitId) {
        const unit = units.get(expense.unitId);
        const building = unit ? buildings.get(unit.buildingId) : undefined;
        if (unit) return [building?.name, unit.reference].filter(Boolean).join(' · ');
    }

    if (expense.buildingId) {
        const building = buildings.get(expense.buildingId);
        if (building) return building.name;
    }

    if (expense.parcelId) {
        const parcel = parcels.get(expense.parcelId);
        if (parcel) return parcel.name;
    }

    return cities.get(expense.cityId)?.name ?? '—';
}

function descriptionLabel(expense: ExpenseItem, workers: Map<string, WorkerItem>): string {
    return expense.notes?.trim()
        || expense.supplier?.trim()
        || expense.reference?.trim()
        || (expense.workerId ? workers.get(expense.workerId)?.fullName : undefined)
        || '—';
}

export async function fetchDepenses(organizationUuid: string): Promise<DepensesData> {
    const [expenses, catalog, workers] = await Promise.all([
        fetchExpenses(),
        fetchPropertyCatalog(organizationUuid),
        fetchWorkers(organizationUuid),
    ]);

    const cities = new Map(catalog.cities.items.map((city) => [city.id, city]));
    const parcels = new Map(catalog.parcels.items.map((parcel) => [parcel.id, parcel]));
    const buildings = new Map(catalog.buildings.items.map((building) => [building.id, building]));
    const units = new Map(catalog.units.items.map((unit) => [unit.id, unit]));
    const workerById = new Map(workers.items.map((worker) => [worker.id, worker]));

    const rows: DepenseRow[] = expenses.items.map((expense) => ({
        id: expense.id,
        date: expense.expenseDate,
        city: cities.get(expense.cityId)?.name ?? '—',
        categoryCode: expense.category,
        category: categoryLabel(expense.category),
        property: propertyLabel(expense, cities, parcels, buildings, units),
        parcelId: expense.parcelId ?? (expense.buildingId ? buildings.get(expense.buildingId)?.parcelId ?? null : expense.unitId ? buildings.get(units.get(expense.unitId)?.buildingId ?? '')?.parcelId ?? null : null),
        buildingId: expense.buildingId ?? (expense.unitId ? units.get(expense.unitId)?.buildingId ?? null : null),
        description: descriptionLabel(expense, workerById),
        amount: expense.amount,
        currency: expense.currency,
    }));

    const note = expenses.total > expenses.items.length
        ? `Liste partielle : ${expenses.total} dépenses au total, les ${REFERENCE_LIMIT} plus récentes sont affichées.`
        : null;

    return { rows, cities: catalog.cities.items, total: expenses.total, note };
}

export const CATEGORY_OPTIONS = Object.entries(CATEGORY_LABEL).map(([value, label]) => ({ value, label }));

export const CURRENCY_OPTIONS = [
    { value: 'USD', label: 'USD' },
    { value: 'CDF', label: 'CDF' },
];

export const EXPENSE_METHOD_OPTIONS = [
    { value: 'cash', label: 'Espèces' },
    { value: 'bank_transfer', label: 'Virement bancaire' },
    { value: 'mobile_money', label: 'Mobile Money' },
    { value: 'check', label: 'Chèque' },
    { value: 'other', label: 'Autre' },
];

/** Payload pour créer une dépense. */
export interface CreateExpensePayload {
    cityUuid: string;
    category: string;
    amount: string;
    currency: 'USD' | 'CDF';
    expenseDate: string;
    method?: string;
    supplier?: string;
    reference?: string;
    notes?: string;
    parcelUuid?: string;
    buildingUuid?: string;
    unitUuid?: string;
    workerUuid?: string;
}

/** `POST /api/v1/expenses` — créer une dépense. */
export async function createExpense(payload: CreateExpensePayload): Promise<unknown> {
    const { data } = await apiClient.post<Feedback<unknown>>('/v1/expenses', payload);
    return data.data;
}

/** Payload pour modifier une dépense. */
export interface UpdateExpensePayload {
    category: string;
    amount: string;
    currency: 'USD' | 'CDF';
    expenseDate: string;
    method?: string;
    supplier?: string;
    reference?: string;
    notes?: string;
    parcelUuid?: string;
    buildingUuid?: string;
    unitUuid?: string;
    workerUuid?: string;
}

/** `PUT /api/v1/expenses/{uuid}` — modifier une dépense. */
export async function updateExpense(expenseUuid: string, payload: UpdateExpensePayload): Promise<unknown> {
    const { data } = await apiClient.put<Feedback<unknown>>(`/v1/expenses/${expenseUuid}`, payload);
    return data.data;
}

/** `POST /api/v1/expenses/{uuid}/cancel` — annuler une dépense (contre-écriture). */
export async function cancelExpense(expenseUuid: string, reason: string): Promise<string> {
    const { data } = await apiClient.post<Feedback<unknown>>(`/v1/expenses/${expenseUuid}/cancel`, { reason });
    return data.flushDescription ?? 'La dépense a été annulée.';
}
