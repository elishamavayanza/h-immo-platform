import { useState } from 'react';
import { Badge } from '../../../../../components/UI/Badge';
import { Button } from '../../../../../components/UI/Button';
import { Card } from '../../../../../components/UI/Card';
import { FormField } from '../../../../../components/Forms/FormField';
import { Select } from '../../../../../components/Forms/Select';
import { useAuth } from '../../../../../app/providers/AuthProvider';
import { useAccountSettings } from '../hooks/useAccountSettings';
import './settings.scss';

export function SettingsPage() {
    const { user } = useAuth();
    const { settings, update, isLoading, error, reload } = useAccountSettings();
    const [saved, setSaved] = useState(false);
    const [retentionDraft, setRetentionDraft] = useState<string | null>(null);
    const [retentionError, setRetentionError] = useState<string | null>(null);
    const isSuperAdmin = user?.platformRole === 'super_admin';

    const apply = async (key: keyof typeof settings, value: unknown) => {
        const didSave = await update(key, value);
        setSaved(didSave);
        if (didSave) window.setTimeout(() => setSaved(false), 1800);
    };

    const toggle = (key: keyof typeof settings) => {
        const value = !settings[key];
        void apply(key, value);
    };

    const handleSelectChange = (key: keyof typeof settings) => (event: React.ChangeEvent<HTMLSelectElement>) => {
        const rawValue = event.target.value;
        const value = key === 'defaultPageSize' ? Number(rawValue) : rawValue;
        void apply(key, value);
    };

    const saveRetention = () => {
        if (retentionDraft === null) return;
        const value = Number(retentionDraft);
        if (!Number.isInteger(value) || value < 30 || value > 2555) {
            setRetentionError('Choisissez une durée entre 30 et 2555 jours.');
            return;
        }
        setRetentionError(null);
        void apply('auditLogRetentionDays', value);
        setRetentionDraft(null);
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
                <Card header="Affichage" className="settings-card settings-card--display">
                    <div className="setting-row">
                        <div>
                            <strong>Thème</strong>
                            <p>Choisissez l'apparence de l'interface.</p>
                            <small className="setting-row__note">Le choix du thème sera disponible prochainement.</small>
                        </div>
                        <Select
                            disabled
                            value={settings.theme}
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
                            <small className="setting-row__note">Le changement de langue sera disponible prochainement.</small>
                        </div>
                        <Select
                            disabled
                            value={settings.locale}
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
                    <Card header="Préférences plateforme" className="settings-card settings-card--platform">
                        <div className="setting-row">
                            <div>
                                <strong>Période d'affichage du journal (jours)</strong>
                                <p>Limiter les résultats affichés aux événements récents (30–2555 jours).</p>
                            </div>
                            <FormField label="" htmlFor="auditRetention">
                                <input
                                    id="auditRetention"
                                    type="number"
                                    min="30"
                                    max="2555"
                                    value={retentionDraft ?? String(settings.auditLogRetentionDays ?? '')}
                                    onChange={(e) => setRetentionDraft(e.target.value)}
                                    onBlur={saveRetention}
                                    onKeyDown={(event) => { if (event.key === 'Enter') { event.currentTarget.blur(); } }}
                                />
                                {retentionError && <small className="setting-row__error">{retentionError}</small>}
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
