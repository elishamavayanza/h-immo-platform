// ============================================================
// assets/react/features/auth/pages/ResetPasswordPage.tsx
// Page de réinitialisation (route `/reset-password`, hors session, sous
// `AuthLayout`). Cible du lien contenu dans l'email « mot de passe
// oublié », qui délivre le jeton dans la query string (`?token=...`).
// ============================================================

import { useMemo } from 'react';
import { useLocation } from 'react-router-dom';

import { readTokenFromSearch } from '../resetPasswordForm';
import { ResetPasswordForm } from '../components/ResetPasswordForm';

import '../../../../styles/pages/auth/_reset-password.scss';

export function ResetPasswordPage() {
    const location = useLocation();

    // Jeton extrait de l'URL à chaque changement de query string.
    const token = useMemo(() => readTokenFromSearch(location.search), [location.search]);

    return <ResetPasswordForm token={token} />;
}