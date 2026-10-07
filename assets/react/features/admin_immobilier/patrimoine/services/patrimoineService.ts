import type { PatrimoineRow } from '../types/patrimoine.types';

export const PATRIMOINE: PatrimoineRow[] = [
    { id: 'p1', name: 'Résidence Les Palmiers', city: 'Kinshasa', address: 'Gombe · Avenue du Port', kind: 'Bâtiment', units: 24, occupancy: '92 %', status: 'Actif' },
    { id: 'p2', name: 'Immeuble Horizon', city: 'Kinshasa', address: 'Ngaliema · Boulevard du 30 Juin', kind: 'Bâtiment', units: 18, occupancy: '83 %', status: 'Actif' },
    { id: 'p3', name: 'Résidence du Lac', city: 'Lubumbashi', address: 'Golf · Avenue Kilela Balanda', kind: 'Bâtiment', units: 32, occupancy: '91 %', status: 'Actif' },
    { id: 'p4', name: 'Parcelle KIN-045', city: 'Kinshasa', address: 'Limete · 7e Rue', kind: 'Parcelle', units: 0, occupancy: '—', status: 'En travaux' },
    { id: 'p5', name: 'Résidence Virunga', city: 'Goma', address: 'Himbi · Route de Sake', kind: 'Bâtiment', units: 12, occupancy: '75 %', status: 'Actif' },
    { id: 'p6', name: 'Dépôt Central', city: 'Matadi', address: 'Centre-ville · Route Nationale 1', kind: 'Unité', units: 1, occupancy: '100 %', status: 'Actif' },
];
