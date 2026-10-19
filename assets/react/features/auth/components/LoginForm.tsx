// ============================================================
// upload/react/features/auth/components/LoginForm.tsx
// Formulaire de connexion (design system).
//
// Le succès navigue vers `/app` : c'est `AuthProvider` (via `useAuth`)
// qui a persisté le jeton avant cet appel ; le formulaire ne parle
// jamais à l'API directement. Le lien « mot de passe oublié » pointe
// vers `/forgot-password` (route publique sous `AuthLayout`).
// ============================================================

import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';

import { Alert } from '../../../components/UI/Alert/Alert';
import { Button } from '../../../components/UI/Button/Button';
import { Form } from '../../../components/Forms/Form/Form';
import { FormField } from '../../../components/Forms/FormField/FormField';
import { Input } from '../../../components/Forms/Input/Input';
import { Password } from '../../../components/Forms/Password/Password';
import { setThemePreference } from '../../../services/userPreferences';

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

const SUN_ICON = (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
    </svg>
);

const MOON_ICON = (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M20.9 13A8.5 8.5 0 0 1 11 3.1 8.8 8.8 0 1 0 20.9 13Z" />
    </svg>
);

const LOGIN_SPINNER = (
    <svg className="auth-login-spinner" viewBox="0 0 100 100" aria-hidden="true" focusable="false">
        <g stroke="currentColor" strokeWidth="10" strokeLinecap="round">
            <line x1="50" y1="9" x2="50" y2="26" opacity="1" />
            <line x1="50" y1="9" x2="50" y2="26" transform="rotate(30 50 50)" opacity=".92" />
            <line x1="50" y1="9" x2="50" y2="26" transform="rotate(60 50 50)" opacity=".84" />
            <line x1="50" y1="9" x2="50" y2="26" transform="rotate(90 50 50)" opacity=".76" />
            <line x1="50" y1="9" x2="50" y2="26" transform="rotate(120 50 50)" opacity=".68" />
            <line x1="50" y1="9" x2="50" y2="26" transform="rotate(150 50 50)" opacity=".6" />
            <line x1="50" y1="9" x2="50" y2="26" transform="rotate(180 50 50)" opacity=".52" />
            <line x1="50" y1="9" x2="50" y2="26" transform="rotate(210 50 50)" opacity=".44" />
            <line x1="50" y1="9" x2="50" y2="26" transform="rotate(240 50 50)" opacity=".36" />
            <line x1="50" y1="9" x2="50" y2="26" transform="rotate(270 50 50)" opacity=".28" />
            <line x1="50" y1="9" x2="50" y2="26" transform="rotate(300 50 50)" opacity=".2" />
            <line x1="50" y1="9" x2="50" y2="26" transform="rotate(330 50 50)" opacity=".12" />
        </g>
    </svg>
);

export function LoginForm({ notice }: { notice?: string | null } = {}) {
    const navigate = useNavigate();
    const [isLightTheme, setIsLightTheme] = useState(
        () => typeof document !== 'undefined' && document.documentElement.dataset.theme === 'light',
    );

    const form = useLoginForm(() => navigate('/app', { replace: true }));

    // L'erreur d'un champ n'apparaît qu'après un blur : pas de reproche
    // prématuré pendant la saisie initiale.
    const emailError = form.touched.email ? form.errors.email : undefined;
    const passwordError = form.touched.password ? form.errors.password : undefined;

    return (
        <main className="auth-form">
            <button
                className="auth-theme-switch"
                type="button"
                role="switch"
                aria-checked={isLightTheme}
                aria-label={isLightTheme ? 'Passer au thème sombre' : 'Passer au thème clair'}
                title={isLightTheme ? 'Passer au thème sombre' : 'Passer au thème clair'}
                onClick={() => {
                    const nextIsLightTheme = !isLightTheme;
                    setThemePreference(nextIsLightTheme ? 'light' : 'dark');
                    setIsLightTheme(nextIsLightTheme);
                }}
            >
                <span className={`auth-theme-switch__icon${isLightTheme ? ' is-active' : ''}`}>{SUN_ICON}</span>
                <span className="auth-theme-switch__track" aria-hidden="true">
                    <span className="auth-theme-switch__thumb" />
                </span>
                <span className={`auth-theme-switch__icon${!isLightTheme ? ' is-active' : ''}`}>{MOON_ICON}</span>
                <span className="auth-theme-switch__label">{isLightTheme ? 'Clair' : 'Sombre'}</span>
            </button>
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

            {notice && <Alert variant="info">{notice}</Alert>}

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

                <Button
                    type="submit"
                    size="large"
                    fullWidth
                    isLoading={form.isSubmitting}
                    spinner={LOGIN_SPINNER}
                    aria-label={form.isSubmitting ? 'Connexion en cours' : undefined}
                >
                    {form.isSubmitting ? null : 'Se connecter'}
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
