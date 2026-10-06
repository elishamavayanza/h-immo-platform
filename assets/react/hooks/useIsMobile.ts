import { MEDIA_QUERIES } from '../services/device';
import { useMediaQuery } from './useMediaQuery';

/**
 * Vrai quand le layout passe en « tiroir » (sidebar off-canvas, fermé
 * par défaut) : largeur < 768px (mobile/tablette compacte) OU orientation
 * portrait. Un écran portrait de 768–1023px de large (tablette, pliable)
 * doit se comporter comme un mobile — sinon son sidebar statique ne peut
 * jamais être fermé. Aligné sur les `@media` CSS du tiroir (même liste
 * de conditions dans `_Sidebar.scss` / `MainLayout.scss`).
 */
export function useIsMobile(): boolean {
    return useMediaQuery(MEDIA_QUERIES.drawer);
}