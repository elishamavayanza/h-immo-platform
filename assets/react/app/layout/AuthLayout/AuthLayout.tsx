// ============================================================
// upload/react/app/layout/AuthLayout/AuthLayout.tsx
// Coquille des écrans HORS session (connexion, réinitialisation de
// mot de passe) : une carte centrée qui contient `<Outlet/>`.
// ============================================================

import { Outlet } from 'react-router-dom';

import './AuthLayout.scss';

export function AuthLayout() {
    return (
        <div className="auth-page">
            <div className="auth-page__card">
                <Outlet />
            </div>
        </div>
    );
}
