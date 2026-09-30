import { useMemo, useState } from 'react';

export interface CandlestickDataPoint {
    date: string | number;
    open: number;
    high: number;
    low: number;
    close: number;
}

interface UseCandlestickChartProps {
    data: CandlestickDataPoint[];
    width?: number;
    height?: number;
    margin?: { top: number; right: number; bottom: number; left: number };
    upColor?: string;
    downColor?: string;
}

export function useCandlestickChart({
                                        data,
                                        width = 600,
                                        height = 300,
                                        // Marges augmentées pour éviter que les libellés soient coupés
                                        margin = { top: 30, right: 30, bottom: 50, left: 70 },
                                        upColor = 'var(--color-success, #2ecc71)',
                                        downColor = 'var(--color-error, #e74c3c)',
                                    }: UseCandlestickChartProps) {
    const [hoveredIndex, setHoveredIndex] = useState<number | null>(null);

    const chartWidth = width - margin.left - margin.right;
    const chartHeight = height - margin.top - margin.bottom;

    const minPrice = data.length > 0 ? Math.min(...data.map(d => d.low)) : 0;
    const maxPrice = data.length > 0 ? Math.max(...data.map(d => d.high)) : 1;

    const xStep = data.length > 0 ? chartWidth / data.length : 0;
    const candleWidth = Math.max(4, xStep * 0.7);

    const getX = (index: number) => margin.left + xStep * index + xStep / 2;
    const getY = (price: number) =>
        margin.top + ((maxPrice - price) / (maxPrice - minPrice)) * chartHeight;

    const handleMouseMove = (index: number) => setHoveredIndex(index);
    const handleMouseLeave = () => setHoveredIndex(null);

    return {
        data,
        width,
        height,
        margin,
        chartWidth,
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
        upColor,
        downColor,
    };
}
