import { Avatar } from '../../../../../components/UI/Avatar';
import { Badge } from '../../../../../components/UI/Badge';
import { Button } from '../../../../../components/UI/Button';
import { Card } from '../../../../../components/UI/Card';
import { ErrorState } from '../../../../../components/UI/ErrorState';
import { Spinner } from '../../../../../components/UI/Spinner';
import { useProfile } from '../hooks/useProfile';
import './profile.scss';

export function ProfilePage() {
    const { profile, isLoading, error, reload } = useProfile();
    if (isLoading) return <Spinner />;
    if (!profile) return <ErrorState size="full" title="Profil indisponible" message={error instanceof Error ? error.message : 'Impossible de charger votre profil.'} onRetry={reload} />;
    const role = profile.platformRole ?? profile.organizations[0]?.role ?? 'Utilisateur';
    return <main className="account-page profile-page">
        <header className="account-page__header"><div><span className="account-page__eyebrow">COMPTE</span><h1>Mon profil</h1><p>Vos informations de compte et vos accès.</p></div><Button variant="secondary" onClick={reload}>Actualiser</Button></header>
        <Card className="profile-hero"><Avatar name={profile.fullName} src={profile.profilePhoto ?? undefined} size="large" /><div className="profile-hero__identity"><h2>{profile.fullName}</h2><p>{profile.email}</p><Badge variant={profile.isActive ? 'success' : 'error'} dot>{profile.isActive ? 'Compte actif' : 'Compte inactif'}</Badge></div></Card>
        <div className="account-page__grid">
            <Card header="Informations personnelles"><dl className="account-details"><div><dt>Nom complet</dt><dd>{profile.fullName}</dd></div><div><dt>Adresse e-mail</dt><dd>{profile.email}</dd></div><div><dt>Téléphone</dt><dd>{profile.phone || 'Non renseigné'}</dd></div><div><dt>Dernière connexion</dt><dd>{profile.lastLoginAt ? new Date(profile.lastLoginAt).toLocaleString('fr-FR') : 'Aucune connexion récente'}</dd></div></dl></Card>
            <Card header="Accès et organisations"><dl className="account-details"><div><dt>Rôle principal</dt><dd>{role}</dd></div><div><dt>Organisations</dt><dd>{profile.organizations.length}</dd></div><div><dt>Villes assignées</dt><dd>{profile.cities.length}</dd></div></dl>{profile.organizations.length > 0 && <ul className="profile-org-list">{profile.organizations.map((organization) => <li key={organization.uuid}><span>{organization.name}</span><Badge>{organization.role}</Badge></li>)}</ul>}</Card>
        </div>
    </main>;
}
