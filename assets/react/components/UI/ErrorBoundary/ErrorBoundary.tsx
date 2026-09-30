import React from 'react';
import { ErrorState } from '@/react/components/UI/ErrorState';
import { useI18n, TranslateFn } from '@/react/i18n/I18nContext';

interface ErrorBoundaryState {
    error: Error | null;
}

interface ErrorBoundaryProps {
    children: React.ReactNode;
}

/**
 * Filet de sécurité global : intercepte toute erreur de rendu
 * React afin de ne jamais afficher de page blanche. L'utilisateur
 * voit un état d'erreur clair avec un bouton « Réessayer ».
 */
class ErrorBoundaryImpl extends React.Component<ErrorBoundaryProps & { t: TranslateFn }, ErrorBoundaryState> {
    state: ErrorBoundaryState = { error: null };

    static getDerivedStateFromError(error: Error): ErrorBoundaryState {
        return { error };
    }

    componentDidCatch(error: Error, info: React.ErrorInfo) {
        console.error('[ERREUR APP] Rendu échoué —', error, info.componentStack);
    }

    private handleRetry = () => {
        window.location.reload();
    };

    render() {
        const { error } = this.state;
        const { children, t } = this.props;

        if (error !== null) {
            return (
                <div
                    style={{
                        display: 'flex',
                        minHeight: '100vh',
                        alignItems: 'center',
                        justifyContent: 'center',
                        padding: '1rem',
                        background: 'var(--color-background, #fff)',
                    }}
                >
                    <ErrorState
                        size="full"
                        title={t('Une erreur inattendue est survenue')}
                        message={t("Nous sommes désolés. Une erreur s'est produite pendant l'affichage de cette page. Vous pouvez réessayer.")}
                        onRetry={this.handleRetry}
                    />
                </div>
            );
        }

        return children;
    }
}

export function ErrorBoundary({ children }: ErrorBoundaryProps) {
    const { t } = useI18n();
    return <ErrorBoundaryImpl t={t}>{children}</ErrorBoundaryImpl>;
}