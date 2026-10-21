export interface DonutChartInput {
    id: string;
    label: string;
    value: number | bigint;
    color: 'primary' | 'neutral' | 'success' | 'warning' | 'info' | 'mint' | 'purple';
    displayValue: string;
}

export interface DonutChartArc extends DonutChartInput {
    percentage: number;
    dashLength: number;
    dashOffset: number;
}

export interface DonutChartLayout {
    total: bigint;
    arcs: DonutChartArc[];
    valid: boolean;
}

const CIRCUMFERENCE = 2 * Math.PI * 38;
const RATIO_SCALE = 1_000_000_000_000n;

/** Prépare des arcs SVG sans convertir les décomptes entiers en flottants. */
export function buildDonutChartLayout(inputs: readonly DonutChartInput[]): DonutChartLayout {
    const values = inputs.map(({ value }) => {
        if (typeof value === 'bigint') return value >= 0n ? value : null;
        if (!Number.isFinite(value) || value < 0) return null;
        if (Number.isSafeInteger(value)) return BigInt(value);
        if (value > 1_000_000) return null;

        return BigInt(Math.round(value * 1_000_000));
    });

    if (values.some((value) => value === null)) return { total: 0n, arcs: [], valid: false };

    const normalizedValues = values as bigint[];
    const total = normalizedValues.reduce((sum, value) => sum + value, 0n);
    if (total === 0n) return { total, arcs: [], valid: true };

    let offset = 0;
    const arcs = inputs.map((input, index) => {
        const scaledRatio = Number((normalizedValues[index] * RATIO_SCALE) / total) / Number(RATIO_SCALE);
        const percentage = scaledRatio * 100;
        const dashLength = CIRCUMFERENCE * scaledRatio;
        const arc: DonutChartArc = {
            ...input,
            percentage,
            dashLength,
            dashOffset: -offset,
        };
        offset += dashLength;
        return arc;
    });

    return { total, arcs, valid: true };
}

/** Valide les compteurs d'occupation et expose le taux issu des mêmes nombres. */
export function resolveOccupancyCounts(
    occupied: number,
    total: number,
    available?: number,
): { valid: true; occupied: number; available: number; total: number; rate: number | null } | { valid: false } {
    if (![occupied, total].every((value) => Number.isSafeInteger(value) && value >= 0)) return { valid: false };
    if (occupied > total) return { valid: false };

    const computedAvailable = total - occupied;
    if (available !== undefined && (!Number.isSafeInteger(available) || available < 0 || available !== computedAvailable)) {
        return { valid: false };
    }

    return {
        valid: true,
        occupied,
        available: computedAvailable,
        total,
        rate: total === 0 ? null : (occupied / total) * 100,
    };
}
