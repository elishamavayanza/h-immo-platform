/**
 * patrimoineRouting.ts — Résolution du niveau Patrimoine et construction des
 * URL de drill-down.
 *
 * Isolé du composant `PatrimoineNav` (qui importe React) pour rester une
 * fonction pure testable sans navigateur : c'est l'autorité sur « quel niveau
 * l'URL décrit » et « quelle URL ouvre quel niveau ». Aucun filtre serveur ni
 * autorisation n'en découle — le backend reste l'autorité.
 */

export type PatrimoineLevel = 'overview' | 'villes' | 'parcelles' | 'batiments' | 'unites';

export interface PatrimoineContext {
    cityId: string | null;
    parcelId: string | null;
    buildingId: string | null;
    /** UUID de l'unité ; `null` pour un contexte bâtiment, absent si seul le niveau compte. */
    unitId?: string | null;
}

export interface PatrimoineSelection {
    level: PatrimoineLevel;
    cityId: string | null;
    parcelId: string | null;
    buildingId: string | null;
    unitId: string | null;
}

/**
 * Déduit le niveau et le contexte de sélection depuis le chemin.
 *
 * Le segment `unites` apparaît après le bâtiment : sa valeur suivante, quand
 * elle existe, est l'unité ; sans valeur il décrit le bâtiment lui-même.
 */
export function resolvePatrimoineSelection(pathname: string, isCityAdmin: boolean): PatrimoineSelection {
    const parts = pathname.split('/').filter(Boolean);
    const valueAfter = (segment: string) => {
        const index = parts.indexOf(segment);
        return index >= 0 ? parts[index + 1] ?? null : null;
    };
    const cityId = valueAfter('villes');
    const parcelId = valueAfter('parcelles');
    const buildingId = valueAfter('batiments');
    const unitId = valueAfter('unites');
    const level: PatrimoineLevel = unitId || buildingId || parts.includes('unites')
        ? 'unites'
        : parcelId || parts.includes('batiments')
            ? 'batiments'
            : cityId || parts.includes('parcelles')
                ? 'parcelles'
                : parts.includes('villes')
                    ? 'villes'
                    : isCityAdmin ? 'parcelles' : 'overview';

    return { level, cityId, parcelId, buildingId, unitId };
}

/**
 * Construit l'URL d'un niveau à partir de son contexte.
 *
 * Les segments présents dans le contexte composent le chemin ; `unitId` prime
 * sur le niveau `unites` nu (bâtiment). `encodeURIComponent` protège l'UUID.
 */
export function buildPatrimoinePath(level: PatrimoineLevel, context: PatrimoineContext): string {
    const segments = ['/app/patrimoine'];
    if (context.cityId) segments.push('villes', encodeURIComponent(context.cityId));
    if (context.parcelId) segments.push('parcelles', encodeURIComponent(context.parcelId));
    if (context.buildingId) segments.push('batiments', encodeURIComponent(context.buildingId));
    if (context.unitId) segments.push('unites', encodeURIComponent(context.unitId));
    else if (level === 'unites') segments.push('unites');
    if (segments.length === 1 && level !== 'overview') segments.push(level);
    return segments.join('/');
}
