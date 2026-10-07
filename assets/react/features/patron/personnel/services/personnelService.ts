import type { PersonnelRow } from '../types/personnel.types';
export const PERSONNEL: PersonnelRow[] = [
    { id: 'w1', name: 'David Kanku', role: 'Gardien', city: 'Kinshasa', assignments: 2, phone: '+243 812 222 111', status: 'Actif' },
    { id: 'w2', name: 'Esther Lunda', role: 'Agent d’entretien', city: 'Kinshasa', assignments: 3, phone: '+243 998 333 222', status: 'Actif' },
    { id: 'w3', name: 'Michel Beya', role: 'Technicien', city: 'Lubumbashi', assignments: 4, phone: '+243 821 444 333', status: 'Actif' },
    { id: 'w4', name: 'Pauline Moke', role: 'Gardienne', city: 'Goma', assignments: 1, phone: '+243 810 555 444', status: 'Suspendu' },
];
