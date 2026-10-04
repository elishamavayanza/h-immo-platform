import React, { useLayoutEffect, useRef, useState } from 'react';
//  Import du style décommenté
import '../../../../assets/styles/components/Data/_candlestick-chart.scss';
import {
    CandlestickDataPoint,
    useCandlestickChart
} from "../../../../assets/react/hook-components/Data/CandlestickChart/useCandlestickChart.ts";

interface CandlestickChartProps {
    data: CandlestickDataPoint[];
    height?: number;
    margin?: { top: number; right: number; bottom: number; left: number };
    upColor?: string;
    downColor?: string;
    showAxis?: boolean;
    formatDate?: (date: string | number) => string;
    formatPrice?: (price: number) => string;
}

export function CandlestickChart({
                                     data,
                                     height = 300,
                                     margin,
                                     upColor,
                                     downColor,
                                     showAxis = true,
                                     formatDate = (d) => String(d),
                                     formatPrice = (p) => p.toFixed(2),
                                 }: CandlestickChartProps) {
    const containerRef = useRef<HTMLDivElement>(null);
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
        chartWidth: innerChartWidth,
        chartHeight,
        minPrice,
        maxPrice,
        xStep,
        candleWidth,
        getX,
        getY,
        hoveredIndex,
        handleMouseMove,
        handleMouseLeave,
        upColor: up,
        downColor: down,
    } = useCandlestickChart({
        data,
        width: finalWidth,
        height,
        margin,
        upColor,
        downColor,
    });

    if (data.length === 0) {
        return <div className="candlestick-chart__empty">{('Aucune donnée')}</div>;
    }

    return (
        <div
            ref={containerRef}
            className="candlestick-chart"
            style={{ height: h }}
            onMouseLeave={handleMouseLeave}
        >
            <svg width={w} height={h}>
                {/* Axe horizontal (dates) */}
                {showAxis && (
                    <g className="candlestick-chart__axis">
                        {data.map((d, i) =>
                            i % Math.ceil(data.length / 6) === 0 ? (
                                <text
                                    key={i}
                                    x={getX(i)}
                                    y={h - 12} // plus haut pour ne pas être coupé
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

                {/* Axe vertical (prix) */}
                {showAxis && (
                    <g className="candlestick-chart__axis">
                        {[maxPrice, (maxPrice + minPrice) / 2, minPrice].map((price, idx) => (
                            <text
                                key={idx}
                                x={m.left - 10} // plus à gauche
                                y={getY(price) + 4}
                                textAnchor="end"
                                fontSize="10"
                                fill="currentColor"
                            >
                                {formatPrice(price)}
                            </text>
                        ))}
                    </g>
                )}

                {/* Chandeliers */}
                {data.map((d, i) => {
                    const isUp = d.close >= d.open;
                    const color = isUp ? up : down;
                    const x = getX(i) - candleWidth / 2;
                    const yOpen = getY(d.open);
                    const yClose = getY(d.close);
                    const yHigh = getY(d.high);
                    const yLow = getY(d.low);

                    const bodyTop = Math.min(yOpen, yClose);
                    const bodyHeight = Math.max(Math.abs(yOpen - yClose), 1);

                    return (
                        <g
                            key={i}
                            onMouseEnter={() => handleMouseMove(i)}
                            className="candlestick-chart__candle"
                            style={{ cursor: 'pointer' }}
                        >
                            <line
                                x1={getX(i)}
                                x2={getX(i)}
                                y1={yHigh}
                                y2={yLow}
                                stroke={color}
                                strokeWidth={1}
                            />
                            <rect
                                x={x}
                                y={bodyTop}
                                width={candleWidth}
                                height={bodyHeight}
                                fill={color}
                                rx={1}
                                stroke="none"
                            />
                        </g>
                    );
                })}
            </svg>

            {/* Tooltip */}
            {hoveredIndex !== null && (
                <div
                    className="tooltip tooltip--top candlestick-chart__tooltip"
                    style={{
                        left: Math.min(Math.max(getX(hoveredIndex), 80), w - 80),
                        top: Math.max(getY(data[hoveredIndex].high) - 10, 10),
                    }}
                >
                    <div className="candlestick-chart__tooltip-date">
                        {formatDate(data[hoveredIndex].date)}
                    </div>
                    <div>Open : {formatPrice(data[hoveredIndex].open)}</div>
                    <div>High : {formatPrice(data[hoveredIndex].high)}</div>
                    <div>Low : {formatPrice(data[hoveredIndex].low)}</div>
                    <div>Close : {formatPrice(data[hoveredIndex].close)}</div>
                </div>
            )}
        </div>
    );
}
