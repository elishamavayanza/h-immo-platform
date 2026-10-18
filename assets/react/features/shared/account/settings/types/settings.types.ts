export interface UserSettings {
    compactTables: boolean;
    emailNotifications: boolean;
    locale: string;
    theme: string;
    accentPalette: 'ocean' | 'violet' | 'sage' | 'amber' | 'coral' | 'slate';
    dateFormat: string;
    inAppNotifications: boolean;
    /** Autorise l’affichage du bouton d’installation de la PWA dans les paramètres. */
    allowInstallPrompt: boolean;
    reducedMotion: boolean;
    /** Replie le menu latéral par défaut sur les écrans où il reste dans le flux. */
    sidebarCollapsed: boolean;
    auditLogRetentionDays?: number;
    defaultPageSize?: number;
}
