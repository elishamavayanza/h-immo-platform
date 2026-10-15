// ============================================================
// En-tête du tableau de bord SUPER_ADMIN : contexte plateforme,
// heure de mise à jour et action « actualiser ».
// ============================================================

import { Button } from '../../../../components/UI/Button';
import { DashboardIcon } from '../../icons/DashboardIcon';

export interface DashboardHeaderProps {
    onReload?: () => void;
    isRefreshing?: boolean;
    generatedAt: string;
}

export function DashboardHeader({ onReload, isRefreshing, generatedAt }: DashboardHeaderProps) {
    const updatedAt = new Date(generatedAt);
    return (
        <header className="sa-header">
            <div className="sa-header__intro">
                <span className="sa-header__eyebrow">
                    <DashboardIcon name="shield" size={14} />
                    Vue plateforme — SUPER_ADMIN
                </span>
                <h1 className="sa-header__title">Tableau de bord</h1>
                <p className="sa-header__subtitle">
                    Vue d’ensemble des organisations, des comptes et des actions de la plateforme.
                </p>
            </div>

            <div className="sa-header__actions">
                <span className="sa-header__updated">
                    {Number.isNaN(updatedAt.getTime())
                        ? 'Données à jour'
                        : `Mis à jour à ${new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(updatedAt)}`}
                </span>

                <Button
                    variant="outline"
                    size="small"
                    icon={<DashboardIcon name="refresh" size={16} />}
                    onClick={onReload}
                    disabled={isRefreshing}
                >
                    {isRefreshing ? 'Actualisation…' : 'Actualiser'}
                </Button>
            </div>
        </header>
    );
}
