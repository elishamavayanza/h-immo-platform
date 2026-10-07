import type { AdminVilleDashboardData } from '../types/adminVilleDashboard.types';
export function buildAdminVilleDashboard(cities: readonly string[]): AdminVilleDashboardData {
    const scope = cities.length > 0 ? cities.join(' · ') : 'Aucune ville attribuée';
    const hasScope = cities.length > 0;
    return {
        city: scope,
        metrics: [
            { id: 'units', label: 'Unités dans mon périmètre', value: hasScope ? '42' : '0', detail: hasScope ? 'Donnée de démonstration' : 'Aucune ville attribuée', tone: 'primary' },
            { id: 'tenants', label: 'Locataires actifs', value: hasScope ? '36' : '0', detail: hasScope ? 'Donnée de démonstration' : 'Aucun périmètre disponible', tone: 'info' },
            { id: 'occupancy', label: 'Taux d’occupation', value: hasScope ? '85,7 %' : '—', detail: hasScope ? 'Donnée de démonstration' : 'Aucun périmètre disponible', tone: 'success' },
            { id: 'late', label: 'Loyers à suivre', value: hasScope ? '5' : '0', detail: hasScope ? 'Donnée de démonstration' : 'Aucune ville attribuée', tone: 'warning' },
        ],
        occupancy: hasScope ? '85,7 %' : '—',
        tasks: hasScope ? [
            { id: 't1', title: 'Échéance à vérifier', detail: `Locataire · ${cities[0]}`, date: 'Aujourd’hui', status: 'warning' },
            { id: 't2', title: 'Visite de contrôle planifiée', detail: `Bien · ${cities[0]}`, date: 'Demain', status: 'info' },
            { id: 't3', title: 'Paiement confirmé', detail: `Échéance · ${cities[0]}`, date: 'Hier', status: 'success' },
        ] : [],
    };
}
