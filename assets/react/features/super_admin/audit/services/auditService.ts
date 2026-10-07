import type { AuditEntry } from '../types/audit.types';

/** Événements fictifs pour la maquette ; aucune donnée d'audit API n'est lue ici. */
export const INITIAL_AUDIT_ENTRIES: AuditEntry[] = [
    { id: 'evt-01', date: '07 oct. 2026 · 09:42', actor: 'Sarah Mbala', action: 'Organisation créée', target: 'Kinshasa Immo Group', category: 'organization', outcome: 'success', ipAddress: '41.243.18.92' },
    { id: 'evt-02', date: '07 oct. 2026 · 09:18', actor: 'David Kalu', action: 'Invitation envoyée', target: 'patrick.nsimba@goma-pat.cd', category: 'user', outcome: 'success', ipAddress: '41.243.18.92' },
    { id: 'evt-03', date: '07 oct. 2026 · 08:56', actor: 'Système', action: 'Connexion refusée', target: 'compte inconnu', category: 'security', outcome: 'warning', ipAddress: '102.67.44.11' },
    { id: 'evt-04', date: '06 oct. 2026 · 17:31', actor: 'Sarah Mbala', action: 'Taux de change modifié', target: 'USD / CDF', category: 'billing', outcome: 'success', ipAddress: '41.243.18.92' },
    { id: 'evt-05', date: '06 oct. 2026 · 16:08', actor: 'Système', action: 'Compte suspendu', target: 'Jean Mbuyi', category: 'user', outcome: 'danger', ipAddress: '—' },
    { id: 'evt-06', date: '06 oct. 2026 · 14:22', actor: 'Sarah Mbala', action: 'Organisation suspendue', target: 'Bukavu Estates', category: 'organization', outcome: 'warning', ipAddress: '41.243.18.92' },
    { id: 'evt-07', date: '06 oct. 2026 · 11:05', actor: 'David Kalu', action: 'Rôle attribué', target: 'Marie Ilunga · Admin immobilier', category: 'user', outcome: 'success', ipAddress: '154.72.190.6' },
];
