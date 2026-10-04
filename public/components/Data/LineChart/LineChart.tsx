import React, { useId, useLayoutEffect, useRef, useState } from 'react';
import '../../../../assets/styles/components/Data/_line-chart.scss';
import {LineChartDataPoint, useLineChart} from "../../../../assets/react/hook-components/Data/LineChart/useLineChart.ts";


interface LineChartProps {
    data: LineChartDataPoint[];
    height?: number;
    margin?: { top: number; right: number; bottom: number; left: number };
    color?: string;
    showAxis?: boolean;
    formatDate?: (date: string | number) => string;
    formatValue?: (value: number) => string;
}

export function LineChart({
                              data,
                              height = 300,
                              margin,
                              color = 'var(--color-primary, #2c7a7b)',
                              showAxis = true,
                              formatDate = (d) => String(d),
                              formatValue = (p) => p.toFixed(2),
                          }: LineChartProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const svgRef = useRef<SVGSVGElement>(null);
    const gradientId = useId();
    const [chartWidth, setChartWidth] = useState<number>(0);

    useLayoutEffect(() => {
        if (!containerRef.current) return;
        const measure = () => {
            setChartWidth(containerRef.current?.clientWidth ?? 0);
        };
        measure();

        const resizeObserver = new ResizeObserver(measure);
        resizeObserver.observe(containerRef.current);

        return () => resizeObserver.disconnect();
    }, []);

    const finalWidth = chartWidth > 0 ? chartWidth : 600;

    const {
        width: w,
        height: h,
        margin: m,
        chartHeight,
        minValue,
        maxValue,
        getY,
        coords,
        linePath,
        areaPath,
        hoveredIndex,
        handleMouseMove,
        handleMouseLeave,
    } = useLineChart({
        data,
        width: finalWidth,
        height,
        margin,
    });

    if (data.length === 0) {
        return <div className="line-chart__empty">{('Aucune donnée')}</div>;
    }

    const dateLabelStep = Math.max(1, Math.ceil(data.length / 6));
    const midValue = (minValue + maxValue) / 2;

    const onSvgMouseMove = (e: React.MouseEvent<SVGSVGElement>) => {
        const rect = svgRef.current?.getBoundingClientRect();
        if (!rect) return;
        handleMouseMove(e.clientX, rect);
    };

    const hoveredPoint = hoveredIndex !== null ? coords[hoveredIndex] : null;

    return (
        <div
            ref={containerRef}
            className="line-chart"
            style={{ height: h }}
            onMouseLeave={handleMouseLeave}
        >
            <svg ref={svgRef} width={w} height={h} onMouseMove={onSvgMouseMove}>
                <defs>
                    <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={color} stopOpacity="0.28" />
                        <stop offset="100%" stopColor={color} stopOpacity="0" />
                    </linearGradient>
                </defs>

                {/* Lignes de grille horizontales */}
                {showAxis && (
                    <g className="line-chart__grid">
                        {[maxValue, midValue, minValue].map((value, index) => (
                            <line
                                key={`grid-${index}`}
                                x1={m.left}
                                x2={w - m.right}
                                y1={getY(value)}
                                y2={getY(value)}
                            />
                        ))}
                    </g>
                )}

                {/* Étiquettes verticales (valeurs) */}
                {showAxis && (
                    <g className="line-chart__axis">
                        {[maxValue, midValue, minValue].map((value, index) => (
                            <text
                                key={`axis-${index}`}
                                x={m.left - 8}
                                y={getY(value) + 4}
                                textAnchor="end"
                                fontSize="10"
                                fill="currentColor"
                            >
                                {formatValue(value)}
                            </text>
                        ))}
                    </g>
                )}

                {/* Étiquettes horizontales (dates) */}
                {showAxis && (
                    <g className="line-chart__axis">
                        {data.map((d, i) =>
                            i % dateLabelStep === 0 ? (
                                <text
                                    key={i}
                                    x={coords[i].x}
                                    y={h - 10}
                                    textAnchor="middle"
                                    fontSize="10"
                                    fill="currentColor"
                                >
                                    {formatDate(d.date)}
                                </text>
                            ) : null
                        )}
                    </g>
                )}

                {/* Aire dégradée sous la courbe */}
                {areaPath && <path d={areaPath} fill={`url(#${gradientId})`} />}

                {/* Courbe lissée */}
                {linePath && <path d={linePath} className="line-chart__line" />}

                {/* Points */}
                {coords.map((point, i) => (
                    <circle
                        key={i}
                        cx={point.x}
                        cy={point.y}
                        r={hoveredIndex === i ? 5 : 3}
                        className={
                            hoveredIndex === i
                                ? 'line-chart__dot line-chart__dot--active'
                                : 'line-chart__dot'
                        }
                    />
                ))}

                {/* Guidage vertical au survol */}
                {hoveredPoint && (
                    <line
                        x1={hoveredPoint.x}
                        x2={hoveredPoint.x}
                        y1={m.top}
                        y2={m.top + chartHeight}
                        className="line-chart__guide"
                    />
                )}
            </svg>

            {/* Tooltip */}
            {hoveredPoint && (
                <div
                    className="line-chart__tooltip"
                    style={{
                        left: Math.min(Math.max(hoveredPoint.x, 80), w - 80),
                        top: Math.max(hoveredPoint.y - 14, 8),
                    }}
                >
                    <div className="line-chart__tooltip-date">
                        {formatDate(data[hoveredIndex!].date)}
                    </div>
                    <div className="line-chart__tooltip-value">
                        {formatValue(data[hoveredIndex!].value)}
                    </div>
                </div>
            )}
        </div>
    );
}
