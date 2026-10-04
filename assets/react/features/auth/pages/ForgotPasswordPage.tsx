// ============================================================
// assets/react/features/auth/pages/ForgotPasswordPage.tsx
// Page « mot de passe oublié » (route `/forgot-password`, hors session,
// sous `AuthLayout`). Cette route est publique dans `security.yaml`.
// ============================================================

import { ForgotPasswordForm } from '../components/ForgotPasswordForm';

import '../../../../styles/pages/auth/_forgot-password.scss';

export function ForgotPasswordPage() {
    return <ForgotPasswordForm />;
}