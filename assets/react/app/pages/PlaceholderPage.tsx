// ============================================================
// assets/react/app/pages/PlaceholderPage.tsx
// Page provisoire d'une feuille du back-office.
// ============================================================
//
// Les pages métier n'existent pas encore : `features/<rôle>/` est vide.
// Plutôt que de laisser un lien mort — ou une redirection en boucle, ce
// qu'un chemin non routé provoquait — chaque feuille du menu affiche son
// libellé et sa route. Le jour où une page réelle arrive, il suffit de
// remplacer l'`element` du `<Route>` correspondant dans `AppRoutes.tsx` :
// le menu, la garde et le test de non-régression ne bougent pas.
//
// ⚠️ Cette page n'affiche AUCUNE donnée : elle n'appelle pas l'API.
// ============================================================

export interface PlaceholderPageProps {
    /** Libellé de la feuille, repris du menu. */
    label: string;
    /** Chemin absolu, affiché pour repérage. */
    path: string;
}

export function PlaceholderPage({ label, path }: PlaceholderPageProps) {
    return (
        <section className="placeholder-page">
            <p className="placeholder-page__path">{path}</p>
            <h1 className="placeholder-page__title">{label}</h1>
            <p className="placeholder-page__text">
                Cet écran est en cours de construction : il sera relié aux données de
                l’API.
            </p>
        </section>
    );
}
