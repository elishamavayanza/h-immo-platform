export interface UserSettings {
    compactTables: boolean;
    emailNotifications: boolean;
    locale: string;
    theme: string;
    dateFormat: string;
    inAppNotifications: boolean;
    reducedMotion: boolean;
    auditLogRetentionDays?: number;
    defaultPageSize?: number;
}