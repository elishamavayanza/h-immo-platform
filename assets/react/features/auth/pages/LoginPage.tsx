// ============================================================
// assets/react/features/auth/pages/LoginPage.tsx
// Page de connexion (route `/login`, hors session, sous `AuthLayout`).
//
// Pendant la restauration de session (`isLoading`), aucun formulaire
// n'est rendu : un utilisateur déjà authentifié doit être redirigé vers
// `/app` sans jamais voir la carte de connexion (évite un flash).
// ============================================================

import { Navigate } from 'react-router-dom';

import { useAuth } from '../../../app/providers/AuthProvider';
import { LoginForm } from '../components/LoginForm';

import '../../../../styles/pages/auth/_login.scss';

export function LoginPage() {
    const { isAuthenticated, isLoading } = useAuth();

    if (isLoading) {
        return null;
    }

    if (isAuthenticated) {
        return <Navigate to="/app" replace />;
    }

    return <LoginForm />;
}