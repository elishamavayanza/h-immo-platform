import type { AdminVilleDashboardData } from '../types/adminVilleDashboard.types';
export function buildAdminVilleDashboard(city: string): AdminVilleDashboardData {
    return {
        city,
        metrics: [
            { id: 'units', label: 'Unités dans ma ville', value: '42', detail: 'sur 18 bâtiments', tone: 'primary' },
            { id: 'tenants', label: 'Locataires actifs', value: '36', detail: 'dans votre périmètre', tone: 'info' },
            { id: 'occupancy', label: 'Taux d’occupation', value: '85,7 %', detail: '6 unités disponibles', tone: 'success' },
            { id: 'late', label: 'Loyers à suivre', value: '5', detail: '2 échéances en retard', tone: 'warning' },
        ],
        occupancy: '85,7 %',
        tasks: [
            { id: 't1', title: 'Échéance à vérifier', detail: 'Patrick Nsimba · HZN-08 · 450 $', date: 'Aujourd’hui', status: 'warning' },
            { id: 't2', title: 'Visite de contrôle planifiée', detail: 'Résidence Les Palmiers · KIN-204', date: 'Demain', status: 'info' },
            { id: 't3', title: 'Paiement confirmé', detail: 'Marie Ilunga · KIN-204', date: 'Hier', status: 'success' },
        ],
    };
}
