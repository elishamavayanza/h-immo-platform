import { useEffect, useState, type FormEvent } from 'react';

import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Input } from '../../../../components/Forms/Input';
import { FormField } from '../../../../components/Forms/FormField';
import { Loading } from '../../../../components/UI/Loading';
import { ErrorState } from '../../../../components/UI/ErrorState';
import { fieldErrorMap } from '../../../shared/utils/actionErrors';
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
    const { profile, isLoading, error, reload, save } = useOrganizationSettings();
    const [form, setForm] = useState<FormState>(EMPTY);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

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

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (saving) return;
        setSaving(true);
        setErrors({});
        try {
            await save({
                name: form.name.trim(),
                email: form.email.trim(),
                phone: form.phone.trim(),
                address: form.address.trim(),
                city: form.city.trim(),
                country: form.country.trim(),
            });
        } catch (cause) {
            const map = fieldErrorMap(cause);
            if (Object.keys(map).length > 0) setErrors(map);
        } finally {
            setSaving(false);
        }
    };

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

            <form className="organization-management-form patron-organization-form" onSubmit={submit}>
                <div className="patron-organization-form__grid">
                    <FormField label="Nom de l’organisation" htmlFor="org-name" required error={errors.name}>
                        <Input id="org-name" value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} placeholder="Ex. : Immo Plus SARL" required fullWidth maxLength={150} />
                    </FormField>
                    <FormField label="Code" htmlFor="org-code" helpText="Identifiant interne, non modifiable.">
                        <Input id="org-code" value={profile?.code ?? ''} readOnly disabled fullWidth />
                    </FormField>
                    <FormField label="Adresse e-mail" htmlFor="org-email" required error={errors.email}>
                        <Input id="org-email" type="email" value={form.email} onChange={(event) => setForm((current) => ({ ...current, email: event.target.value }))} placeholder="contact@immoplus.cd" required fullWidth maxLength={180} />
                    </FormField>
                    <FormField label="Téléphone" htmlFor="org-phone" required error={errors.phone}>
                        <Input id="org-phone" value={form.phone} onChange={(event) => setForm((current) => ({ ...current, phone: event.target.value }))} placeholder="+243 99 00 00 000" required fullWidth maxLength={30} />
                    </FormField>
                    <FormField label="Adresse du siège" htmlFor="org-address" error={errors.address}>
                        <Input id="org-address" value={form.address} onChange={(event) => setForm((current) => ({ ...current, address: event.target.value }))} placeholder="Ex. : 12, avenue du Lac" fullWidth maxLength={255} />
                    </FormField>
                    <FormField label="Ville" htmlFor="org-city" error={errors.city}>
                        <Input id="org-city" value={form.city} onChange={(event) => setForm((current) => ({ ...current, city: event.target.value }))} placeholder="Ex. : Goma" fullWidth maxLength={100} />
                    </FormField>
                    <FormField label="Pays" htmlFor="org-country" error={errors.country}>
                        <Input id="org-country" value={form.country} onChange={(event) => setForm((current) => ({ ...current, country: event.target.value }))} placeholder="Ex. : République démocratique du Congo" fullWidth maxLength={100} />
                    </FormField>
                </div>

                <div className="patron-organization-form__actions">
                    <Button type="submit" isLoading={saving}>{saving ? 'Enregistrement…' : 'Enregistrer'}</Button>
                </div>
            </form>
        </Card>
    );
}
