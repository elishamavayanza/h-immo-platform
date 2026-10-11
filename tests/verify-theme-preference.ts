import { resolveAccentPalette, resolveTheme, type ThemePreference } from '../assets/react/services/userPreferences.ts';

const cases: Array<[ThemePreference, boolean, 'light' | 'dark']> = [
    ['light', false, 'light'],
    ['light', true, 'light'],
    ['dark', false, 'dark'],
    ['dark', true, 'dark'],
    ['system', false, 'dark'],
    ['system', true, 'light'],
];

for (const [preference, systemPrefersLight, expected] of cases) {
    const actual = resolveTheme(preference, systemPrefersLight);
    if (actual !== expected) {
        throw new Error(`Préférence ${preference} avec système clair=${systemPrefersLight}: ${actual}, attendu ${expected}`);
    }
}

console.log(`Thème utilisateur : ${cases.length} contrôles passés.`);

const paletteCases: Array<[unknown, 'ocean' | 'violet' | 'sage' | 'amber' | 'coral' | 'slate']> = [
    ['ocean', 'ocean'],
    ['violet', 'violet'],
    ['sage', 'sage'],
    ['amber', 'amber'],
    ['coral', 'coral'],
    ['slate', 'slate'],
    ['invalid', 'ocean'],
    [null, 'ocean'],
];

for (const [value, expected] of paletteCases) {
    const actual = resolveAccentPalette(value);
    if (actual !== expected) {
        throw new Error(`Palette ${String(value)}: ${actual}, attendu ${expected}`);
    }
}

console.log(`Palettes d’accent : ${paletteCases.length} contrôles passés.`);
