import { useState } from 'react';
import { Badge } from '../../../../../components/UI/Badge';
import { Button } from '../../../../../components/UI/Button';
import { Card } from '../../../../../components/UI/Card';
import { useAccountSettings } from '../hooks/useAccountSettings';
import './settings.scss';

export function SettingsPage() {
    const { settings, update } = useAccountSettings();
    const [saved, setSaved] = useState(false);
    const toggle = (key: 'compactTables' | 'emailNotifications') => { update(key, !settings[key]); setSaved(true); window.setTimeout(() => setSaved(false), 1800); };
    return <main className="account-page settings-page">
        <header className="account-page__header"><div><span className="account-page__eyebrow">COMPTE</span><h1>Paramètres</h1><p>Personnalisez votre expérience H-Immo.</p></div>{saved && <Badge variant="success">Préférences enregistrées</Badge>}</header>
        <div className="settings-page__sections">
            <Card header="Préférences d’affichage" className="settings-card">
                <div className="setting-row"><div><strong>Tableaux plus compacts</strong><p>Réduire l’espacement dans les tableaux pour afficher davantage de lignes.</p></div><button className="setting-switch" type="button" role="switch" aria-checked={settings.compactTables} onClick={() => toggle('compactTables')}><span /></button></div>
            </Card>
            <Card header="Notifications" className="settings-card">
                <div className="setting-row"><div><strong>Notifications par e-mail</strong><p>Recevoir les notifications importantes liées à votre compte.</p></div><button className="setting-switch" type="button" role="switch" aria-checked={settings.emailNotifications} onClick={() => toggle('emailNotifications')}><span /></button></div>
            </Card>
            <Card header="Sécurité du compte" className="settings-card"><div className="setting-row"><div><strong>Mot de passe</strong><p>Pour modifier votre mot de passe, utilisez la procédure de réinitialisation sécurisée.</p></div><Button as="a" href="/forgot-password" variant="secondary">Réinitialiser</Button></div></Card>
        </div>
    </main>;
}
