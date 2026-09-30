import React from 'react';
import { useTheme } from '@/react/hooks/ThemeProvider';
import { useI18n } from '@/react/i18n/I18nContext';

const SunIcon = () => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
    </svg>
);

const MoonIcon = () => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
    </svg>
);

export interface ThemeToggleProps {
    className?: string;
    compact?: boolean;
}

export function ThemeToggle({ className = '', compact = false }: ThemeToggleProps) {
    const { t } = useI18n();
    const { theme, toggleTheme } = useTheme();
    const isDark = theme === 'dark';

    return (
        <button
            type="button"
            className={`theme-toggle ${className}`.trim()}
            onClick={toggleTheme}
            aria-label={isDark ? t('Activer le thème clair') : t('Activer le thème sombre')}
            title={isDark ? t('Thème clair') : t('Thème sombre')}
        >
            {isDark ? <SunIcon /> : <MoonIcon />}
            {!compact && <span className="theme-toggle__label">{isDark ? t('Clair') : t('Sombre')}</span>}
        </button>
    );
}