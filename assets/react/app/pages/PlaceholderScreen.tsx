// ============================================================
// assets/react/app/pages/PlaceholderScreen.tsx
// Écran minimal « section à venir ».
//
// Les écrans métier réels ne sont pas encore écrits : chaque feuille du
// menu affiche ce placeholder pour valider l'intégration du sidebar et
// du routeur sans blocage (acceptance de la présente issue).
// ============================================================

interface PlaceholderScreenProps {
    title: string;
    description?: string;
}

export function PlaceholderScreen({ title, description }: PlaceholderScreenProps) {
    return (
        <div className="placeholder-screen">
            <h1 className="placeholder-screen__title">{title}</h1>
            <p className="placeholder-screen__text">
                {description ?? 'Cette section est en cours de développement.'}
            </p>
        </div>
    );
}