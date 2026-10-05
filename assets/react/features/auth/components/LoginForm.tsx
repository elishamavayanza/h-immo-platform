// ============================================================
// upload/react/features/auth/components/LoginForm.tsx
// Formulaire de connexion (design system).
//
// Le succès navigue vers `/app` : c'est `AuthProvider` (via `useAuth`)
// qui a persisté le jeton avant cet appel ; le formulaire ne parle
// jamais à l'API directement. Le lien « mot de passe oublié » pointe
// vers `/forgot-password` (route publique sous `AuthLayout`).
// ============================================================

import { Link, useNavigate } from 'react-router-dom';

import { Alert } from '../../../components/UI/Alert/Alert';
import { Button } from '../../../components/UI/Button/Button';
import { Form } from '../../../components/Forms/Form/Form';
import { FormField } from '../../../components/Forms/FormField/FormField';
import { Input } from '../../../components/Forms/Input/Input';
import { Password } from '../../../components/Forms/Password/Password';

import { useLoginForm } from '../hooks/useLoginForm';

import logoUrl from '../../../app/upload/logo.png';

const MAIL_ICON = (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <rect x="2" y="4" width="20" height="16" rx="2" />
        <polyline points="22,6 12,13 2,6" />
    </svg>
);

const LOCK_ICON = (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <rect x="3" y="11" width="18" height="11" rx="2" />
        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
    </svg>
);

export function LoginForm() {
    const navigate = useNavigate();

    const form = useLoginForm(() => navigate('/app', { replace: true }));

    // L'erreur d'un champ n'apparaît qu'après un blur : pas de reproche
    // prématuré pendant la saisie initiale.
    const emailError = form.touched.email ? form.errors.email : undefined;
    const passwordError = form.touched.password ? form.errors.password : undefined;

    return (
        <main className="auth-form">
            <header className="auth-page__header">
<img
                        className="auth-page__logo"
                        src={logoUrl}
                        alt="H-Immo"
                    />
                <h1 className="auth-page__title">Connexion</h1>
                <p className="auth-page__description">
                    Accédez à votre espace de gestion immobilière.
                </p>
            </header>

            <Form
                layout="vertical"
                gap="large"
                fullWidth
                className="auth-page__form"
                onSubmit={form.handleSubmit}
                noValidate
            >
                <FormField
                    label="Adresse email"
                    htmlFor="login-email"
                    required
                    error={emailError}
                    variant={emailError !== undefined ? 'error' : 'default'}
                >
                    <Input
                        id="login-email"
                        name="email"
                        type="email"
                        autoComplete="email"
                        placeholder="vous@exemple.com"
                        fullWidth
                        icon={MAIL_ICON}
                        required
                        value={form.values.email}
                        onChange={(event) => form.handleChange('email', event.target.value)}
                        onBlur={() => form.handleBlur('email')}
                        variant={emailError !== undefined ? 'error' : 'default'}
                        disabled={form.isSubmitting}
                    />
                </FormField>

                <FormField
                    label="Mot de passe"
                    htmlFor="login-password"
                    required
                    error={passwordError}
                    variant={passwordError !== undefined ? 'error' : 'default'}
                >
                    <Password
                        id="login-password"
                        name="password"
                        autoComplete="current-password"
                        placeholder="Votre mot de passe"
                        fullWidth
                        required
                        value={form.values.password}
                        onChange={(event) => form.handleChange('password', event.target.value)}
                        onBlur={() => form.handleBlur('password')}
                        variant={passwordError !== undefined ? 'error' : 'default'}
                        disabled={form.isSubmitting}
                    />
                </FormField>

                {form.serverError !== null && (
                    <Alert variant="error">
                        {form.serverError}
                    </Alert>
                )}

                <Button type="submit" size="large" fullWidth isLoading={form.isSubmitting}>
                    Se connecter
                </Button>
            </Form>

            <p className="auth-page__switch">
                <Link to="/forgot-password" className="auth-page__link">
                    Mot de passe oublié&nbsp;?
                </Link>
            </p>
        </main>
    );
}
