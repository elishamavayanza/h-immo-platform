import { useEffect, useState } from 'react';

import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Input } from '../../../../components/Forms/Input';
import { FormField } from '../../../../components/Forms/FormField';
import { Loading } from '../../../../components/UI/Loading';
import { ErrorState } from '../../../../components/UI/ErrorState';
import { useOrganizationSettings } from '../hooks/useOrganizationSettings';

interface FormState {
    name: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    country: string;
}

const EMPTY: FormState = { name: '', email: '', phone: '', address: '', city: '', country: '' };

/**
 * Coordonnées de l'organisation (espace PATRON).
 *
 * Carte d'identité du tenant : nom, contact et adresse du siège
 * (`PUT /v1/identity/organizations/{uuid}`). La présentation publique
 * (slug, description, logo) est du ressort de la page Vitrine, pour ne pas
 * mêler identité légale et vitrine.
 */
export function OrganizationProfileCard() {
    const { profile, isLoading, error, reload } = useOrganizationSettings();
    const [form, setForm] = useState<FormState>(EMPTY);

    useEffect(() => {
        if (profile) {
            setForm({
                name: profile.name,
                email: profile.email,
                phone: profile.phone,
                address: profile.address ?? '',
                city: profile.city ?? '',
                country: profile.country ?? '',
            });
        }
    }, [profile]);

    if (isLoading && !profile) {
        return <Card className="organization-table-card" padding="medium"><Loading text="Chargement des coordonnées…" /></Card>;
    }

    if (error && !profile) {
        return <Card className="organization-table-card" padding="medium"><ErrorState title="Impossible de charger l’organisation" message={error} onRetry={() => void reload()} /></Card>;
    }

    return (
        <Card className="organization-table-card" padding="medium">
            <div className="organization-table-toolbar">
                <div>
                    <h2>Organisation</h2>
                    <p>Coordonnées de l’entreprise : nom, contact et adresse du siège.</p>
                </div>
            </div>

            <p className="organization-management-form__hint">Consultation seule pour votre rôle. Pour modifier ces coordonnées, contactez un administrateur autorisé.</p>
            <div className="organization-management-form patron-organization-form">
                <div className="patron-organization-form__grid">
                    <FormField label="Nom de l’organisation" htmlFor="org-name" required>
                        <Input id="org-name" value={form.name} readOnly fullWidth />
                    </FormField>
                    <FormField label="Code" htmlFor="org-code" helpText="Identifiant interne, non modifiable.">
                        <Input id="org-code" value={profile?.code ?? ''} readOnly disabled fullWidth />
                    </FormField>
                    <FormField label="Adresse e-mail" htmlFor="org-email" required>
                        <Input id="org-email" type="email" value={form.email} readOnly fullWidth />
                    </FormField>
                    <FormField label="Téléphone" htmlFor="org-phone" required>
                        <Input id="org-phone" value={form.phone} readOnly fullWidth />
                    </FormField>
                    <FormField label="Adresse du siège" htmlFor="org-address">
                        <Input id="org-address" value={form.address || '—'} readOnly fullWidth />
                    </FormField>
                    <FormField label="Ville" htmlFor="org-city">
                        <Input id="org-city" value={form.city || '—'} readOnly fullWidth />
                    </FormField>
                    <FormField label="Pays" htmlFor="org-country">
                        <Input id="org-country" value={form.country || '—'} readOnly fullWidth />
                    </FormField>
                </div>
            </div>
        </Card>
    );
}
