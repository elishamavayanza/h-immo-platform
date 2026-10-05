/**
 * Distance entre le bouton parent et le panneau qui s'ouvre.
 *
 * Elle vit dans un module neutre plutôt que dans le composant ou dans le
 * hook : c'est la même valeur des deux côtés — le placement du panneau
 * (gauche du hook `useSidebar`) et le calcul de la fermeture différée (droite
 * du composant). Deux constantes distinctes divergeraient au premier ajustement
 * du design, et le flyout se mettrait à fermer plus vite que sa propre
 * animation d'ouverture.
 *
 * Elle duplique `$sidebar-flyout-offset` du SCSS, qui n'est pas lisible depuis
 * TypeScript. Toute modification doit être faite ici ET dans
 * `assets/styles/baseVariables/_variables.scss`.
 */
export const FLYOUT_OFFSET = 8;

/**
 * Délai avant fermeture quand le pointeur quitte le panneau.
 *
 * Le panneau est portalé : un vide physique sépare le bouton parent de son bord
 * gauche. Sans temporisation, traverser ce vide en diagonale — le geste naturel
 * — fermerait le panneau avant d'y arriver. Le hook annule ce délai dès que le
 * pointeur entre dans le panneau, ce qui rend le passage fiable sans fond
 * transparent qui laisserait passer les clics.
 */
export const FLYOUT_CLOSE_DELAY_MS = 220;
