import type { AdminImmobilierDashboardData } from '../types/adminImmobilierDashboard.types';
const MOCK: AdminImmobilierDashboardData = {
    metrics: [
        { id: 'units', label: 'Unités gérées', value: '238', detail: 'sur 124 biens', tone: 'primary' },
        { id: 'tenants', label: 'Locataires actifs', value: '96', detail: '4 nouvelles entrées', tone: 'info' },
        { id: 'collected', label: 'Loyers encaissés', value: '18 450 $', detail: '74 % des échéances', tone: 'success' },
        { id: 'late', label: 'Retards à traiter', value: '12', detail: '3 dossiers prioritaires', tone: 'warning' },
    ],
    collectionRate: '74 %', openIssues: 12,
    operations: [
        { id: 'o1', title: 'Paiement enregistré', detail: 'Marie Ilunga · KIN-204 · 850 $', time: '09:42', status: 'success' },
        { id: 'o2', title: 'Échéance en retard', detail: 'Patrick Nsimba · HZN-08 · 450 $', time: '08:18', status: 'warning' },
        { id: 'o3', title: 'Nouvelle dépense', detail: 'Les Palmiers · Réparation pompe', time: 'Hier', status: 'info' },
        { id: 'o4', title: 'Affectation mise à jour', detail: 'Michel Beya · Résidence du Lac', time: 'Hier', status: 'success' },
    ],
};
export async function fetchAdminImmobilierDashboard(): Promise<AdminImmobilierDashboardData> { return MOCK; }
