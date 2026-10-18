import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { useNavigate, useParams } from 'react-router-dom';

import { ApiError } from '../../../../../services/api/api.types';
import { FormField } from '../../../../components/Forms/FormField';
import { Input } from '../../../../components/Forms/Input';
import { Alert } from '../../../../components/UI/Alert';
import { Button } from '../../../../components/UI/Button';
import { Card } from '../../../../components/UI/Card';
import { Loading } from '../../../../components/UI/Loading';
import { useOrganizations } from '../hooks/useOrganizations';
import { PlatformPageHeader } from '../../components/PlatformPageHeader';
import { OrganizationLogoPicker, type OrganizationLogoChange } from '../components/OrganizationLogoPicker';
import { CapacityLimitField } from '../components/CapacityLimitField';
import type { OrganizationCapacityData, OrganizationRow } from '../types/organization.types';
import { organizationsService } from '../services/organizationsService';
import '../../../../../styles/pages/super_admin/organizations/_organizations.scss';
import { changedFields } from '../../../shared/utils/changedFields';

type SetupStep = 1 | 2 | 3 | 4;
type SetupForm = {
    name: string;
    code: string;
    email: string;
    phone: string;
    city: string;
    address: string;
    country: string;
    showcaseEnabled: boolean;
    maxCities: number | null;
    maxParcels: number | null;
    maxBuildings: number | null;
    maxUnits: number | null;
    maxMembers: number | null;
    patronFullName: string;
    patronEmail: string;
    patronPhone: string;
};

const EMPTY_FORM: SetupForm = {
    name: '', code: '', email: '', phone: '', city: '', address: '', country: 'RDC', showcaseEnabled: false,
    maxCities: null, maxParcels: null, maxBuildings: null, maxUnits: null, maxMembers: null,
    patronFullName: '', patronEmail: '', patronPhone: '',
};
const CAPACITY_FIELDS = ['maxCities', 'maxParcels', 'maxBuildings', 'maxUnits', 'maxMembers'] as const;
const ORGANIZATION_FIELDS = ['name', 'code', 'email', 'phone', 'city', 'address', 'country'] as const;
const PATRON_FIELDS = ['patronFullName', 'patronEmail', 'patronPhone'] as const;

function Stepper({ step, steps }: { step: number; steps: string[] }) {
    return <>
        <ol className="sa-wizard__stepper" aria-label="Étapes de configuration" style={{ gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))` }}>
            {steps.map((label, index) => <li className={`sa-wizard__step-item${step > index + 1 ? ' sa-wizard__step-item--done' : ''}`} key={label}>
                <span className={`sa-wizard__step${step === index + 1 ? ' sa-wizard__step--active' : step > index + 1 ? ' sa-wizard__step--done' : ''}`} aria-current={step === index + 1 ? 'step' : undefined}>
                    <span className="sa-wizard__step-number" aria-hidden="true">{step > index + 1 ? '✓' : index + 1}</span><span className="sa-wizard__step-label">{label}</span>
                </span>
            </li>)}
        </ol>
        <div className="sa-wizard__intro"><strong>{steps[step - 1]}</strong><span>Étape {step} sur {steps.length}</span></div>
    </>;
}

export function OrganizationSetupPage() {
    const navigate = useNavigate();
    const { uuid } = useParams<{ uuid: string }>();
    const isEdit = Boolean(uuid);
    const { addOrganization, updateOrganization } = useOrganizations();
    const [step, setStep] = useState<SetupStep>(1);
    const [form, setForm] = useState<SetupForm>(EMPTY_FORM);
    const [organization, setOrganization] = useState<OrganizationRow | null>(null);
    const [capacity, setCapacity] = useState<OrganizationCapacityData | null>(null);
    const [logoChange, setLogoChange] = useState<OrganizationLogoChange>(null);
    const [loading, setLoading] = useState(isEdit);
    const [capacityLoading, setCapacityLoading] = useState(false);
    const [capacityError, setCapacityError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

    const steps = useMemo(() => isEdit ? ['Organisation', 'Capacités et options', 'Logo'] : ['Identité', 'Capacités', 'Compte PATRON', 'Logo'], [isEdit]);

    useEffect(() => {
        if (!uuid) return;
        let active = true;
        setLoading(true);
        void organizationsService.show(uuid)
            .then((row) => {
                if (!active) return;
                setOrganization(row);
                setForm({ ...EMPTY_FORM, ...row, showcaseEnabled: row.showcaseEnabled });
            })
            .catch((cause: unknown) => {
                if (active) setError(cause instanceof Error ? cause.message : 'Impossible de charger cette organisation.');
            })
            .finally(() => { if (active) setLoading(false); });
        return () => { active = false; };
    }, [uuid]);

    useEffect(() => {
        if (!uuid) return;
        let active = true;
        setCapacityLoading(true);
        setCapacityError(null);
        void organizationsService.capacity(uuid)
            .then((usage) => { if (active) setCapacity(usage); })
            .catch((cause: unknown) => { if (active) setCapacityError(cause instanceof Error ? cause.message : 'Impossible de charger les usages actuels.'); })
            .finally(() => { if (active) setCapacityLoading(false); });
        return () => { active = false; };
    }, [uuid]);

    const change = (key: keyof SetupForm, value: string) => setForm((current) => ({ ...current, [key]: value }));
    const returnToList = () => navigate('/app/admin/organisations');

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (submitting) return;
        setError(null);
        setFieldErrors({});
        if (step < steps.length) {
            setStep((current) => (current + 1) as SetupStep);
            return;
        }

        setSubmitting(true);
        try {
            if (!isEdit) {
                await addOrganization({
                    name: form.name.trim(), code: form.code.trim().toUpperCase(), email: form.email.trim(), phone: form.phone.trim(),
                    city: form.city.trim() || undefined, address: form.address.trim() || undefined, country: form.country.trim() || undefined,
                    maxCities: form.maxCities, maxParcels: form.maxParcels, maxBuildings: form.maxBuildings, maxUnits: form.maxUnits, maxMembers: form.maxMembers,
                    capacitySettingsProvided: true, patronFullName: form.patronFullName.trim(), patronEmail: form.patronEmail.trim(), patronPhone: form.patronPhone.trim(),
                    logoFile: logoChange?.kind === 'new' ? logoChange.file : null,
                });
            } else if (organization && uuid) {
                const changes = changedFields({
                    name: form.name.trim(), code: form.code.trim().toUpperCase(), email: form.email.trim(), phone: form.phone.trim(),
                    city: form.city.trim(), address: form.address.trim(), showcaseEnabled: form.showcaseEnabled,
                    status: organization.status,
                    logoFile: logoChange?.kind === 'new' ? logoChange.file : undefined,
                    removeLogo: logoChange?.kind === 'removed' ? true : undefined,
                }, {
                    name: organization.name.trim(), code: organization.code.trim().toUpperCase(), email: organization.email.trim(), phone: organization.phone.trim(),
                    city: organization.city.trim(), address: organization.address.trim(), showcaseEnabled: organization.showcaseEnabled,
                    status: organization.status, logoFile: undefined, removeLogo: undefined,
                });
                const payload: Parameters<typeof updateOrganization>[1] = { ...changes };
                if (CAPACITY_FIELDS.some((key) => form[key] !== organization[key])) {
                    Object.assign(payload, {
                        capacitySettingsProvided: true,
                        maxCities: form.maxCities, maxParcels: form.maxParcels, maxBuildings: form.maxBuildings,
                        maxUnits: form.maxUnits, maxMembers: form.maxMembers,
                    });
                }
                await updateOrganization(uuid, payload);
            }
            returnToList();
        } catch (cause) {
            if (cause instanceof ApiError) {
                const violations = Object.fromEntries(Object.entries(cause.data.errors ?? {}).filter(([key]) => key !== '_flush_description_'));
                setFieldErrors(violations);
                setError(cause.message);
                if (Object.keys(violations).some((key) => (ORGANIZATION_FIELDS as readonly string[]).includes(key))) setStep(1);
                else if (Object.keys(violations).some((key) => (PATRON_FIELDS as readonly string[]).includes(key))) setStep(3);
            } else {
                setError(cause instanceof Error ? cause.message : 'Une erreur inattendue est survenue.');
            }
        } finally {
            setSubmitting(false);
        }
    };

    const setCapacityValue = (key: typeof CAPACITY_FIELDS[number], value: number | null) => setForm((current) => ({ ...current, [key]: value }));

    if (loading) return <div className="sa-dashboard sa-management-page"><Loading text="Chargement de l’organisation…" /></div>;
    if (isEdit && (!organization || error)) return <div className="sa-dashboard sa-management-page"><PlatformPageHeader title="Organisation indisponible" description="Les informations de cette organisation n’ont pas pu être chargées." icon="briefcase" /><Alert variant="error">{error ?? 'Organisation introuvable.'}</Alert><Button variant="outline" onClick={returnToList}>Retour aux organisations</Button></div>;

    return <div className="sa-dashboard sa-management-page sa-organization-setup-page">
        <PlatformPageHeader title={isEdit ? 'Modifier une organisation' : 'Créer une organisation'} description={isEdit ? `Mettez à jour les informations de ${organization?.name}.` : 'Configurez progressivement une organisation et son accès à la plateforme.'} icon="briefcase" action={<Button variant="outline" onClick={returnToList}>Retour aux organisations</Button>} />
        <Card className="sa-organization-setup-card" padding="large">
            <Stepper step={step} steps={steps} />
            {error && <Alert variant="error">{error}</Alert>}
            <form className="sa-wizard" onSubmit={submit}>
                {step === 1 && <fieldset className="sa-wizard__fields">
                    <p className="sa-management-form__hint">Ces informations identifient l’organisation dans la plateforme et servent aux communications administratives.</p>
                    <FormField label="Nom de l’organisation" htmlFor="setup-org-name" required error={fieldErrors.name}><Input id="setup-org-name" placeholder="Ex. : Synerque Immobilier" value={form.name} onChange={(event) => change('name', event.target.value)} maxLength={150} autoComplete="organization" required fullWidth /></FormField>
                    <FormField label="Code unique" htmlFor="setup-org-code" required helpText="Identifiant court pour distinguer l’organisation." error={fieldErrors.code}><Input id="setup-org-code" placeholder="Ex. : SYNERQUE" value={form.code} onChange={(event) => change('code', event.target.value.toUpperCase())} maxLength={30} required fullWidth /></FormField>
                    <FormField label="E-mail de contact" htmlFor="setup-org-email" required error={fieldErrors.email}><Input id="setup-org-email" type="email" placeholder="Ex. : contact@synerque.cd" value={form.email} onChange={(event) => change('email', event.target.value)} maxLength={180} autoComplete="email" required fullWidth /></FormField>
                    <FormField label="Téléphone" htmlFor="setup-org-phone" required error={fieldErrors.phone}><Input id="setup-org-phone" placeholder="Ex. : +243 812 345 678" value={form.phone} onChange={(event) => change('phone', event.target.value)} maxLength={30} autoComplete="tel" required fullWidth /></FormField>
                    <FormField label="Ville du siège" htmlFor="setup-org-city"><Input id="setup-org-city" placeholder="Ex. : Kinshasa" value={form.city} onChange={(event) => change('city', event.target.value)} maxLength={100} autoComplete="address-level2" fullWidth /></FormField>
                    <FormField label="Adresse" htmlFor="setup-org-address"><Input id="setup-org-address" placeholder="Ex. : 12, avenue de la Paix" value={form.address} onChange={(event) => change('address', event.target.value)} maxLength={255} autoComplete="street-address" fullWidth /></FormField>
                    {!isEdit && <FormField label="Pays" htmlFor="setup-org-country"><Input id="setup-org-country" placeholder="Ex. : RDC" value={form.country} onChange={(event) => change('country', event.target.value)} maxLength={100} autoComplete="country-name" fullWidth /></FormField>}
                </fieldset>}

                {step === 2 && <fieldset className="sa-wizard__fields">
                    {isEdit && <label className="sa-management-form__toggle"><input type="checkbox" checked={form.showcaseEnabled} onChange={(event) => setForm((current) => ({ ...current, showcaseEnabled: event.target.checked }))} /><span><strong>Autoriser la vitrine immobilière</strong><small>Affiche le menu « Vitrine » aux rôles PATRON et ADMIN_IMMOBILIER de cette organisation.</small></span></label>}
                    <fieldset className="sa-capacity-fields"><legend>Capacités de l’organisation</legend><p className="sa-management-form__hint">Choisissez un plafond positif ou « Sans limite ». Modifier un plafond ne supprime aucune donnée existante.</p>
                        {isEdit && capacity && <div className="sa-capacity-preview"><strong>Utilisation actuelle</strong><div>{([['cities', 'Villes', 'maxCities'], ['parcels', 'Parcelles', 'maxParcels'], ['buildings', 'Bâtiments', 'maxBuildings'], ['units', 'Unités', 'maxUnits'], ['members', 'Membres actifs', 'maxMembers']] as const).map(([key, label, field]) => <span key={key}>{label} : {capacity.usage[key]} · plafond : {form[field] ?? 'Sans limite'}</span>)}</div></div>}
                        {isEdit && capacityError && <Alert variant="error">{capacityError}</Alert>}
                        {isEdit && capacityLoading && <p role="status">Chargement de l’utilisation actuelle…</p>}
                        {isEdit && capacity && (['cities', 'parcels', 'buildings', 'units', 'members'] as const).some((key) => {
                            const field = ({ cities: 'maxCities', parcels: 'maxParcels', buildings: 'maxBuildings', units: 'maxUnits', members: 'maxMembers' } as const)[key];
                            return form[field] !== null && capacity.usage[key] > form[field]!;
                        }) && <Alert variant="warning">Un plafond est inférieur à l’utilisation actuelle. Les données existantes restent accessibles ; les nouvelles créations concernées seront bloquées.</Alert>}
                        {([['maxCities', 'Villes'], ['maxParcels', 'Parcelles'], ['maxBuildings', 'Bâtiments'], ['maxUnits', 'Unités'], ['maxMembers', 'Membres actifs']] as const).map(([key, label]) => <CapacityLimitField key={key} id={`setup-${key}`} label={label} value={form[key]} onChange={(value) => setCapacityValue(key, value)} disabled={isEdit && (capacityLoading || capacityError !== null)} />)}
                    </fieldset>
                </fieldset>}

                {!isEdit && step === 3 && <fieldset className="sa-wizard__fields">
                    <p className="sa-management-form__hint">Le PATRON administre l’organisation. Son e-mail servira d’identifiant de connexion ; un lien de configuration du mot de passe sera envoyé.</p>
                    <FormField label="Nom complet du PATRON" htmlFor="setup-patron-name" required error={fieldErrors.patronFullName}><Input id="setup-patron-name" placeholder="Ex. : Jean Mukendi" value={form.patronFullName} onChange={(event) => change('patronFullName', event.target.value)} maxLength={200} autoComplete="name" required fullWidth /></FormField>
                    <FormField label="E-mail du PATRON" htmlFor="setup-patron-email" required error={fieldErrors.patronEmail}><Input id="setup-patron-email" type="email" placeholder="Ex. : jean.mukendi@synerque.cd" value={form.patronEmail} onChange={(event) => change('patronEmail', event.target.value)} maxLength={180} autoComplete="email" required fullWidth /></FormField>
                    <FormField label="Téléphone du PATRON" htmlFor="setup-patron-phone" required error={fieldErrors.patronPhone}><Input id="setup-patron-phone" placeholder="Ex. : +243 812 345 678" value={form.patronPhone} onChange={(event) => change('patronPhone', event.target.value)} maxLength={30} autoComplete="tel" required fullWidth /></FormField>
                </fieldset>}

                {((isEdit && step === 3) || (!isEdit && step === 4)) && <fieldset className="sa-wizard__fields">
                    <p className="sa-management-form__hint">Facultatif. Ajoutez ou remplacez le logo de l’organisation. Vous pourrez aussi le modifier plus tard.</p>
                    <OrganizationLogoPicker value={organization?.logo ?? null} change={logoChange} onChange={setLogoChange} />
                </fieldset>}

                <div className="sa-organization-setup-actions">
                    <Button type="button" variant="outline" onClick={step === 1 ? returnToList : () => setStep((current) => (current - 1) as SetupStep)}>{step === 1 ? 'Annuler' : 'Étape précédente'}</Button>
                    <Button type="submit" isLoading={submitting}>{submitting ? (isEdit ? 'Enregistrement…' : 'Création…') : step < steps.length ? 'Continuer' : (isEdit ? 'Enregistrer l’organisation' : 'Créer l’organisation')}</Button>
                </div>
            </form>
        </Card>
    </div>;
}
