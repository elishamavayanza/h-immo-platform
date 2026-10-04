// ============================================================
// assets/react/features/auth/components/ResetPasswordForm.tsx
// Formulaire de réinitialisation du mot de passe (design system).
//
// Trois états possibles :
//   - jeton absent de l'URL → écran « lien invalide » (rien à soumettre) ;
//   - formulaire : deux champs mot de passe, le serveur juge le jeton ;
//   - `isDone` : confirmation, avec retour vers la connexion.
// Le jeton n'est jamais affiché : sa seule présence dans l'URL suffit.
// ============================================================

import { Link } from 'react-router-dom';

import { Alert } from '../../../components/UI/Alert/Alert';
import { Button } from '../../../components/UI/Button/Button';
import { Form } from '../../../components/Forms/Form/Form';
import { FormField } from '../../../components/Forms/FormField/FormField';
import { Password } from '../../../components/Forms/Password/Password';

import { RESET_SUCCESS_MESSAGE, useResetPasswordForm } from '../hooks/useResetPasswordForm';

export function ResetPasswordForm({ token }: { token: string }) {
    const form = useResetPasswordForm(token);
    const isSubmitting = form.isSubmitting;

    const passwordError = form.touched.password ? form.errors.password : undefined;
    const confirmationError = form.touched.confirmation ? form.errors.confirmation : undefined;

    if (token === '') {
        return (
            <main className="auth-page">
                <header className="auth-page__header">
                    <div className="auth-page__brand">
                        <span className="auth-page__brand-mark" aria-hidden="true">H</span>
                        <span className="auth-page__brand-name">Immo</span>
                    </div>
                    <h1 className="auth-page__title">Lien de réinitialisation invalide</h1>
                </header>

                <Alert variant="error">
                    Ce lien ne contient pas de jeton. Demandez-en un nouveau depuis la page de connexion.
                </Alert>

                <p className="auth-page__switch">
                    <Link to="/login" className="auth-page__link">Revenir à la connexion</Link>
                </p>
            </main>
        );
    }

    if (form.isDone) {
        return (
            <main className="auth-page">
                <header className="auth-page__header">
                    <div className="auth-page__brand">
                        <span className="auth-page__brand-mark" aria-hidden="true">H</span>
                        <span className="auth-page__brand-name">Immo</span>
                    </div>
                    <h1 className="auth-page__title">Mot de passe défini</h1>
                </header>

                <Alert variant="success">
                    {RESET_SUCCESS_MESSAGE}
                </Alert>

                <p className="auth-page__switch">
                    <Link to="/login" className="auth-page__link">Se connecter</Link>
                </p>
            </main>
        );
    }

    return (
        <main className="auth-page">
            <header className="auth-page__header">
                <div className="auth-page__brand">
                    <span className="auth-page__brand-mark" aria-hidden="true">H</span>
                    <span className="auth-page__brand-name">Immo</span>
                </div>
                <h1 className="auth-page__title">Réinitialiser mon mot de passe</h1>
                <p className="auth-page__description">
                    Choisissez un nouveau mot de passe pour votre compte.
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
                    label="Nouveau mot de passe"
                    htmlFor="reset-password"
                    required
                    error={passwordError}
                    variant={passwordError !== undefined ? 'error' : 'default'}
                >
                    <Password
                        id="reset-password"
                        name="password"
                        autoComplete="new-password"
                        placeholder="8 caractères minimum"
                        fullWidth
                        required
                        value={form.values.password}
                        onChange={(event) => form.handleChange('password', event.target.value)}
                        onBlur={() => form.handleBlur('password')}
                        variant={passwordError !== undefined ? 'error' : 'default'}
                        disabled={isSubmitting}
                    />
                </FormField>

                <FormField
                    label="Confirmer le mot de passe"
                    htmlFor="reset-password-confirmation"
                    required
                    error={confirmationError}
                    variant={confirmationError !== undefined ? 'error' : 'default'}
                >
                    <Password
                        id="reset-password-confirmation"
                        name="confirmation"
                        autoComplete="new-password"
                        placeholder="Confirmez le mot de passe"
                        fullWidth
                        required
                        value={form.values.confirmation}
                        onChange={(event) => form.handleChange('confirmation', event.target.value)}
                        onBlur={() => form.handleBlur('confirmation')}
                        variant={confirmationError !== undefined ? 'error' : 'default'}
                        disabled={isSubmitting}
                    />
                </FormField>

                {form.serverError !== null && (
                    <Alert variant="error">
                        {form.serverError}
                    </Alert>
                )}

                <Button type="submit" size="large" fullWidth isLoading={isSubmitting}>
                    Réinitialiser mon mot de passe
                </Button>
            </Form>
        </main>
    );
}