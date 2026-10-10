/**
 * personnelService — Page Personnel branchée sur l'API réelle.
 *
 * Lecture : `workers` (périmètre multi-organisation serveur) joint côté client
 * aux affectations (`worker-assignments`) et aux villes pour composer la
 * fonction et la ville courantes — `WorkerResponse` ne les expose pas.
 */
import { REFERENCE_LIMIT, fetchCities, fetchWorkerAssignments, fetchWorkers } from '../../shared/services/referenceService';
import type { PersonnelData, PersonnelRow } from '../types/personnel.types';

const ROLE_LABEL: Record<string, string> = {
    gerant: 'Gérant',
    sentinelle: 'Sentinelle',
    menager: 'Ménager',
    gardien: 'Gardien',
    agent_entretien: 'Agent d’entretien',
    agent_administratif: 'Agent administratif',
    autre: 'Autre',
};

function roleLabel(role: string): string {
    return ROLE_LABEL[role] ?? role;
}

export async function fetchPersonnel(organizationUuid: string): Promise<PersonnelData> {
    const [workers, assignments, cities] = await Promise.all([
        fetchWorkers(organizationUuid),
        fetchWorkerAssignments(),
        fetchCities(organizationUuid),
    ]);

    const cityById = new Map(cities.items.map((city) => [city.id, city]));

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
            phone: worker.phone,
            email: worker.email,
        };
    });

    const truncated = workers.total > workers.items.length;
    const note = truncated
        ? `Liste partielle : ${workers.total} membres au total, les ${REFERENCE_LIMIT} premiers sont affichés.`
        : null;

    return { rows, total: workers.total, note };
}
