import type { DepenseRow } from '../types/depense.types';
export const DEPENSES: DepenseRow[] = [
    { id: 'd1', date: '06 oct. 2026', city: 'Kinshasa', category: 'Entretien', property: 'Les Palmiers', description: 'Réparation pompe à eau', amount: '240 $', status: 'Validée' },
    { id: 'd2', date: '04 oct. 2026', city: 'Kinshasa', category: 'Taxes', property: 'Immeuble Horizon', description: 'Taxe foncière trimestrielle', amount: '1 200 $', status: 'À valider' },
    { id: 'd3', date: '02 oct. 2026', city: 'Lubumbashi', category: 'Personnel', property: 'Résidence du Lac', description: 'Gardiennage · septembre', amount: '320 $', status: 'Validée' },
    { id: 'd4', date: '29 sept. 2026', city: 'Goma', category: 'Eau et électricité', property: 'Résidence Virunga', description: 'Facture SNEL', amount: '185 $', status: 'Validée' },
];
