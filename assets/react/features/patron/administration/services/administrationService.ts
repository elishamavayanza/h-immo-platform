import type { TeamMember } from '../types/administration.types';
export const TEAM: TeamMember[] = [
    { id: 'm1', name: 'Sarah Mbala', email: 'sarah.mbala@example.cd', role: 'Patron', scope: 'Toutes les villes', status: 'Actif' },
    { id: 'm2', name: 'David Kalu', email: 'david.kalu@example.cd', role: 'Admin immobilier', scope: 'Kinshasa, Matadi', status: 'Actif' },
    { id: 'm3', name: 'Marie Ilunga', email: 'marie.ilunga@example.cd', role: 'Admin ville', scope: 'Lubumbashi', status: 'Actif' },
    { id: 'm4', name: 'Patrick Nsimba', email: 'patrick.nsimba@example.cd', role: 'Admin ville', scope: 'Goma', status: 'Invitation envoyée' },
    { id: 'm5', name: 'Aline Kabeya', email: 'aline.kabeya@example.cd', role: 'Admin immobilier', scope: 'Kinshasa', status: 'Suspendu' },
];
