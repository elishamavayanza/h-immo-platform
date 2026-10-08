import { useState } from 'react';
import { Avatar } from '../../../../../components/UI/Avatar';
import { Badge } from '../../../../../components/UI/Badge';
import { Button } from '../../../../../components/UI/Button';
import { Card } from '../../../../../components/UI/Card';
import { ErrorState } from '../../../../../components/UI/ErrorState';
import { Spinner } from '../../../../../components/UI/Spinner';
import { FormField } from '../../../../../components/Forms/FormField';
import { Input } from '../../../../../components/Forms/Input';
import { UserPhotoPicker } from '../components/UserPhotoPicker';
import { useProfile } from '../hooks/useProfile';
import { formatUserDate } from '../../../../../services/userPreferences';
import './profile.scss';

export function ProfilePage() {
    const { profile, isLoading, error, reload, updateProfile, uploadPhoto, deletePhoto } = useProfile();
    const [isEditing, setIsEditing] = useState(false);
    const [formData, setFormData] = useState({
        firstName: '',
        lastName: '',
        phone: '',
    });
    const [photoChange, setPhotoChange] = useState<{ kind: 'new'; dataUrl: string; file: File } | { kind: 'removed' } | null>(null);
    const [saveError, setSaveError] = useState<string | null>(null);
    const [isSaving, setIsSaving] = useState(false);

    const fullName = profile?.fullName ?? '';
    const nameParts = fullName.split(' ');
    const firstName = nameParts[0] ?? '';
    const lastName = nameParts.slice(1).join(' ');

    if (isLoading) return <Spinner className="account-page__spinner" />;

    if (!profile) {
        return (
            <ErrorState
                size="full"
                title="Profil indisponible"
                message={error instanceof Error ? error.message : 'Impossible de charger votre profil.'}
                onRetry={reload}
            />
        );
    }

    const handleStartEdit = () => {
        setFormData({ firstName, lastName, phone: profile.phone ?? '' });
        setPhotoChange(null);
        setSaveError(null);
        setIsEditing(true);
    };

    const handleCancelEdit = () => {
        setIsEditing(false);
        setSaveError(null);
        setFormData({ firstName, lastName, phone: profile.phone ?? '' });
        setPhotoChange(null);
    };

    const handleSave = async () => {
        if (isSaving) return;
        setSaveError(null);
        setIsSaving(true);
        try {
            if (photoChange?.kind === 'new') {
                await uploadPhoto(photoChange.file);
            } else if (photoChange?.kind === 'removed') {
                await deletePhoto();
            }

            const nextFullName = `${formData.firstName.trim()} ${formData.lastName.trim()}`.trim();
            if (nextFullName !== profile.fullName || formData.phone !== (profile.phone ?? '')) {
                await updateProfile({
                    firstName: formData.firstName,
                    lastName: formData.lastName,
                    phone: formData.phone,
                });
            }
            setIsEditing(false);
            setPhotoChange(null);
        } catch (err) {
            setSaveError(err instanceof Error ? err.message : 'Échec de la sauvegarde');
        } finally {
            setIsSaving(false);
        }
    };

    const handlePhotoChange = (change: { kind: 'new'; dataUrl: string; file: File } | { kind: 'removed' } | null) => {
        setPhotoChange(change);
    };

    const role = profile.platformRole ?? profile.organizations[0]?.role ?? 'Utilisateur';

    return (
        <main className="account-page profile-page">
            <header className="account-page__header">
                <div>
                    <span className="account-page__eyebrow">COMPTE</span>
                    <h1>Mon profil</h1>
                    <p>Vos informations de compte et vos accès.</p>
                </div>
                <div className="account-page__header-actions">
                    {isEditing ? (
                        <>
                            <Button variant="outline" onClick={handleCancelEdit} disabled={isSaving}>Annuler</Button>
                            <Button onClick={handleSave} isLoading={isSaving}>
                                {isSaving ? 'Enregistrement…' : 'Enregistrer'}
                            </Button>
                        </>
                    ) : (
                        <Button onClick={handleStartEdit}>Modifier</Button>
                    )}
                </div>
            </header>

            {saveError && <div className="profile-error">{saveError}</div>}

            <Card className="profile-hero">
                <div className="profile-hero__avatar">
                    <UserPhotoPicker
                        value={profile.profilePhoto ?? undefined}
                        change={photoChange}
                        onChange={handlePhotoChange}
                        disabled={!isEditing}
                    />
                </div>
                <div className="profile-hero__identity">
                    {isEditing ? (
                        <div className="profile-form">
                            <div className="profile-form__row">
                                <FormField label="Prénom" htmlFor="firstName" required>
                                    <Input
                                        id="firstName"
                                        value={formData.firstName}
                                        onChange={(e) => setFormData({ ...formData, firstName: e.target.value })}
                                    />
                                </FormField>
                                <FormField label="Nom" htmlFor="lastName" required>
                                    <Input
                                        id="lastName"
                                        value={formData.lastName}
                                        onChange={(e) => setFormData({ ...formData, lastName: e.target.value })}
                                    />
                                </FormField>
                            </div>
                            <FormField label="Téléphone" htmlFor="phone">
                                <Input
                                    id="phone"
                                    type="tel"
                                    value={formData.phone}
                                    onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                                    placeholder="+243 99 00 00 000"
                                />
                            </FormField>
                        </div>
                    ) : (
                        <>
                            <h2>{profile.fullName}</h2>
                            <p>{profile.email}</p>
                            <Badge variant={profile.isActive ? 'success' : 'error'} dot>
                                {profile.isActive ? 'Compte actif' : 'Compte inactif'}
                            </Badge>
                        </>
                    )}
                </div>
            </Card>

            <div className="account-page__grid">
                <Card header="Informations personnelles">
                    <dl className="account-details">
                        <div><dt>Nom complet</dt><dd>{isEditing ? `${formData.firstName} ${formData.lastName}` : profile.fullName}</dd></div>
                        <div><dt>Adresse e-mail</dt><dd>{profile.email}</dd></div>
                        <div><dt>Téléphone</dt><dd>{isEditing ? formData.phone : (profile.phone || 'Non renseigné')}</dd></div>
                        <div><dt>Dernière connexion</dt><dd>{profile.lastLoginAt ? formatUserDate(profile.lastLoginAt, true) : 'Aucune connexion récente'}</dd></div>
                    </dl>
                </Card>
                <Card header="Accès et organisations">
                    <dl className="account-details">
                        <div><dt>Rôle principal</dt><dd>{role}</dd></div>
                        <div><dt>Organisations</dt><dd>{profile.organizations.length}</dd></div>
                        <div><dt>Villes assignées</dt><dd>{profile.cities.length}</dd></div>
                    </dl>
                    {profile.organizations.length > 0 && (
                        <ul className="profile-org-list">
                            {profile.organizations.map((organization) => (
                                <li key={organization.uuid}>
                                    <span>{organization.name}</span>
                                    <Badge>{organization.role}</Badge>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </main>
    );
}
