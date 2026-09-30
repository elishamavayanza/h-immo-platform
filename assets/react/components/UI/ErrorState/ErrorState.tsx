import React from 'react';
import { useErrorState, UseErrorStateProps } from '../../../hook-components/UI/ErrorState';
import { Button } from '@/react/components/UI/Button';
import { ApiErrorKind } from '@/services/api/errorDisplay';
import { useI18n, TranslateFn } from '@/react/i18n/I18nContext';

// ─────────────────────────────────────────
// Icônes d'illustration
// ─────────────────────────────────────────

const BadRequestIcon = () => (
    <svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="10" />
        <line x1="12" y1="8" x2="12" y2="12" />
        <circle cx="12" cy="16" r="0.5" fill="currentColor" />
    </svg>
);

const LockIcon = () => (
    <svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <rect x="3" y="11" width="18" height="11" rx="2" />
        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        <circle cx="12" cy="16.5" r="1.2" fill="currentColor" />
    </svg>
);

const ShieldIcon = () => (
    <svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z" />
        <line x1="9" y1="12" x2="15" y2="12" />
    </svg>
);

const NotFoundIcon = () => (
    <svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <circle cx="11" cy="11" r="7" />
        <line x1="21" y1="21" x2="16.5" y2="16.5" />
        <line x1="9" y1="11" x2="13" y2="11" />
    </svg>
);

const ServerIcon = () => (
    <svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <rect x="2" y="3" width="20" height="7" rx="2" />
        <rect x="2" y="14" width="20" height="7" rx="2" />
        <line x1="6" y1="6.5" x2="6" y2="6.5" />
        <line x1="6" y1="17.5" x2="6" y2="17.5" />
        <line x1="10" y1="6.5" x2="16" y2="6.5" />
        <line x1="10" y1="17.5" x2="16" y2="17.5" />
    </svg>
);

const NetworkIcon = () => (
    <svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M8.5 16.5a5 5 0 0 1 7 0" />
        <path d="M5 13a9 9 0 0 1 14 0" />
        <path d="M1.5 9.5a13 13 0 0 1 21 0" />
        <line x1="12" y1="19" x2="12" y2="20" />
    </svg>
);

const GenericIcon = () => (
    <svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
        <line x1="12" y1="9" x2="12" y2="13" />
        <line x1="12" y1="17" x2="12.01" y2="17" />
    </svg>
);

function pickIcon(status?: number, kind?: ApiErrorKind): React.ReactNode {
    if (kind === 'unauthorized' || status === 401) return <LockIcon />;
    if (kind === 'forbidden' || status === 403) return <ShieldIcon />;
    if (kind === 'not-found' || status === 404) return <NotFoundIcon />;
    if (kind === 'network' || status === 0) return <NetworkIcon />;
    if (status && status >= 500) return <ServerIcon />;
    if (status && status >= 400) return <BadRequestIcon />;
    return <GenericIcon />;
}

function pickTone(status?: number, kind?: ApiErrorKind): 'default' | 'warning' | 'danger' | 'info' {
    if (kind === 'forbidden' || kind === 'network' || kind === 'server') return 'danger';
    if (status && status >= 500) return 'danger';
    if (status === 0) return 'danger';
    if (kind === 'unauthorized') return 'warning';
    if (status && status >= 400 && status < 500) return 'warning';
    return 'info';
}

// ─────────────────────────────────────────
// Composant
// ─────────────────────────────────────────

export interface ErrorStateProps extends UseErrorStateProps {
    /** Code HTTP (ex. 404, 500) affiché en secondaire. */
    status?: number;
    /** Libellé HTTP secondaire (ex. "Internal Server Error"). */
    codeLabel?: string;
    /** Catégorie d'erreur API (optionnelle, aide au choix icône/couleur). */
    kind?: ApiErrorKind;
    /** Titre principal, compréhensible par un utilisateur non technique. */
    title?: string;
    /** Message d'explication. */
    message?: string;
    /** Relance la requête qui a échoué. */
    onRetry?: () => void;
    /** Redirige vers la page de connexion (401). */
    onLogin?: () => void;
    /** Retour en arrière (403/404). */
    onBack?: () => void;
    /** Retour à l'accueil (404). */
    onHome?: () => void;
}

function renderActions(props: ErrorStateProps & { t: TranslateFn }) {
    const { onRetry, onLogin, onBack, onHome, t } = props;
    const hasActions = !!(onRetry || onLogin || onBack || onHome);
    if (!hasActions) return null;

    return (
        <div className="error-state__actions">
            {onRetry && (
                <Button variant="primary" onClick={onRetry}>
                    {t('Réessayer')}
                </Button>
            )}
            {onLogin && (
                <Button variant="primary" onClick={onLogin}>
                    {t('Se connecter')}
                </Button>
            )}
            {onBack && (
                <Button variant="secondary" onClick={onBack}>
                    {t('Retour')}
                </Button>
            )}
            {onHome && (
                <Button variant="secondary" onClick={onHome}>
                    {t('Accueil')}
                </Button>
            )}
        </div>
    );
}

export function ErrorState({
                               status,
                               codeLabel,
                               kind,
                               title,
                               message,
                               onRetry,
                               onLogin,
                               onBack,
                               onHome,
                               size,
                               tone,
                               compact,
                               className,
                           }: ErrorStateProps) {
    const { t } = useI18n();
    const { classes } = useErrorState({
        size,
        tone: tone ?? pickTone(status, kind),
        compact,
        className,
    });

    const displayedTitle = title ? (typeof title === 'string' ? t(title) : title) : t('Une erreur est survenue');
    const displayedMessage = message ? (typeof message === 'string' ? t(message) : message) : undefined;
    const displayedCode = status !== undefined
        ? (codeLabel
            ? t('ERREUR {{ status }} · {{ codeLabel }}', { status: String(status), codeLabel })
            : t('ERREUR {{ status }}', { status: String(status) }))
        : codeLabel;

    return (
        <div className={classes} role="alert" aria-live="polite">
            <div className="error-state__icon" aria-hidden="true">
                {pickIcon(status, kind)}
            </div>

            <h2 className="error-state__title">{displayedTitle}</h2>

            {displayedMessage && <p className="error-state__message">{displayedMessage}</p>}

            {displayedCode && (
                <span className="error-state__code">{displayedCode}</span>
            )}

            {renderActions({ status, codeLabel, kind, title, message: displayedMessage, onRetry, onLogin, onBack, onHome, t })}
        </div>
    );
}
