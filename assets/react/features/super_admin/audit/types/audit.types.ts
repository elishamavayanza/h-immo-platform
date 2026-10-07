/** Champs alignés sur AuditLogResponse du backend. */
export interface AuditEntry {
    id: string;
    organizationId: string | null;
    userId: string | null;
    action: string;
    entityType: string;
    /** Champ actuellement exposé par l'API ; à remplacer par un UUID côté backend. */
    entityId: number;
    oldValues: Record<string, unknown> | null;
    newValues: Record<string, unknown> | null;
    createdAt: string;
}
