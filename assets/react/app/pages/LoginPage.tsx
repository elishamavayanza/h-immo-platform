// ============================================================
// assets/react/app/pages/LoginPage.tsx
// Écran de connexion — VERSION PROVISOIRE.
//
// L'écran de connexion final (formulaire `POST /api/auth/login` via
// `useAuth().login`) sort du périmètre de la présente issue (sidebar
// piloté par rôle). Ce placeholder permet au routeur et à la garde
// « authentifié » d'exister sans page blanche.
// ============================================================

export function LoginPage() {
    return (
        <div className="login-page">
            <h1 className="login-page__title">H-Immo</h1>
            <p className="login-page__text">
                L&apos;écran de connexion sera intégré prochainement. Rendez-vous sur l&apos;API
                (`POST /api/auth/login`) en attendant.
            </p>
        </div>
    );
}