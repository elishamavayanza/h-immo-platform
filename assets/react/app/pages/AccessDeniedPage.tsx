// ============================================================
// assets/react/app/pages/AccessDeniedPage.tsx
// Page « accès non prévu ».
//
// Affichée par la garde de route (RequireRole) lorsqu'une URL n'appartient
// pas au menu du rôle courant (ex. un ADMIN_VILLE sur `/app/administration/equipe`).
// Refus d'AFFICHAGE uniquement : la protection réelle reste côté API.
// ============================================================

export function AccessDeniedPage() {
    return (
        <div className="access-denied-page">
            <h1 className="access-denied-page__title">Accès non prévu</h1>
            <p className="access-denied-page__text">
                Votre rôle ne permet pas d&apos;accéder à cette section du menu. Si vous pensez
                qu&apos;il s&apos;agit d&apos;une erreur, contactez le responsable de votre organisation.
            </p>
        </div>
    );
}