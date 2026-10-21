import { buildDonutChartLayout, type DonutChartInput } from './donutChart';
import { useChartDisplayMode } from './useChartDisplayMode';

export interface DonutChartProps {
    segments: readonly DonutChartInput[];
    centerValue: string;
    centerLabel: string;
    totalLabel?: string;
    ariaLabel: string;
    size?: 'small' | 'medium' | 'large';
    className?: string;
}

/** Anneau SVG partagé pour les taux et répartitions métier. */
export function DonutChart({
    segments,
    centerValue,
    centerLabel,
    totalLabel,
    ariaLabel,
    size = 'medium',
    className = '',
}: DonutChartProps) {
    const layout = buildDonutChartLayout(segments);
    const [displayMode] = useChartDisplayMode();

    if (!layout.valid) {
        return <p className={`donut-chart__invalid ${className}`.trim()} role="status">Données incohérentes : graphique non calculable.</p>;
    }

    return (
        <figure className={`donut-chart donut-chart--${size}${displayMode === 'bar' ? ' donut-chart--bar' : ''} ${className}`.trim()}>
            {displayMode === 'bar' ? (
                <div className="donut-chart__bar-visual">
                    <div className="donut-chart__stacked-track" role="img" aria-label={ariaLabel}>
                        {layout.arcs.map((arc) => arc.percentage > 0 && (
                            <span
                                className={`donut-chart__stacked-segment donut-chart__stacked-segment--${arc.color}`}
                                key={arc.id}
                                style={{ width: `${arc.percentage}%` }}
                                title={`${arc.label} : ${arc.displayValue}`}
                            />
                        ))}
                    </div>
                    <span className="donut-chart__bar-summary"><strong>{centerValue}</strong><small>{centerLabel}</small></span>
                </div>
            ) : (
                <div className="donut-chart__visual" role="img" aria-label={ariaLabel}>
                    <svg className="donut-chart__svg" viewBox="0 0 100 100" aria-hidden="true" focusable="false">
                        <circle className="donut-chart__track" cx="50" cy="50" r="38" />
                        {layout.arcs.map((arc) => arc.dashLength > 0 && (
                            <circle
                                className={`donut-chart__arc donut-chart__arc--${arc.color}`}
                                key={arc.id}
                                cx="50"
                                cy="50"
                                r="38"
                                strokeDasharray={`${arc.dashLength} ${2 * Math.PI * 38 - arc.dashLength}`}
                                strokeDashoffset={arc.dashOffset}
                            />
                        ))}
                    </svg>
                    <span className="donut-chart__center">
                        <strong>{centerValue}</strong>
                        <small>{centerLabel}</small>
                    </span>
                </div>
            )}
            <figcaption className="donut-chart__details">
                <ul className="donut-chart__legend">
                    {layout.arcs.map((arc) => (
                        <li key={arc.id}>
                            <span className={`donut-chart__swatch donut-chart__swatch--${arc.color}`} aria-hidden="true" />
                            <span className="donut-chart__legend-label">{arc.label}</span>
                            <strong>{arc.displayValue}</strong>
                        </li>
                    ))}
                </ul>
                {totalLabel && <small className="donut-chart__total">{totalLabel}</small>}
            </figcaption>
        </figure>
    );
}
