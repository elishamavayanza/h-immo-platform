import { Link } from 'react-router-dom';
import { UserManualSection } from '../components/UserManualSection';
import './settings.scss';

export function UserManualPage() {
    return (
        <main className="account-page settings-page settings-manual-page">
            <header className="account-page__header">
                <div>
                    <span className="account-page__eyebrow">AIDE ET DOCUMENTATION</span>
                    <h1>Manuel d’utilisation</h1>
                    <p>Consultez le guide adapté à votre rôle dans Soft-IMMO.</p>
                </div>
                <Link className="settings-manual-page__back" to="/settings">Retour aux paramètres</Link>
            </header>
            <div className="settings-page__sections">
                <UserManualSection />
            </div>
        </main>
    );
}
