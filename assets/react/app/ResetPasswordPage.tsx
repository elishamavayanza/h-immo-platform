import { FormEvent, useMemo, useState } from 'react';
import { MIN_PASSWORD_LENGTH, readTokenFromSearch, validatePasswordForm } from './password-form.ts';

/**
 * Page de réinitialisation du mot de passe.
 *
 * Cible du lien contenu dans l'email « mot de passe oublié ». Elle présente
 * deux champs : le nouveau mot de passe et sa confirmation. La confirmation
 * n'existe que côté client — l'API ne reçoit que `token` et `newPassword` :
 * elle n'a aucun intérêt à recevoir la même valeur deux fois.
 *
 * Les erreurs métier affichées proviennent du serveur (Jeton invalide,
 * expiré, déjà utilisé...) : le client ne doit jamais prétendre qu'un jeton
 * est valide, il ne peut que vérifier la mise en forme.
 */
export function ResetPasswordPage({ search }: { search: string }) {
    const token = useMemo(() => readTokenFromSearch(search), [search]);

    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [touched, setTouched] = useState({ password: false, confirmation: false });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [serverError, setServerError] = useState('');
    const [isDone, setIsDone] = useState(false);

    const showPassword = useMemo(() => validatePasswordForm(password, confirmation), [password, confirmation]);
    const passwordError = touched.password ? showPassword.errors.password : undefined;
    const confirmationError = touched.confirmation ? showPassword.errors.confirmation : undefined;

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setServerError('');
        setTouched({ password: true, confirmation: true });

        if (!showPassword.isValid || isSubmitting) {
            return;
        }

        setIsSubmitting(true);

        try {
            const response = await fetch('/api/auth/reset-password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ token, newPassword: password }),
            });

            const payload = (await response.json().catch(() => null)) as
                | { message?: string; flushDescription?: string; errors?: Record<string, string[]> }
                | null;

            if (!response.ok) {
                setServerError(
                    payload?.flushDescription
                        ?? payload?.message
                        ?? 'La réinitialisation a échoué. Veuillez réutiliser le lien reçu par email.',
                );

                return;
            }

            setIsDone(true);
        } catch {
            setServerError('Impossible de joindre le serveur. Vérifiez votre connexion puis réessayez.');
        } finally {
            setIsSubmitting(false);
        }
    };

    // Aucun jeton dans l'URL : le lien est tronqué ou expiré côté client.
    // Soumettre le formulaire n'aurait aucun sens, on ne propose donc rien.
    if (token === '') {
        return (
            <main className="card">
                <h1>Lien de réinitialisation invalide</h1>
                <p className="alert alert-error" role="alert">
                    Ce lien ne contient pas de jeton. Demandez-en un nouveau depuis la page de connexion.
                </p>
                <a className="button" href="/connexion">Revenir à la connexion</a>
            </main>
        );
    }

    if (isDone) {
        return (
            <main className="card">
                <h1>Mot de passe défini</h1>
                <p className="alert alert-success" role="status">
                    Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.
                </p>
                <a className="button" href="/connexion">Se connecter</a>
            </main>
        );
    }

    return (
        <main className="card">
            <h1>Réinitialiser mon mot de passe</h1>
            <p className="hint">Choisissez un nouveau mot de passe pour votre compte.</p>

            <form onSubmit={submit} noValidate>
                <div className="field">
                    <label htmlFor="password">Nouveau mot de passe</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autoComplete="new-password"
                        minLength={MIN_PASSWORD_LENGTH}
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                        onBlur={() => setTouched((state) => ({ ...state, password: true }))}
                        aria-invalid={passwordError !== undefined}
                        aria-describedby={passwordError !== undefined ? 'password-error' : undefined}
                        disabled={isSubmitting}
                        required
                    />
                    {passwordError !== undefined && (
                        <p className="field-error" id="password-error" role="alert">{passwordError}</p>
                    )}
                </div>

                <div className="field">
                    <label htmlFor="confirmation">Confirmer le mot de passe</label>
                    <input
                        id="confirmation"
                        name="confirmation"
                        type="password"
                        autoComplete="new-password"
                        value={confirmation}
                        onChange={(event) => setConfirmation(event.target.value)}
                        onBlur={() => setTouched((state) => ({ ...state, confirmation: true }))}
                        aria-invalid={confirmationError !== undefined}
                        aria-describedby={confirmationError !== undefined ? 'confirmation-error' : undefined}
                        disabled={isSubmitting}
                        required
                    />
                    {confirmationError !== undefined && (
                        <p className="field-error" id="confirmation-error" role="alert">{confirmationError}</p>
                    )}
                </div>

                {serverError !== '' && (
                    <p className="alert alert-error" role="alert">{serverError}</p>
                )}

                <button type="submit" disabled={isSubmitting}>
                    {isSubmitting ? 'Enregistrement…' : 'Réinitialiser mon mot de passe'}
                </button>
            </form>
        </main>
    );
}
