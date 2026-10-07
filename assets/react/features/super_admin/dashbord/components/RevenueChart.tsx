// ============================================================
// Graphique « revenu + abonnements » 12 mois.
// SVG inline : aucune dépendance externe, thème piloté par CSS.
// ============================================================

import { useMemo } from 'react';

import type { RevenuePoint } from '../types';

export interface RevenueChartProps {
    series: RevenuePoint[];
}

const W = 760;
const H = 280;
const PAD = { top: 24, right: 22, bottom: 40, left: 52 } as const;
const INNER_W = W - PAD.left - PAD.right;
const INNER_H = H - PAD.top - PAD.bottom;

function buildPath(points: Array<[number, number]>): string {
    return points.map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x},${y}`).join(' ');
}

export function RevenueChart({ series }: RevenueChartProps) {
    const { linePath, areaPath, subPath, dots, yTicks } = useMemo(() => {
        const max = Math.max(
            ...series.map((p) => Math.max(p.revenue, p.subscriptions * 220)),
        );
        const step = max / 4;

        const xFor = (i: number) =>
            PAD.left + (INNER_W * i) / Math.max(series.length - 1, 1);
        const yFor = (v: number) => PAD.top + INNER_H - (INNER_H * v) / max;

        const revPts: Array<[number, number]> = series.map((p, i) => [
            xFor(i),
            yFor(p.revenue),
        ]);
        const subPts: Array<[number, number]> = series.map((p, i) => [
            xFor(i),
            yFor(p.subscriptions * 220),
        ]);

        const line = buildPath(revPts);
        const area =
            `M${PAD.left},${PAD.top + INNER_H} ` +
            revPts.map(([x, y]) => `L${x},${y}`).join(' ') +
            ` L${PAD.left + INNER_W},${PAD.top + INNER_H} Z`;

        const ticks = Array.from({ length: 5 }, (_, i) => ({
            y: PAD.top + INNER_H - (INNER_H * (step * i)) / max,
            value: Math.round(step * i),
        }));

        return {
            linePath: line,
            areaPath: area,
            subPath: buildPath(subPts),
            dots: revPts,
            yTicks: ticks,
        };
    }, [series]);

    return (
        <div className="sa-chart">
            <svg
                viewBox={`0 0 ${W} ${H}`}
                className="sa-chart__svg"
                role="img"
                aria-label="Évolution du revenu et des abonnements"
            >
                <defs>
                    <linearGradient id="sa-area" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="var(--sa-primary)" stopOpacity="0.42" />
                        <stop offset="100%" stopColor="var(--sa-primary)" stopOpacity="0" />
                    </linearGradient>
                    <linearGradient id="sa-line" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0%" stopColor="var(--sa-primary)" />
                        <stop offset="100%" stopColor="var(--sa-accent)" />
                    </linearGradient>
                </defs>

                {yTicks.map((t) => (
                    <g key={t.y}>
                        <line
                            x1={PAD.left}
                            x2={W - PAD.right}
                            y1={t.y}
                            y2={t.y}
                            className="sa-chart__grid"
                        />
                        <text x={PAD.left - 10} y={t.y + 4} className="sa-chart__tick" textAnchor="end">
                            {t.value >= 1000 ? `${Math.round(t.value / 1000)}k` : t.value}
                        </text>
                    </g>
                ))}

                <path d={areaPath} fill="url(#sa-area)" />
                <path d={subPath} className="sa-chart__sub" />
                <path d={linePath} className="sa-chart__line" stroke="url(#sa-line)" />

                {dots.map(([x, y], i) => (
                    <g key={i} className="sa-chart__dot-group">
                        <circle cx={x} cy={y} r="4" className="sa-chart__dot" />
                        <circle cx={x} cy={y} r="8" className="sa-chart__dot-halo" />
                    </g>
                ))}

                {series.map((p, i) => (
                    <text
                        key={p.label}
                        x={PAD.left + (INNER_W * i) / Math.max(series.length - 1, 1)}
                        y={H - 12}
                        className="sa-chart__tick"
                        textAnchor="middle"
                    >
                        {p.label}
                    </text>
                ))}
            </svg>

            <div className="sa-chart__legend">
                <span className="sa-legend">
                    <i className="sa-legend__swatch sa-legend__swatch--primary" />
                    Revenu mensuel (USD)
                </span>
                <span className="sa-legend">
                    <i className="sa-legend__swatch sa-legend__swatch--accent" />
                    Abonnements actifs
                </span>
            </div>
        </div>
    );
}
