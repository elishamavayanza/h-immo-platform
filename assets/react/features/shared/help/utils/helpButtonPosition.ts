export interface HelpButtonPosition {
    /** Position normalisée du centre du bouton, entre 0 et 1. */
    x: number;
    y: number;
}

export interface HelpButtonPixels {
    x: number;
    y: number;
}

export const HELP_BUTTON_SIZE = 48;
const VIEWPORT_GUTTER = 12;

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), Math.max(min, max));
}

export function normalizeHelpButtonPosition(x: number, y: number, width: number, height: number, size = HELP_BUTTON_SIZE): HelpButtonPosition {
    const gutter = size / 2 + VIEWPORT_GUTTER;
    return {
        x: clamp(x, gutter, width - gutter) / Math.max(width, 1),
        y: clamp(y, gutter, height - gutter) / Math.max(height, 1),
    };
}

export function helpButtonPositionToPixels(position: HelpButtonPosition, width: number, height: number, size = HELP_BUTTON_SIZE): HelpButtonPixels {
    const gutter = size / 2 + VIEWPORT_GUTTER;
    return {
        x: clamp(position.x * width, gutter, width - gutter),
        y: clamp(position.y * height, gutter, height - gutter),
    };
}

export function parseHelpButtonPosition(value: string | null): HelpButtonPosition | null {
    if (!value) return null;
    try {
        const parsed: unknown = JSON.parse(value);
        if (typeof parsed !== 'object' || parsed === null || !('x' in parsed) || !('y' in parsed)) return null;
        const { x, y } = parsed as { x: unknown; y: unknown };
        if (typeof x !== 'number' || typeof y !== 'number' || !Number.isFinite(x) || !Number.isFinite(y)) return null;
        return { x: Math.min(Math.max(x, 0), 1), y: Math.min(Math.max(y, 0), 1) };
    } catch {
        return null;
    }
}
