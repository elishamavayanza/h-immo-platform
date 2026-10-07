import type { PatronDashboardData } from '../types/patronDashboard.types';

const MOCK: PatronDashboardData = {
    metrics: [
        { id: 'properties', label: 'Biens immobiliers', value: '124', detail: 'dans 8 villes', tone: 'primary' },
        { id: 'tenants', label: 'Locataires actifs', value: '96', detail: '4 nouvelles entrées ce mois', tone: 'info' },
        { id: 'occupancy', label: 'Taux d’occupation', value: '87,5 %', detail: '108 unités occupées', tone: 'success' },
        { id: 'unpaid', label: 'Loyers à suivre', value: '12', detail: '3 en retard', tone: 'warning' },
    ],
    occupancy: 87.5,
    rentCollected: '18 450 $',
    activities: [
        { id: 'a1', title: 'Nouveau bail enregistré', detail: 'Kinshasa Immo Group · Unité KIN-204', date: 'Aujourd’hui, 09:42', status: 'success' },
        { id: 'a2', title: 'Paiement reçu', detail: 'Marie Ilunga · Loyer d’octobre', date: 'Aujourd’hui, 08:18', status: 'success' },
        { id: 'a3', title: 'Échéance à suivre', detail: 'Patrick Nsimba · Loyer de septembre', date: 'Hier, 16:30', status: 'warning' },
        { id: 'a4', title: 'Unité ajoutée au patrimoine', detail: 'Lubumbashi Résidences · LUB-A12', date: 'Hier, 11:05', status: 'info' },
    ],
};

export async function fetchPatronDashboard(): Promise<PatronDashboardData> { return MOCK; }
