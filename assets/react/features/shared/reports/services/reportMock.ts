import type { ReportsData } from '../types/report.types';

const trend = [
    { label: 'Mai', revenue: '8 420', expenses: '3 210', revenueShare: 52, expenseShare: 29 },
    { label: 'Juin', revenue: '9 180', expenses: '3 640', revenueShare: 57, expenseShare: 32 },
    { label: 'Juil', revenue: '9 760', expenses: '3 420', revenueShare: 61, expenseShare: 30 },
    { label: 'Août', revenue: '10 140', expenses: '3 980', revenueShare: 64, expenseShare: 35 },
    { label: 'Sept', revenue: '10 820', expenses: '4 120', revenueShare: 68, expenseShare: 36 },
    { label: 'Oct', revenue: '11 460', expenses: '3 760', revenueShare: 72, expenseShare: 33 },
];

export const REPORT_MOCKS: Record<'super_admin' | 'patron' | 'admin_immobilier' | 'admin_ville', ReportsData> = {
    super_admin: {
        title: 'Rapports de la plateforme',
        description: 'Suivez la croissance et la santé globale des organisations H-Immo.',
        scope: 'Ensemble de la plateforme', periodLabel: 'Mai – octobre 2026', currency: 'USD', trend,
        metrics: [
            { id: 'orgs', label: 'Organisations actives', value: '148', helper: '+12 sur 6 mois', tone: 'primary' },
            { id: 'users', label: 'Comptes utilisateurs', value: '3 942', helper: '214 invitations en attente', tone: 'info' },
            { id: 'mrr', label: 'Revenu récurrent', value: '42 300 $', helper: '+8,4 % sur la période', tone: 'success' },
            { id: 'health', label: 'Organisations à suivre', value: '7', helper: '5 essais · 2 suspendues', tone: 'warning' },
        ],
        breakdownTitle: 'Organisations par activité',
        breakdown: [
            { id: 'kin', label: 'Kinshasa Immo Group', detail: 'Kinshasa · 186 unités', amount: '98,4 %', share: 98 },
            { id: 'lubu', label: 'Lubumbashi Résidences', detail: 'Lubumbashi · 74 unités', amount: '91,2 %', share: 91 },
            { id: 'goma', label: 'Goma Patrimoine', detail: 'Goma · 31 unités', amount: '84,6 %', share: 85 },
            { id: 'matadi', label: 'Matadi Logements', detail: 'Matadi · 12 unités', amount: '78,1 %', share: 78 },
        ],
        attentionTitle: 'Organisations à surveiller',
        attention: [
            { id: 'trial', label: 'Essais arrivant à échéance', detail: '5 organisations · cette semaine', amount: '5', status: 'À suivre' },
            { id: 'suspended', label: 'Organisations suspendues', detail: 'Vérifier le statut des comptes', amount: '2', status: 'Action requise' },
            { id: 'growth', label: 'Nouvelles organisations', detail: 'Créées sur les 30 derniers jours', amount: '12', status: 'En hausse' },
        ],
    },
    patron: {
        title: 'Rapports de l’organisation',
        description: 'Analysez les revenus, l’occupation et les dépenses de votre portefeuille.',
        scope: 'Kinshasa Immo Group', periodLabel: 'Mai – octobre 2026', currency: 'USD', trend,
        metrics: [
            { id: 'revenue', label: 'Loyers encaissés', value: '59 780 $', helper: '+7,2 % sur 6 mois', tone: 'success' },
            { id: 'occupancy', label: 'Taux d’occupation', value: '92,6 %', helper: '342 unités occupées', tone: 'primary' },
            { id: 'arrears', label: 'Impayés à recouvrer', value: '6 420 $', helper: '18 échéances en retard', tone: 'warning' },
            { id: 'expenses', label: 'Dépenses engagées', value: '22 130 $', helper: 'Sur la période sélectionnée', tone: 'info' },
        ],
        breakdownTitle: 'Occupation par ville',
        breakdown: [
            { id: 'kin', label: 'Kinshasa', detail: '342 / 365 unités occupées', amount: '93,7 %', share: 94 },
            { id: 'lubu', label: 'Lubumbashi', detail: '128 / 146 unités occupées', amount: '87,7 %', share: 88 },
            { id: 'goma', label: 'Goma', detail: '74 / 82 unités occupées', amount: '90,2 %', share: 90 },
        ],
        attentionTitle: 'Suivi financier',
        attention: [
            { id: 'arrears', label: 'Loyers en retard', detail: '18 échéances · 6 locataires', amount: '6 420 $', status: 'À recouvrer' },
            { id: 'expense', label: 'Dépenses à valider', detail: '3 demandes de validation', amount: '1 850 $', status: 'À valider' },
            { id: 'leases', label: 'Baux à renouveler', detail: 'Échéance dans les 60 jours', amount: '8', status: 'À anticiper' },
        ],
    },
    admin_immobilier: {
        title: 'Rapports immobiliers',
        description: 'Mesurez l’occupation du patrimoine et identifiez les actions opérationnelles.',
        scope: 'Kinshasa Immo Group', periodLabel: 'Mai – octobre 2026', currency: 'USD', trend,
        metrics: [
            { id: 'occupancy', label: 'Occupation globale', value: '92,6 %', helper: '+2,1 pts sur 6 mois', tone: 'primary' },
            { id: 'units', label: 'Unités gérées', value: '593', helper: '27 biens immobiliers', tone: 'info' },
            { id: 'arrears', label: 'Impayés signalés', value: '18', helper: '6 420 $ à recouvrer', tone: 'warning' },
            { id: 'expenses', label: 'Dépenses liées aux biens', value: '22 130 $', helper: 'Dont 86 % validées', tone: 'success' },
        ],
        breakdownTitle: 'Occupation par immeuble',
        breakdown: [
            { id: 'palmiers', label: 'Résidence Les Palmiers', detail: '96 / 102 unités occupées', amount: '94,1 %', share: 94 },
            { id: 'lac', label: 'Résidence du Lac', detail: '74 / 82 unités occupées', amount: '90,2 %', share: 90 },
            { id: 'horizon', label: 'Immeuble Horizon', detail: '62 / 68 unités occupées', amount: '91,2 %', share: 91 },
            { id: 'virunga', label: 'Résidence Virunga', detail: '48 / 55 unités occupées', amount: '87,3 %', share: 87 },
        ],
        attentionTitle: 'Points opérationnels',
        attention: [
            { id: 'vacant', label: 'Unités disponibles', detail: 'À remettre en location', amount: '44', status: 'Vacantes' },
            { id: 'arrears', label: 'Échéances en retard', detail: '18 échéances dans 4 immeubles', amount: '6 420 $', status: 'À traiter' },
            { id: 'maintenance', label: 'Dépenses à valider', detail: 'Réparations et entretien', amount: '1 850 $', status: 'À valider' },
        ],
    },
    admin_ville: {
        title: 'Rapports de la ville',
        description: 'Consultez les indicateurs des villes et des biens qui vous sont attribués.',
        scope: 'Kinshasa', periodLabel: 'Mai – octobre 2026', currency: 'USD', trend,
        metrics: [
            { id: 'occupancy', label: 'Occupation de la ville', value: '93,7 %', helper: '342 unités occupées', tone: 'primary' },
            { id: 'units', label: 'Unités suivies', value: '365', helper: 'Dans vos villes attribuées', tone: 'info' },
            { id: 'arrears', label: 'Impayés à suivre', value: '11', helper: '3 780 $ en retard', tone: 'warning' },
            { id: 'workers', label: 'Personnel actif', value: '24', helper: '6 affectations cette semaine', tone: 'success' },
        ],
        breakdownTitle: 'Occupation par zone',
        breakdown: [
            { id: 'gombe', label: 'Gombe', detail: '128 / 132 unités occupées', amount: '97,0 %', share: 97 },
            { id: 'limete', label: 'Limete', detail: '104 / 113 unités occupées', amount: '92,0 %', share: 92 },
            { id: 'ngaliema', label: 'Ngaliema', detail: '110 / 120 unités occupées', amount: '91,7 %', share: 92 },
        ],
        attentionTitle: 'À suivre dans la ville',
        attention: [
            { id: 'arrears', label: 'Échéances en retard', detail: '11 échéances · 4 biens', amount: '3 780 $', status: 'À recouvrer' },
            { id: 'expense', label: 'Dépenses en attente', detail: 'Demandes liées à vos villes', amount: '920 $', status: 'À valider' },
            { id: 'workers', label: 'Affectations du personnel', detail: 'Interventions prévues cette semaine', amount: '6', status: 'Planifiées' },
        ],
    },
};

export async function fetchReportMock(role: keyof typeof REPORT_MOCKS): Promise<ReportsData> {
    await new Promise((resolve) => setTimeout(resolve, 220));
    return structuredClone(REPORT_MOCKS[role]);
}
