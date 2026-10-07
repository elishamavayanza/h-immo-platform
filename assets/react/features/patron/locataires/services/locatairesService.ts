import type { LocataireRow } from '../types/locataire.types';

export const LOCATAIRES: LocataireRow[] = [
    { id: 't1', name: 'Marie Ilunga', email: 'marie.ilunga@example.cd', phone: '+243 812 345 678', city: 'Kinshasa', property: 'Résidence Les Palmiers · KIN-204', leaseEnd: '30 juin 2027', balance: '0 $', status: 'Actif' },
    { id: 't2', name: 'Patrick Nsimba', email: 'patrick.nsimba@example.cd', phone: '+243 998 456 123', city: 'Kinshasa', property: 'Immeuble Horizon · HZN-08', leaseEnd: '31 mars 2027', balance: '450 $', status: 'Actif' },
    { id: 't3', name: 'Aline Kabeya', email: 'aline.kabeya@example.cd', phone: '+243 821 987 654', city: 'Lubumbashi', property: 'Résidence du Lac · LAC-12', leaseEnd: '31 déc. 2026', balance: '0 $', status: 'Actif' },
    { id: 't4', name: 'Jean Mbuyi', email: 'jean.mbuyi@example.cd', phone: '+243 810 222 333', city: 'Goma', property: 'Résidence Virunga · VIR-04', leaseEnd: '—', balance: '0 $', status: 'En attente' },
    { id: 't5', name: 'Grâce Banza', email: 'grace.banza@example.cd', phone: '+243 899 333 444', city: 'Kinshasa', property: 'Résidence Les Palmiers · KIN-110', leaseEnd: '31 août 2026', balance: '0 $', status: 'Ancien' },
];
