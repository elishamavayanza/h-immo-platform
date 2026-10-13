/**
 * Périodes rapides des rapports.
 *
 * Logique pure, sans React ni DOM, afin d'être vérifiable hors navigateur
 * (`tests/verify-report-periods.ts`). Les bornes sont calculées dans le
 * calendrier **local** de l'utilisateur : « ce mois-ci » doit correspondre au
 * mois affiché sur son calendrier, pas au mois UTC, sinon un utilisateur à
 * Kinshasa (UTC+1) verrait un basculement de mois décalé de quelques heures.
 * Le format produit est ISO `YYYY-MM-DD`, celui attendu par l'API.
 */
export type PeriodPresetId = 'today' | 'this_week' | 'this_month' | 'last_month' | 'this_quarter' | 'this_year';

export interface PeriodRange {
    from: string;
    to: string;
}

/** Vrai uniquement si les deux dates sont définies et dans l'ordre. */
export function isValidPeriodRange(range: PeriodRange): boolean {
    const isCalendarDate = (value: string): boolean => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
        const parsed = new Date(`${value}T00:00:00.000Z`);
        return !Number.isNaN(parsed.getTime()) && parsed.toISOString().slice(0, 10) === value;
    };

    return isCalendarDate(range.from) && isCalendarDate(range.to) && range.from <= range.to;
}

export const PERIOD_PRESETS: ReadonlyArray<{ id: PeriodPresetId; label: string }> = [
    { id: 'today', label: 'Aujourd’hui' },
    { id: 'this_week', label: 'Cette semaine' },
    { id: 'this_month', label: 'Ce mois-ci' },
    { id: 'last_month', label: 'Mois dernier' },
    { id: 'this_quarter', label: 'Ce trimestre' },
    { id: 'this_year', label: 'Cette année' },
];

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

function toIsoDate(year: number, monthIndex0: number, day: number): string {
    return `${year}-${pad(monthIndex0 + 1)}-${pad(day)}`;
}

/** Dernier jour du mois (1er du mois suivant moins un jour). */
function lastDayOfMonth(year: number, monthIndex0: number): number {
    return new Date(year, monthIndex0 + 1, 0).getDate();
}

/** Bornes ISO d'une période rapide pour la date de référence donnée. */
export function computePeriodPreset(preset: PeriodPresetId, reference: Date = new Date()): PeriodRange {
    const year = reference.getFullYear();
    const month = reference.getMonth();
    const day = reference.getDate();

    switch (preset) {
        case 'today':
            return { from: toIsoDate(year, month, day), to: toIsoDate(year, month, day) };
        case 'this_week': {
            const mondayOffset = (reference.getDay() + 6) % 7;
            const monday = new Date(year, month, day - mondayOffset);
            const sunday = new Date(year, month, day + (6 - mondayOffset));

            return {
                from: toIsoDate(monday.getFullYear(), monday.getMonth(), monday.getDate()),
                to: toIsoDate(sunday.getFullYear(), sunday.getMonth(), sunday.getDate()),
            };
        }
        case 'this_month':
            return {
                from: toIsoDate(year, month, 1),
                to: toIsoDate(year, month, lastDayOfMonth(year, month)),
            };
        case 'last_month': {
            const isJanuary = month === 0;
            const targetMonth = isJanuary ? 11 : month - 1;
            const targetYear = isJanuary ? year - 1 : year;

            return {
                from: toIsoDate(targetYear, targetMonth, 1),
                to: toIsoDate(targetYear, targetMonth, lastDayOfMonth(targetYear, targetMonth)),
            };
        }
        case 'this_quarter': {
            const startMonth = Math.floor(month / 3) * 3;
            const endMonth = startMonth + 2;

            return {
                from: toIsoDate(year, startMonth, 1),
                to: toIsoDate(year, endMonth, lastDayOfMonth(year, endMonth)),
            };
        }
        case 'this_year':
            return { from: toIsoDate(year, 0, 1), to: toIsoDate(year, 11, 31) };
    }
}

/** `2026-09-01` → `01/09/2026` (affichage français). */
export function formatIsoDate(value: string): string {
    const [year, month, day] = value.split('-');

    if (!year || !month || !day) {
        return value;
    }

    return `${day}/${month}/${year}`;
}

/** Libellé lisible de la période affichée, bornes manquantes en pointillés. */
export function formatPeriodLabel(range: PeriodRange): string {
    if (!range.from && !range.to) {
        return 'Période complète';
    }

    return `${range.from ? formatIsoDate(range.from) : '…'} → ${range.to ? formatIsoDate(range.to) : '…'}`;
}

/**
 * Identifie la période rapide correspondant exactement à un intervalle, ou
 * `null` pour une période personnalisée. Sert à mettre en évidence le bouton
 * actif sans dupliquer la règle de calcul.
 */
export function findMatchingPreset(from: string, to: string, reference: Date = new Date()): PeriodPresetId | null {
    if (!from && !to) {
        return null;
    }

    for (const preset of PERIOD_PRESETS) {
        const range = computePeriodPreset(preset.id, reference);

        if (range.from === from && range.to === to) {
            return preset.id;
        }
    }

    return null;
}
