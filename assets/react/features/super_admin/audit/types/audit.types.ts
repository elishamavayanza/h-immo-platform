export type AuditCategory = 'organization' | 'user' | 'security' | 'billing';
export type AuditOutcome = 'success' | 'warning' | 'danger';

export interface AuditEntry {
    id: string;
    date: string;
    actor: string;
    action: string;
    target: string;
    category: AuditCategory;
    outcome: AuditOutcome;
    ipAddress: string;
}
