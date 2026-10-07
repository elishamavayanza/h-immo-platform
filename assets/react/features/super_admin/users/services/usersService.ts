import type { UserRow } from '../types/user.types';

/** Données de démonstration isolées du rendu, en attente du branchement API. */
export const INITIAL_USERS: UserRow[] = [
    { id: 'user-1', name: 'Sarah Mbala', email: 'sarah.mbala@kinimmo.cd', organization: 'Kinshasa Immo Group', role: 'super_admin', status: 'active', lastActivity: 'À l’instant' },
    { id: 'user-2', name: 'David Kalu', email: 'david.kalu@kinimmo.cd', organization: 'Kinshasa Immo Group', role: 'patron', status: 'active', lastActivity: 'Il y a 12 min' },
    { id: 'user-3', name: 'Marie Ilunga', email: 'marie.ilunga@lubu-res.cd', organization: 'Lubumbashi Résidences', role: 'admin_immobilier', status: 'active', lastActivity: 'Il y a 35 min' },
    { id: 'user-4', name: 'Patrick Nsimba', email: 'patrick.nsimba@goma-pat.cd', organization: 'Goma Patrimoine', role: 'admin_ville', status: 'invited', lastActivity: 'Invitation envoyée' },
    { id: 'user-5', name: 'Aline Kabeya', email: 'aline.kabeya@matadi-log.cd', organization: 'Matadi Logements', role: 'patron', status: 'active', lastActivity: 'Hier' },
    { id: 'user-6', name: 'Jean Mbuyi', email: 'jean.mbuyi@bukavu-est.cd', organization: 'Bukavu Estates', role: 'admin_immobilier', status: 'suspended', lastActivity: '12 sept. 2024' },
    { id: 'user-7', name: 'Grâce Banza', email: 'grace.banza@kolwezi-rent.cd', organization: 'Kolwezi Mines Rentals', role: 'admin_ville', status: 'active', lastActivity: 'Hier' },
    { id: 'user-8', name: 'Luc Monga', email: 'luc.monga@congo-habitat.cd', organization: 'Congo Habitat', role: 'admin_immobilier', status: 'active', lastActivity: 'Il y a 2 jours' },
];
