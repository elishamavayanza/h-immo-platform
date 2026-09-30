import { useMemo, useState } from 'react';

export interface LineChartDataPoint {
    date: string | number;
    value: number;
}

export interface XYPoint extends LineChartDataPoint {
    x: number;
    y: number;
}

interface UseLineChartProps {
    data: LineChartDataPoint[];
    width?: number;
    height?: number;
    margin?: { top: number; right: number; bottom: number; left: number };
}

/**
 * Construit une courbe lissée (Catmull-Rom → Bézier cubique) depuis les points.
 */
function buildSmoothPath(points: XYPoint[]): string {
    if (points.length < 2) {
        return points.length === 1 ? `M ${points[0].x} ${points[0].y}` : '';
    }

    let d = `M ${points[0].x} ${points[0].y}`;

    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i - 1] ?? points[i];
        const p1 = points[i];
        const p2 = points[i + 1];
        const p3 = points[i + 2] ?? p2;

        const cp1x = p1.x + (p2.x - p0.x) / 6;
        const cp1y = p1.y + (p2.y - p0.y) / 6;
        const cp2x = p2.x - (p3.x - p1.x) / 6;
        const cp2y = p2.y - (p3.y - p1.y) / 6;

        d += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${p2.x} ${p2.y}`;
    }

    return d;
}

export function useLineChart({
                                 data,
                                 width = 600,
                                 height = 300,
                                 margin = { top: 24, right: 24, bottom: 42, left: 56 },
                             }: UseLineChartProps) {
    const [hoveredIndex, setHoveredIndex] = useState<number | null>(null);

    const chartWidth = width - margin.left - margin.right;
    const chartHeight = height - margin.top - margin.bottom;

    const values = data
        .map((point) => Number(point.value))
        .filter((value) => Number.isFinite(value));
    const minValue = values.length > 0 ? Math.min(...values) : 0;
    const maxValue = values.length > 0 ? Math.max(...values) : 1;
    const valueRange = maxValue - minValue;
    // Une série constante (ex. 1.26, 1.26) ne doit pas créer une division par zéro.
    const pad = valueRange > 0 ? valueRange * 0.08 : Math.max(Math.abs(maxValue) * 0.08, 1);
    const paddedMin = minValue - pad;
    const paddedMax = maxValue + pad;

    const xStep = data.length > 1 ? chartWidth / (data.length - 1) : 0;

    const getX = (index: number) => margin.left + xStep * index;
    const getY = (value: number) => {
        const numericValue = Number(value);
        const safeValue = Number.isFinite(numericValue) ? numericValue : minValue;

        return margin.top + ((paddedMax - safeValue) / (paddedMax - paddedMin)) * chartHeight;
    };

    const coords: XYPoint[] = useMemo(
        () => data.map((d, i) => {
            const numericValue = Number(d.value);
            const value = Number.isFinite(numericValue) ? numericValue : minValue;

            return { ...d, value, x: getX(i), y: getY(value) };
        }),
        [data, width, height, margin.left, margin.right, margin.top, margin.bottom, minValue, paddedMax, paddedMin, chartWidth, chartHeight]
    );

    const linePath = useMemo(() => buildSmoothPath(coords), [coords]);
    const areaPath = useMemo(() => {
        if (coords.length === 0) return '';
        const baseline = margin.top + chartHeight;
        return `${linePath} L ${coords[coords.length - 1].x} ${baseline} L ${coords[0].x} ${baseline} Z`;
    }, [linePath, coords, margin.top, chartHeight]);

    const getHoveredIndex = (clientX: number, rect: { left: number; width: number }): number => {
        if (data.length === 0) return 0;
        const svgX = ((clientX - rect.left) / Math.max(rect.width, 1)) * width;
        const index = Math.round((svgX - margin.left) / Math.max(xStep, 1));
        return Math.min(Math.max(index, 0), data.length - 1);
    };

    const handleMouseMove = (clientX: number, rect: { left: number; width: number }) => {
        setHoveredIndex(getHoveredIndex(clientX, rect));
    };

    const handleMouseLeave = () => setHoveredIndex(null);

    return {
        data,
        width,
        height,
        margin,
        chartWidth,
        chartHeight,
        minValue,
        maxValue,
        xStep,
        getY,
        coords,
        linePath,
        areaPath,
        hoveredIndex,
        handleMouseMove,
        handleMouseLeave,
    };
}
