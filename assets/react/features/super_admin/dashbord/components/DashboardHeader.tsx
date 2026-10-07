// ============================================================
// En-tête du tableau de bord SUPER_ADMIN : contexte plateforme,
// sélecteur de période et action « rafraîchir ».
// ============================================================

import { useState } from 'react';

import { Button } from '../../../../components/UI/Button';
import { DashboardIcon } from './DashboardIcon';

const PERIODS = [
    { id: '7d', label: '7 jours' },
    { id: '30d', label: '30 jours' },
    { id: '12m', label: '12 mois' },
] as const;

type PeriodId = (typeof PERIODS)[number]['id'];

export interface DashboardHeaderProps {
    onReload?: () => void;
    isRefreshing?: boolean;
}

export function DashboardHeader({ onReload, isRefreshing }: DashboardHeaderProps) {
    const [period, setPeriod] = useState<PeriodId>('12m');

    return (
        <header className="sa-header">
            <div className="sa-header__intro">
                <span className="sa-header__eyebrow">
                    <DashboardIcon name="shield" size={14} />
                    Vue plateforme — SUPER_ADMIN
                </span>
                <h1 className="sa-header__title">Tableau de bord</h1>
                <p className="sa-header__subtitle">
                    Synthèse de l’activité, de la croissance et de la santé de la
                    plateforme.
                </p>
            </div>

            <div className="sa-header__actions">
                <div className="sa-segmented" role="tablist" aria-label="Période">
                    {PERIODS.map((p) => (
                        <button
                            key={p.id}
                            type="button"
                            role="tab"
                            aria-selected={period === p.id}
                            className={
                                'sa-segmented__btn' +
                                (period === p.id ? ' is-active' : '')
                            }
                            onClick={() => setPeriod(p.id)}
                        >
                            {p.label}
                        </button>
                    ))}
                </div>

                <Button
                    variant="outline"
                    size="small"
                    icon={<DashboardIcon name="sparkle" size={16} />}
                    onClick={onReload}
                    disabled={isRefreshing}
                >
                    {isRefreshing ? 'Actualisation…' : 'Actualiser'}
                </Button>
            </div>
        </header>
    );
}
