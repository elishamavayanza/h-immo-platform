import React from 'react';
import { ErrorState } from '../ErrorState';

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
class ErrorBoundaryImpl extends React.Component<ErrorBoundaryProps, ErrorBoundaryState> {
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
        const { children } = this.props;

        if (error !== null) {
            return (
                <div
                    style={{
                        display: 'flex',
                        minHeight: '100vh',
                        alignItems: 'center',
                        justifyContent: 'center',
                        padding: '1rem',
                        background: 'var(--color-bg-app, #0A0B0D)',
                    }}
                >
                    <ErrorState
                        size="full"
                        tone="danger"
                        title="Une erreur inattendue est survenue"
                        message="Nous sommes désolés. Une erreur s'est produite pendant l'affichage de cette page. Vous pouvez réessayer."
                        onRetry={this.handleRetry}
                    />
                </div>
            );
        }

        return children;
    }
}

export function ErrorBoundary({ children }: ErrorBoundaryProps) {
    return <ErrorBoundaryImpl>{children}</ErrorBoundaryImpl>;
}
