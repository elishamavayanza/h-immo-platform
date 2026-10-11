export interface UserSettings {
    compactTables: boolean;
    emailNotifications: boolean;
    locale: string;
    theme: string;
    accentPalette: 'ocean' | 'violet' | 'sage' | 'amber' | 'coral' | 'slate';
    dateFormat: string;
    inAppNotifications: boolean;
    reducedMotion: boolean;
    auditLogRetentionDays?: number;
    defaultPageSize?: number;
}
