// ============================================================
// upload/react/features/auth/components/ForgotPasswordForm.tsx
// Formulaire « mot de passe oublié » (design system).
//
// Une fois la demande acceptée, l'écran devient une simple confirmation
// : l'API répondant toujours 200 avec un message générique, le succès
// n'affirme jamais qu'une adresse existe (anti-énumération).
// ============================================================

import { Link } from 'react-router-dom';

import { Alert } from '../../../../../public/components/UI/Alert/Alert';
import { Button } from '../../../../../public/components/UI/Button/Button';
import { Form } from '../../../../../public/components/Forms/Form/Form';
import { FormField } from '../../../../../public/components/Forms/FormField/FormField';
import { Input } from '../../../../../public/components/Forms/Input/Input';

import { DEFAULT_FORGOT_RESET_MESSAGE, useForgotPasswordForm } from '../hooks/useForgotPasswordForm';

import logoUrl from '../../../app/upload/logo.png';

const MAIL_ICON = (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <rect x="2" y="4" width="20" height="16" rx="2" />
        <polyline points="22,6 12,13 2,6" />
    </svg>
);

export function ForgotPasswordForm() {
    const form = useForgotPasswordForm();

    const emailError = form.touched.email ? form.errors.email : undefined;

    if (form.isDone) {
        return (
            <main className="auth-form">
                <header className="auth-page__header">
                    <img
                        className="auth-page__logo"
                        src={logoUrl}
                        alt="H-Immo"
                    />
                    <h1 className="auth-page__title">Email envoyé</h1>
                </header>

                <Alert variant="success">
                    {form.successMessage ?? DEFAULT_FORGOT_RESET_MESSAGE}
                </Alert>

                <p className="auth-page__switch">
                    <Link to="/login" className="auth-page__link">
                        Revenir à la connexion
                    </Link>
                </p>
            </main>
        );
    }

    return (
        <main className="auth-form">
            <header className="auth-page__header">
                <img
                    className="auth-page__logo"
                    src={logoUrl}
                    alt="H-Immo"
                />
                <h1 className="auth-page__title">Mot de passe oublié</h1>
                <p className="auth-page__description">
                    Saisissez votre adresse email. Si un compte existe, un lien de
                    réinitialisation lui sera envoyé.
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
                    htmlFor="forgot-password-email"
                    required
                    error={emailError}
                    variant={emailError !== undefined ? 'error' : 'default'}
                >
                    <Input
                        id="forgot-password-email"
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

                {form.serverError !== null && (
                    <Alert variant="error">
                        {form.serverError}
                    </Alert>
                )}

                <Button type="submit" size="large" fullWidth isLoading={form.isSubmitting}>
                    Envoyer le lien
                </Button>
            </Form>

            <p className="auth-page__switch">
                <Link to="/login" className="auth-page__link">
                    Revenir à la connexion
                </Link>
            </p>
        </main>
    );
}
