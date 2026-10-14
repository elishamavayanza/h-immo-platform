// ============================================================
// upload/react/features/auth/pages/LoginPage.tsx
// Page de connexion (route `/login`, hors session, sous `AuthLayout`).
//
// Pendant la restauration de session (`isLoading`), aucun formulaire
// n'est rendu : un utilisateur déjà authentifié doit être redirigé vers
// `/app` sans jamais voir la carte de connexion (évite un flash).
// ============================================================

import { useState } from 'react';
import { Navigate } from 'react-router-dom';

import { useAuth } from '../../../app/providers/AuthProvider';
import { LoginForm } from '../components/LoginForm';
import { SESSION_REAUTH_NOTICE_KEY } from '../../../../services/api/interceptors';

import '../../../../styles/pages/auth/_login.scss';

export function LoginPage() {
    const { isAuthenticated, isLoading } = useAuth();
    const [sessionNotice] = useState(() => {
        try {
            const notice = window.sessionStorage.getItem(SESSION_REAUTH_NOTICE_KEY);
            window.sessionStorage.removeItem(SESSION_REAUTH_NOTICE_KEY);
            return notice === 'expired' ? 'Votre session a expiré. Reconnectez-vous pour reprendre votre travail.' : null;
        } catch {
            return null;
        }
    });

    if (isLoading) {
        return null;
    }

    if (isAuthenticated) {
        return <Navigate to="/app" replace />;
    }

    return <LoginForm notice={sessionNotice} />;
}
