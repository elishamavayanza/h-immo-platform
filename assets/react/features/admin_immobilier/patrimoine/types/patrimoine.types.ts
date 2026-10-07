export type PatrimoineKind = 'Parcelle' | 'Bâtiment' | 'Unité';
export interface PatrimoineRow { id: string; name: string; city: string; address: string; kind: PatrimoineKind; units: number; occupancy: string; status: 'Actif' | 'En travaux'; }
