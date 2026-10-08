import { useState } from 'react';
import { Avatar } from '../../../../../components/UI/Avatar';
import { Badge } from '../../../../../components/UI/Badge';
import { Button } from '../../../../../components/UI/Button';
import { Card } from '../../../../../components/UI/Card';
import { ErrorState } from '../../../../../components/UI/ErrorState';
import { Spinner } from '../../../../../components/UI/Spinner';
import { FormField } from '../../../../../components/Forms/FormField';
import { Input } from '../../../../../components/Forms/Input';
import { useProfile } from '../hooks/useProfile';
import './profile.scss';

export function ProfilePage() {
    const { profile, isLoading, error, reload, updateProfile, uploadPhoto, deletePhoto } = useProfile();
    const [isEditing, setIsEditing] = useState(false);
    const [formData, setFormData] = useState({
        firstName: '',
        lastName: '',
        phone: '',
    });
    const [photoFile, setPhotoFile] = useState<File | null>(null);
    const [isUploading, setIsUploading] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);

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
        setSaveError(null);
        setIsEditing(true);
    };

    const handleCancelEdit = () => {
        setIsEditing(false);
        setSaveError(null);
        setFormData({ firstName, lastName, phone: profile.phone ?? '' });
    };

    const handleSave = async () => {
        setSaveError(null);
        try {
            const fullName = `${formData.firstName.trim()} ${formData.lastName.trim()}`.trim();
            await updateProfile({ firstName: formData.firstName, lastName: formData.lastName, phone: formData.phone });
            setIsEditing(false);
        } catch (err) {
            setSaveError(err instanceof Error ? err.message : 'Échec de la sauvegarde');
        }
    };

    const handlePhotoChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (file) {
            if (!file.type.startsWith('image/')) {
                setSaveError('Le fichier doit être une image');
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                setSaveError('L\'image ne doit pas dépasser 10 Mo');
                return;
            }
            setPhotoFile(file);
            setSaveError(null);
        }
    };

    const handleUploadPhoto = async () => {
        if (!photoFile) return;
        setIsUploading(true);
        setSaveError(null);
        try {
            await uploadPhoto(photoFile);
            setPhotoFile(null);
        } catch (err) {
            setSaveError(err instanceof Error ? err.message : 'Échec de l\'upload');
        } finally {
            setIsUploading(false);
        }
    };

    const handleDeletePhoto = async () => {
        if (!window.confirm('Supprimer votre photo de profil ?')) return;
        try {
            await deletePhoto();
        } catch (err) {
            setSaveError(err instanceof Error ? err.message : 'Échec de la suppression');
        }
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
                    <Button variant="secondary" onClick={reload}>Actualiser</Button>
                    {isEditing ? (
                        <>
                            <Button variant="outline" onClick={handleCancelEdit}>Annuler</Button>
                            <Button onClick={handleSave} disabled={isUploading}>
                                {isUploading ? 'Sauvegarde...' : 'Enregistrer'}
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
                    <Avatar
                        name={profile.fullName}
                        src={profile.profilePhoto ?? undefined}
                        size="large"
                    />
                    {isEditing && (
                        <div className="profile-avatar__actions">
                            <label className="btn btn--ghost btn--small" htmlFor="photo-upload">
                                📷 Changer
                                <input
                                    id="photo-upload"
                                    type="file"
                                    accept="image/*"
                                    onChange={handlePhotoChange}
                                    style={{ display: 'none' }}
                                />
                            </label>
                            {photoFile && (
                                <Button size="small" variant="primary" onClick={handleUploadPhoto} disabled={isUploading}>
                                    {isUploading ? 'Upload...' : 'Valider'}
                                </Button>
                            )}
                            {profile.profilePhoto && (
                                <Button size="small" variant="outline" onClick={handleDeletePhoto}>
                                    Supprimer
                                </Button>
                            )}
                        </div>
                    )}
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
                                        disabled={isUploading}
                                    />
                                </FormField>
                                <FormField label="Nom" htmlFor="lastName" required>
                                    <Input
                                        id="lastName"
                                        value={formData.lastName}
                                        onChange={(e) => setFormData({ ...formData, lastName: e.target.value })}
                                        disabled={isUploading}
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
                                    disabled={isUploading}
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
                        <div><dt>Dernière connexion</dt><dd>{profile.lastLoginAt ? new Date(profile.lastLoginAt).toLocaleString('fr-FR') : 'Aucune connexion récente'}</dd></div>
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