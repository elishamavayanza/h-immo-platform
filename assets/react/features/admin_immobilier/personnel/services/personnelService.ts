/**
 * personnelService — Page Personnel branchée sur l'API réelle.
 *
 * Lecture : `workers` (périmètre multi-organisation serveur) joint côté client
 * aux affectations (`worker-assignments`) et aux villes pour composer la
 * fonction et la ville courantes — `WorkerResponse` ne les expose pas.
 *
 * Écriture : Worker CRUD (`POST/PUT /v1/workers`), Assignment CRUD
 * (`POST/PUT /v1/worker-assignments`, `POST /v1/worker-assignments/{uuid}/end`).
 */
import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';
import { REFERENCE_LIMIT, fetchBuildings, fetchCities, fetchParcels, fetchUnits, fetchWorkerAssignments, fetchWorkers } from '../../shared/services/referenceService';
import type { CityItem, WorkerAssignmentItem, WorkerItem } from '../../shared/types/reference.types';
import type { PersonnelData, PersonnelRow } from '../types/personnel.types';

const ROLE_LABEL: Record<string, string> = {
    gerant: 'Gérant',
    sentinelle: 'Sentinelle',
    menager: 'Ménager',
    gardien: 'Gardien',
    agent_entretien: 'Agent d\'entretien',
    agent_administratif: 'Agent administratif',
    autre: 'Autre',
};

export function roleLabel(role: string): string {
    return ROLE_LABEL[role] ?? role;
}

export async function fetchPersonnel(organizationUuid: string): Promise<PersonnelData> {
    const [workers, assignments, cities, parcels, buildings, units] = await Promise.all([
        fetchWorkers(organizationUuid),
        fetchWorkerAssignments(),
        fetchCities(organizationUuid),
        fetchParcels(organizationUuid),
        fetchBuildings(organizationUuid),
        fetchUnits(organizationUuid),
    ]);

    const cityById = new Map(cities.items.map((city) => [city.id, city]));
    const buildingById = new Map(buildings.items.map((building) => [building.id, building]));

    const assignmentsByWorker = new Map<string, typeof assignments.items>();
    for (const assignment of assignments.items) {
        const current = assignmentsByWorker.get(assignment.workerId) ?? [];
        current.push(assignment);
        assignmentsByWorker.set(assignment.workerId, current);
    }

    const rows: PersonnelRow[] = workers.items.map((worker) => {
        const workerAssignments = assignmentsByWorker.get(worker.id) ?? [];
        const latest = workerAssignments[0];

        return {
            id: worker.id,
            name: worker.fullName,
            role: latest ? roleLabel(latest.role) : '—',
            city: latest ? (cityById.get(latest.cityId)?.name ?? '—') : '—',
            assignments: workerAssignments.length,
            parcelId: latest?.parcelId ?? null,
            buildingId: latest?.buildingId ?? null,
            parcelIds: [...new Set(workerAssignments.flatMap((assignment) => assignment.parcelId ? [assignment.parcelId] : assignment.buildingId && buildingById.has(assignment.buildingId) ? [buildingById.get(assignment.buildingId)!.parcelId] : []))],
            buildingIds: [...new Set(workerAssignments.flatMap((assignment) => assignment.buildingId ? [assignment.buildingId] : []))],
            phone: worker.phone,
            email: worker.email,
        };
    });

    const truncated = workers.total > workers.items.length;
    const note = truncated
        ? `Liste partielle : ${workers.total} membres au total, les ${REFERENCE_LIMIT} premiers sont affichés.`
        : null;

    return { rows, cities: cities.items, parcels: parcels.items, buildings: buildings.items, units: units.items, total: workers.total, note };
}

export const WORKER_ROLE_OPTIONS = Object.entries(ROLE_LABEL).map(([value, label]) => ({ value, label }));

export const ASSIGNMENT_ROLE_OPTIONS = WORKER_ROLE_OPTIONS;

export const CURRENCY_OPTIONS = [
    { value: 'USD', label: 'USD' },
    { value: 'CDF', label: 'CDF' },
];

/** Payload pour créer un travailleur. */
export interface CreateWorkerPayload {
    organizationUuid: string;
    fullName: string;
    phone: string;
    email?: string;
    nationalId?: string;
    address?: string;
    notes?: string;
}

/** `POST /api/v1/workers` — créer un travailleur. */
export async function createWorker(payload: CreateWorkerPayload): Promise<WorkerItem> {
    const { data } = await apiClient.post<Feedback<WorkerItem>>('/v1/workers', payload);
    return data.data;
}

/** Payload pour modifier un travailleur. */
export interface UpdateWorkerPayload {
    fullName: string;
    phone: string;
    email?: string;
    nationalId?: string;
    address?: string;
    notes?: string;
}

/** `PUT /api/v1/workers/{uuid}` — modifier un travailleur. */
export async function updateWorker(workerUuid: string, payload: UpdateWorkerPayload): Promise<WorkerItem> {
    const { data } = await apiClient.put<Feedback<WorkerItem>>(`/v1/workers/${workerUuid}`, payload);
    return data.data;
}

/** Payload pour créer une affectation. */
export interface CreateAssignmentPayload {
    workerUuid: string;
    cityUuid: string;
    role: string;
    monthlySalary: string;
    currency: 'USD' | 'CDF';
    startDate: string;
    endDate?: string;
    parcelUuid?: string;
    buildingUuid?: string;
    unitUuid?: string;
    notes?: string;
}

/** `POST /api/v1/worker-assignments` — créer une affectation. */
export async function createAssignment(payload: CreateAssignmentPayload): Promise<WorkerAssignmentItem> {
    const { data } = await apiClient.post<Feedback<WorkerAssignmentItem>>('/v1/worker-assignments', payload);
    return data.data;
}

/** Payload pour modifier une affectation. */
export interface UpdateAssignmentPayload {
    role: string;
    monthlySalary: string;
    currency: 'USD' | 'CDF';
    startDate: string;
    endDate?: string;
    parcelUuid?: string;
    buildingUuid?: string;
    unitUuid?: string;
    notes?: string;
}

/** `PUT /api/v1/worker-assignments/{uuid}` — modifier une affectation. */
export async function updateAssignment(assignmentUuid: string, payload: UpdateAssignmentPayload): Promise<WorkerAssignmentItem> {
    const { data } = await apiClient.put<Feedback<WorkerAssignmentItem>>(`/v1/worker-assignments/${assignmentUuid}`, payload);
    return data.data;
}

/** `POST /api/v1/worker-assignments/{uuid}/end` — terminer une affectation. */
export async function endAssignment(assignmentUuid: string, endDate: string): Promise<string> {
    const { data } = await apiClient.post<Feedback<unknown>>(`/v1/worker-assignments/${assignmentUuid}/end`, { endDate });
    return data.flushDescription ?? 'Affectation terminée.';
}
