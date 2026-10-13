import { useMemo } from 'react';

import { DateRangePicker } from '../../../../components/Forms/DateRangePicker';
import {
    PERIOD_PRESETS,
    computePeriodPreset,
    findMatchingPreset,
} from '../periodPresets';
import type { PeriodRange } from '../periodPresets';

export interface ReportPeriodFilterProps {
    /** Borne basse appliquée, format ISO `YYYY-MM-DD` (vide = non filtrée). */
    periodFrom: string;
    /** Borne haute appliquée, format ISO `YYYY-MM-DD` (vide = non filtrée). */
    periodTo: string;
    onChange: (range: PeriodRange) => void;
    disabled?: boolean;
    className?: string;
}

/**
 * Sélecteur de période commun aux rapports des trois rôles.
 *
 * Deux niveaux complémentaires : des périodes rapides (ce mois-ci, mois
 * dernier, ce trimestre, cette année) pour les cas courants, et une période
 * personnalisée via `DateRangePicker`. Le composant est **contrôlé** : il
 * n'applique pas lui-même le filtre, il remonte la période choisie afin que
 * l'écran et l'export PDF partagent exactement les mêmes bornes. Le bouton
 * actif est déduit des bornes reçues, sans état interne redondant.
 */
export function ReportPeriodFilter({
    periodFrom,
    periodTo,
    onChange,
    disabled = false,
    className,
}: ReportPeriodFilterProps) {
    const activePreset = useMemo(
        () => findMatchingPreset(periodFrom, periodTo),
        [periodFrom, periodTo],
    );

    const classes = ['report-period-filter', className].filter(Boolean).join(' ');

    return (
        <div className={classes}>
            <div className="report-period-filter__presets" role="group" aria-label="Périodes rapides">
                {PERIOD_PRESETS.map((preset) => {
                    const isActive = activePreset === preset.id;

                    return (
                        <button
                            key={preset.id}
                            type="button"
                            className={
                                'report-period-filter__preset'
                                + (isActive ? ' report-period-filter__preset--active' : '')
                            }
                            aria-pressed={isActive}
                            disabled={disabled}
                            onClick={() => onChange(computePeriodPreset(preset.id))}
                        >
                            {preset.label}
                        </button>
                    );
                })}
            </div>
            <DateRangePicker
                startDate={periodFrom}
                endDate={periodTo}
                fullWidth
                disabled={disabled}
                onChange={(range) => onChange({ from: range.startDate, to: range.endDate })}
            />
        </div>
    );
}
