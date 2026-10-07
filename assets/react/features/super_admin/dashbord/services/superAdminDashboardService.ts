// ============================================================
// Service du tableau de bord SUPER_ADMIN.
// V1 : renvoie des données mockées (aucun appel réseau).
// V2 : remplacer `MOCK` par un appel `api.get('/admin/dashboard')`
//      en gardant la même signature `Promise<SuperAdminDashboardData>`.
// ============================================================


import {SuperAdminDashboardData} from "../types/superAdminDashboard.types.ts";

const MOCK: SuperAdminDashboardData = {
    kpis: [
        {
            id: 'orgs',
            label: 'Organisations actives',
            value: '148',
            delta: 12.4,
            trend: 'up',
            positive: true,
            helper: '12 nouvelles ce mois',
            tone: 'primary',
            icon: 'building',
        },
        {
            id: 'users',
            label: 'Utilisateurs',
            value: '3 942',
            delta: 8.1,
            trend: 'up',
            positive: true,
            helper: 'dont 214 invités',
            tone: 'info',
            icon: 'users',
        },
        {
            id: 'mrr',
            label: 'Revenu mensuel (MRR)',
            value: '42 300 $',
            delta: 5.7,
            trend: 'up',
            positive: true,
            helper: 'Prévision 44 100 $',
            tone: 'success',
            icon: 'revenue',
        },
        {
            id: 'churn',
            label: 'Taux de churn',
            value: '1,8 %',
            delta: -0.4,
            trend: 'down',
            positive: true,
            helper: 'Objectif < 2 %',
            tone: 'warning',
            icon: 'pulse',
        },
    ],

    revenueSeries: [
        { label: 'Jan', revenue: 21800, subscriptions: 82 },
        { label: 'Fév', revenue: 23400, subscriptions: 88 },
        { label: 'Mar', revenue: 24100, subscriptions: 94 },
        { label: 'Avr', revenue: 25900, subscriptions: 101 },
        { label: 'Mai', revenue: 27600, subscriptions: 108 },
        { label: 'Juin', revenue: 28400, subscriptions: 112 },
        { label: 'Juil', revenue: 30200, subscriptions: 119 },
        { label: 'Août', revenue: 31800, subscriptions: 125 },
        { label: 'Sep', revenue: 33500, subscriptions: 131 },
        { label: 'Oct', revenue: 36200, subscriptions: 138 },
        { label: 'Nov', revenue: 39400, subscriptions: 143 },
        { label: 'Déc', revenue: 42300, subscriptions: 148 },
    ],

    recentOrganizations: [
        {
            id: 'org-1',
            name: 'Kinshasa Immo Group',
            slug: 'kinshasa-immo',
            city: 'Kinshasa',
            plan: 'Enterprise',
            users: 42,
            properties: 186,
            status: 'active',
            createdAt: '2024-11-02',
        },
        {
            id: 'org-2',
            name: 'Lubumbashi Résidences',
            slug: 'lubu-residences',
            city: 'Lubumbashi',
            plan: 'Pro',
            users: 18,
            properties: 74,
            status: 'active',
            createdAt: '2024-10-21',
        },
        {
            id: 'org-3',
            name: 'Goma Patrimoine',
            slug: 'goma-patrimoine',
            city: 'Goma',
            plan: 'Pro',
            users: 9,
            properties: 31,
            status: 'trial',
            createdAt: '2024-10-14',
        },
        {
            id: 'org-4',
            name: 'Matadi Logements',
            slug: 'matadi-logements',
            city: 'Matadi',
            plan: 'Starter',
            users: 4,
            properties: 12,
            status: 'active',
            createdAt: '2024-10-05',
        },
        {
            id: 'org-5',
            name: 'Bukavu Estates',
            slug: 'bukavu-estates',
            city: 'Bukavu',
            plan: 'Starter',
            users: 3,
            properties: 8,
            status: 'suspended',
            createdAt: '2024-09-28',
        },
        {
            id: 'org-6',
            name: 'Kolwezi Mines Rentals',
            slug: 'kolwezi-rentals',
            city: 'Kolwezi',
            plan: 'Pro',
            users: 12,
            properties: 45,
            status: 'active',
            createdAt: '2024-09-19',
        },
    ],

    activity: [
        {
            id: 'act-1',
            actor: 'Sarah Mbala',
            action: 'a créé l’organisation',
            target: 'Kinshasa Immo Group',
            timestamp: 'il y a 4 min',
            kind: 'create',
        },
        {
            id: 'act-2',
            actor: 'Système',
            action: 'a suspendu',
            target: 'Bukavu Estates',
            timestamp: 'il y a 1 h',
            kind: 'alert',
        },
        {
            id: 'act-3',
            actor: 'David Kalu',
            action: 'a mis à jour le plan de',
            target: 'Lubumbashi Résidences',
            timestamp: 'il y a 3 h',
            kind: 'update',
        },
        {
            id: 'act-4',
            actor: 'Sarah Mbala',
            action: 's’est connectée au back-office',
            target: '',
            timestamp: 'il y a 5 h',
            kind: 'login',
        },
        {
            id: 'act-5',
            actor: 'Système',
            action: 'a purgé les jetons révoqués',
            target: 'RevokedToken',
            timestamp: 'hier',
            kind: 'delete',
        },
        {
            id: 'act-6',
            actor: 'David Kalu',
            action: 'a invité',
            target: '6 utilisateurs',
            timestamp: 'hier',
            kind: 'create',
        },
    ],

    health: [
        { id: 'cpu', label: 'CPU', value: 38, unit: '%', status: 'healthy' },
        { id: 'latency', label: 'Latence p95', value: 172, unit: 'ms', status: 'healthy' },
        { id: 'storage', label: 'Stockage', value: 71, unit: '%', status: 'warning' },
        { id: 'errors', label: 'Erreurs 5xx', value: 0.4, unit: '%', status: 'healthy' },
    ],

    topCities: [
        { name: 'Kinshasa', count: 62, share: 0.42 },
        { name: 'Lubumbashi', count: 34, share: 0.23 },
        { name: 'Goma', count: 21, share: 0.14 },
        { name: 'Bukavu', count: 17, share: 0.11 },
        { name: 'Kolwezi', count: 14, share: 0.10 },
    ],
};

/** Simule un appel réseau (latence réaliste, sans backend). */
export async function fetchSuperAdminDashboard(): Promise<SuperAdminDashboardData> {
    await new Promise((resolve) => setTimeout(resolve, 320));
    // Retourne une copie pour éviter toute mutation accidentelle.
    return structuredClone(MOCK);
}
