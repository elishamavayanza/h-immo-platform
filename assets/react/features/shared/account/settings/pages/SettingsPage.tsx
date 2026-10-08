import { useState, useContext } from 'react';
import { Badge } from '../../../../../components/UI/Badge';
import { Button } from '../../../../../components/UI/Button';
import { Card } from '../../../../../components/UI/Card';
import { FormField } from '../../../../../components/Forms/FormField';
import { Select } from '../../../../../components/Forms/Select';
import { useAuth } from '../../../../../app/providers/AuthProvider';
import { useAccountSettings } from '../hooks/useAccountSettings';
import './settings.scss';

function platformRoleLabel(role: string | null): string {
    switch (role) {
        case 'super_admin': return 'Super Administrateur';
        case null: return 'Utilisateur';
        default: return role;
    }
}

export function SettingsPage() {
    const { user } = useAuth();
    const { settings, update, isLoading, error, reload } = useAccountSettings();
    const [saved, setSaved] = useState(false);
    const isSuperAdmin = user?.platformRole === 'super_admin';

    const toggle = (key: keyof typeof settings) => {
        const value = !settings[key];
        update(key, value);
        setSaved(true);
        window.setTimeout(() => setSaved(false), 1800);
    };

    const handleSelectChange = (key: keyof typeof settings) => (event: React.ChangeEvent<HTMLSelectElement>) => {
        const value = event.target.value;
        update(key, value);
        setSaved(true);
        window.setTimeout(() => setSaved(false), 1800);
    };

    if (isLoading) return <div className="account-page__spinner"><span>Chargement des préférences…</span></div>;

    if (error) {
        return (
            <div className="settings-error">
                <p>Erreur : {error}</p>
                <Button variant="secondary" onClick={reload}>Réessayer</Button>
            </div>
        );
    }

    return (
        <main className="account-page settings-page">
            <header className="account-page__header">
                <div>
                    <span className="account-page__eyebrow">COMPTE</span>
                    <h1>Paramètres</h1>
                    <p>Personnalisez votre expérience H-Immo.</p>
                </div>
                {saved && <Badge variant="success">Préférences enregistrées</Badge>}
            </header>

            <div className="settings-page__sections">
                <Card header="Affichage" className="settings-card">
                    <div className="setting-row">
                        <div>
                            <strong>Thème</strong>
                            <p>Choisissez l'apparence de l'interface.</p>
                        </div>
                        <Select
                            value={settings.theme}
                            onChange={handleSelectChange('theme')}
                            options={[
                                { value: 'system', label: 'Système' },
                                { value: 'light', label: 'Clair' },
                                { value: 'dark', label: 'Sombre' },
                            ]}
                        />
                    </div>
                    <div className="setting-row">
                        <div>
                            <strong>Langue</strong>
                            <p>Langue de l'interface.</p>
                        </div>
                        <Select
                            value={settings.locale}
                            onChange={handleSelectChange('locale')}
                            options={[
                                { value: 'fr', label: 'Français' },
                                { value: 'en', label: 'English' },
                            ]}
                        />
                    </div>
                    <div className="setting-row">
                        <div>
                            <strong>Format de date</strong>
                            <p>Format d'affichage des dates.</p>
                        </div>
                        <Select
                            value={settings.dateFormat}
                            onChange={handleSelectChange('dateFormat')}
                            options={[
                                { value: 'DD/MM/YYYY', label: 'JJ/MM/AAAA' },
                                { value: 'MM/DD/YYYY', label: 'MM/JJ/AAAA' },
                                { value: 'YYYY-MM-DD', label: 'AAAA-MM-JJ' },
                            ]}
                        />
                    </div>
                    <div className="setting-row">
                        <div>
                            <strong>Tableaux compacts</strong>
                            <p>Réduire l'espacement pour afficher plus de lignes.</p>
                        </div>
                        <button
                            className="setting-switch"
                            type="button"
                            role="switch"
                            aria-checked={settings.compactTables}
                            onClick={() => toggle('compactTables')}
                        >
                            <span />
                        </button>
                    </div>
                    <div className="setting-row">
                        <div>
                            <strong>Réduire les animations</strong>
                            <p>Désactive les transitions pour plus de fluidité.</p>
                        </div>
                        <button
                            className="setting-switch"
                            type="button"
                            role="switch"
                            aria-checked={settings.reducedMotion}
                            onClick={() => toggle('reducedMotion')}
                        >
                            <span />
                        </button>
                    </div>
                </Card>

                <Card header="Notifications" className="settings-card">
                    <div className="setting-row">
                        <div>
                            <strong>Notifications par e-mail</strong>
                            <p>Recevoir les alertes importantes par e-mail.</p>
                        </div>
                        <button
                            className="setting-switch"
                            type="button"
                            role="switch"
                            aria-checked={settings.emailNotifications}
                            onClick={() => toggle('emailNotifications')}
                        >
                            <span />
                        </button>
                    </div>
                    <div className="setting-row">
                        <div>
                            <strong>Notifications dans l'application</strong>
                            <p>Afficher les alertes dans l'interface.</p>
                        </div>
                        <button
                            className="setting-switch"
                            type="button"
                            role="switch"
                            aria-checked={settings.inAppNotifications}
                            onClick={() => toggle('inAppNotifications')}
                        >
                            <span />
                        </button>
                    </div>
                </Card>

                <Card header="Sécurité" className="settings-card">
                    <div className="setting-row">
                        <div>
                            <strong>Mot de passe</strong>
                            <p>Pour modifier votre mot de passe, utilisez la procédure de réinitialisation sécurisée.</p>
                        </div>
                        <Button as="a" href="/forgot-password" variant="secondary">
                            Réinitialiser
                        </Button>
                    </div>
                </Card>

                {isSuperAdmin && (
                    <Card header="Administration plateforme (SUPER_ADMIN)" className="settings-card">
                        <div className="setting-row">
                            <div>
                                <strong>Rétention logs d'audit (jours)</strong>
                                <p>Durée de conservation des journaux d'audit (30–2555 jours).</p>
                            </div>
                            <FormField label="" htmlFor="auditRetention">
                                <input
                                    id="auditRetention"
                                    type="number"
                                    min="30"
                                    max="2555"
                                    value={settings.auditLogRetentionDays ?? ''}
                                    onChange={(e) => {
                                        const value = e.target.value === '' ? undefined : parseInt(e.target.value, 10);
                                        update('auditLogRetentionDays', value);
                                    }}
                                />
                            </FormField>
                        </div>
                        <div className="setting-row">
                            <div>
                                <strong>Taille de page par défaut</strong>
                                <p>Nombre d'éléments par page dans les tableaux (10–100).</p>
                            </div>
                            <Select
                                value={settings.defaultPageSize ?? 20}
                                onChange={handleSelectChange('defaultPageSize')}
                                options={[
                                    { value: 10, label: '10' },
                                    { value: 20, label: '20' },
                                    { value: 50, label: '50' },
                                    { value: 100, label: '100' },
                                ]}
                            />
                        </div>
                    </Card>
                )}
            </div>
        </main>
    );
}