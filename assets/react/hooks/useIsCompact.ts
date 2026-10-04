import { MEDIA_QUERIES } from '../services/device';
import { useMediaQuery } from './useMediaQuery';

/**
 * True quand l'espace impose un layout compact : mobile + tablette,
 * soit largeur < 1024 px (seuil desktop). Les panneaux passent alors
 * en drawer / plein écran, les densités se réduisent.
 */
export function useIsCompact(): boolean {
    return useMediaQuery(MEDIA_QUERIES.compact);
}