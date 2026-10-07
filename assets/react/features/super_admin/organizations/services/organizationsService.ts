import type { OrganizationRow } from '../types/organization.types';

/** Données de démonstration isolées du rendu, en attente du branchement API. */
export const INITIAL_ORGANIZATIONS: OrganizationRow[] = [
    { id: 'org-1', name: 'Kinshasa Immo Group', code: 'KIN-IMG', city: 'Kinshasa', plan: 'Enterprise', members: 42, properties: 186, status: 'active', createdAt: '02 nov. 2024' },
    { id: 'org-2', name: 'Lubumbashi Résidences', code: 'LUB-RES', city: 'Lubumbashi', plan: 'Pro', members: 18, properties: 74, status: 'active', createdAt: '21 oct. 2024' },
    { id: 'org-3', name: 'Goma Patrimoine', code: 'GOM-PAT', city: 'Goma', plan: 'Pro', members: 9, properties: 31, status: 'trial', createdAt: '14 oct. 2024' },
    { id: 'org-4', name: 'Matadi Logements', code: 'MAT-LOG', city: 'Matadi', plan: 'Starter', members: 4, properties: 12, status: 'active', createdAt: '05 oct. 2024' },
    { id: 'org-5', name: 'Bukavu Estates', code: 'BUK-EST', city: 'Bukavu', plan: 'Starter', members: 3, properties: 8, status: 'suspended', createdAt: '28 sept. 2024' },
    { id: 'org-6', name: 'Kolwezi Mines Rentals', code: 'KOL-MIN', city: 'Kolwezi', plan: 'Pro', members: 12, properties: 45, status: 'active', createdAt: '19 sept. 2024' },
    { id: 'org-7', name: 'Congo Habitat', code: 'COD-HAB', city: 'Kinshasa', plan: 'Enterprise', members: 27, properties: 113, status: 'active', createdAt: '03 sept. 2024' },
    { id: 'org-8', name: 'Kivu Immobilier', code: 'KIV-IMM', city: 'Goma', plan: 'Pro', members: 7, properties: 26, status: 'trial', createdAt: '24 août 2024' },
];
