import { useEffect, useState, type FormEvent } from 'react';

import { ApiError } from '../../../../../services/api/api.types';
import { Alert } from '../../../../components/UI/Alert';
import { Button } from '../../../../components/UI/Button';
import { Input } from '../../../../components/Forms/Input';
import { FormField } from '../../../../components/Forms/FormField';
import { Modal } from '../../../../components/UI/Modal';
import { OrganizationLogoPicker, type OrganizationLogoChange } from './OrganizationLogoPicker';
import type { OrganizationCreatePayload, OrganizationCreateResult } from '../types/organization.types';

/**
 * Champs de l'étape 1. Utilisée pour ramener l'utilisateur sur la bonne
 * étape quand le backend signale une violation sur un champ organisation.
 */
const ORG_STEP_FIELDS = ['name', 'code', 'email', 'phone', 'city', 'address', 'country'] as const;

const EMPTY_ORG = { name: '', code: '', email: '', phone: '', city: '', address: '', country: 'RDC' };
const EMPTY_PATRON = { fullName: '', email: '', phone: '' };

interface OrganizationWizardModalProps {
    isOpen: boolean;
    onClose: () => void;
    /** Exécute le POST atomique (organisation + PATRON) et renvoie le résultat + feedback backend. */
    onCreate: (payload: OrganizationCreatePayload) => Promise<OrganizationCreateResult>;
    /** Rappel optionnel après une création réussie (le succès est signalé par toast côté hook). */
    onCreated?: (result: OrganizationCreateResult) => void;
}

/**
 * Wizard de création en deux étapes :
 *  1. L'organisation (nom, code, contacts, siège)
 *  2. Le PATRON (nom, email, téléphone)
 *
 * Les deux étapes sont collectées puis envoyées dans UN SEUL
 * `POST /v1/identity/organizations` : le backend crée le tenant et son
 * PATRON dans une transaction unique (une organisation sans PATRON serait
 * inaccessible). Séparer en deux appels créerait une fenêtre où le tenant
 * n'a aucun administrateur si la seconde requête échoue.
 */
export function OrganizationWizardModal({ isOpen, onClose, onCreate, onCreated }: OrganizationWizardModalProps) {
    const [step, setStep] = useState<1 | 2 | 3>(1);
    const [org, setOrg] = useState(EMPTY_ORG);
    const [patron, setPatron] = useState(EMPTY_PATRON);
    const [logoChange, setLogoChange] = useState<OrganizationLogoChange>(null);
    const [submitting, setSubmitting] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [globalError, setGlobalError] = useState<string | null>(null);

    useEffect(() => {
        if (isOpen) {
            setStep(1);
            setOrg(EMPTY_ORG);
            setPatron(EMPTY_PATRON);
            setLogoChange(null);
            setSubmitting(false);
            setFieldErrors({});
            setGlobalError(null);
        }
    }, [isOpen]);

    const changeOrg = (key: keyof typeof EMPTY_ORG, value: string) => setOrg((current) => ({ ...current, [key]: value }));
    const changePatron = (key: keyof typeof EMPTY_PATRON, value: string) => setPatron((current) => ({ ...current, [key]: value }));

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (submitting) return;

        if (step === 1) {
            setFieldErrors({});
            setGlobalError(null);
            setStep(2);
            return;
        }

        if (step === 2) {
            setFieldErrors({});
            setGlobalError(null);
            setStep(3);
            return;
        }

        void submit();
    };

    const submit = async () => {
        setSubmitting(true);
        setFieldErrors({});
        setGlobalError(null);

        const payload: OrganizationCreatePayload = {
            name: org.name.trim(),
            code: org.code.trim().toUpperCase(),
            email: org.email.trim(),
            phone: org.phone.trim(),
            city: org.city.trim() || undefined,
            address: org.address.trim() || undefined,
            country: org.country.trim() || undefined,
            patronFullName: patron.fullName.trim(),
            patronEmail: patron.email.trim(),
            patronPhone: patron.phone.trim(),
            logoFile: logoChange?.kind === 'new' ? logoChange.file : null,
        };

        try {
            const result = await onCreate(payload);
            onCreated?.(result);
            onClose();
        } catch (cause) {
            if (cause instanceof ApiError) {
                const fieldErrors: Record<string, string> = {};
                for (const [field, message] of Object.entries(cause.data.errors ?? {})) {
                    if (field === '_flush_description_') continue;
                    fieldErrors[field] = message;
                }
                setFieldErrors(fieldErrors);
                setGlobalError(cause.message);
                if (Object.keys(fieldErrors).some((field) => (ORG_STEP_FIELDS as readonly string[]).includes(field))) {
                    setStep(1);
                }
            } else {
                setGlobalError('Une erreur inattendue est survenue. Réessayez.');
            }
        } finally {
            setSubmitting(false);
        }
    };

    const goBack = () => {
        setFieldErrors({});
        setGlobalError(null);
        setStep(1);
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title="Nouvelle organisation"
            size="medium"
            footer={step === 1 ? (
                <>
                    <Button variant="outline" onClick={onClose}>Annuler</Button>
                    <Button type="submit" form="organization-wizard-form">Continuer</Button>
                </>
            ) : step === 2 ? (
                <>
                    <Button variant="outline" onClick={goBack}>Précédent</Button>
                    <Button type="submit" form="organization-wizard-form">Continuer</Button>
                </>
            ) : (
                <>
                    <Button variant="outline" onClick={goBack}>Précédent</Button>
                    <Button type="submit" form="organization-wizard-form" isLoading={submitting}>{submitting ? 'Création…' : 'Créer l’organisation'}</Button>
                </>
            )}
        >
            <form id="organization-wizard-form" className="sa-wizard" onSubmit={handleSubmit}>
                <ol className="sa-wizard__stepper" aria-label="Étapes de création">
                    <li className={`sa-wizard__step${step === 1 ? ' sa-wizard__step--active' : ' sa-wizard__step--done'}`}>
                        <span className="sa-wizard__step-number" aria-hidden="true">{step === 1 ? '1' : '✓'}</span>
                        <span>Organisation</span>
                    </li>
                    <li className="sa-wizard__connector" aria-hidden="true" />
                    <li className={`sa-wizard__step${step === 2 ? ' sa-wizard__step--active' : step > 2 ? ' sa-wizard__step--done' : ''}`}>
                        <span className="sa-wizard__step-number" aria-hidden="true">{step === 2 ? '2' : step > 2 ? '✓' : '2'}</span>
                        <span>PATRON</span>
                    </li>
                    <li className="sa-wizard__connector" aria-hidden="true" />
                    <li className={`sa-wizard__step${step === 3 ? ' sa-wizard__step--active' : ''}`}>
                        <span className="sa-wizard__step-number" aria-hidden="true">3</span>
                        <span>Logo</span>
                    </li>
                </ol>

                {globalError && <Alert variant="error">{globalError}</Alert>}

                {step === 1 ? (
                    <fieldset className="sa-wizard__fields">
                        <FormField label="Nom de l’organisation" htmlFor="wizard-org-name" required error={fieldErrors.name}>
                            <Input id="wizard-org-name" placeholder="Ex. : Synerque Immobilier" value={org.name} onChange={(event) => changeOrg('name', event.target.value)} maxLength={150} required fullWidth />
                        </FormField>
                        <FormField label="Code unique" htmlFor="wizard-org-code" required helpText="Identifiant court, utilisé pour l’isolation multi-tenant." error={fieldErrors.code}>
                            <Input id="wizard-org-code" placeholder="Ex. : SYNERQUE" value={org.code} onChange={(event) => changeOrg('code', event.target.value.toUpperCase())} maxLength={30} required fullWidth />
                        </FormField>
                        <FormField label="E-mail de contact" htmlFor="wizard-org-email" required error={fieldErrors.email}>
                            <Input id="wizard-org-email" type="email" placeholder="exemple@entreprise.com" value={org.email} onChange={(event) => changeOrg('email', event.target.value)} maxLength={180} required fullWidth />
                        </FormField>
                        <FormField label="Téléphone" htmlFor="wizard-org-phone" required error={fieldErrors.phone}>
                            <Input id="wizard-org-phone" placeholder="+243 000 000 000" value={org.phone} onChange={(event) => changeOrg('phone', event.target.value)} maxLength={30} required fullWidth />
                        </FormField>
                        <FormField label="Ville du siège" htmlFor="wizard-org-city" required error={fieldErrors.city}>
                            <Input id="wizard-org-city" placeholder="Ex. : Kinshasa" value={org.city} onChange={(event) => changeOrg('city', event.target.value)} maxLength={100} required fullWidth />
                        </FormField>
                        <FormField label="Adresse" htmlFor="wizard-org-address" error={fieldErrors.address}>
                            <Input id="wizard-org-address" placeholder="Ex. : Avenue de la Paix, n°12" value={org.address} onChange={(event) => changeOrg('address', event.target.value)} maxLength={255} fullWidth />
                        </FormField>
                        <FormField label="Pays" htmlFor="wizard-org-country" error={fieldErrors.country}>
                            <Input id="wizard-org-country" placeholder="Ex. : RDC" value={org.country} onChange={(event) => changeOrg('country', event.target.value)} maxLength={100} fullWidth />
                        </FormField>
                    </fieldset>
                ) : step === 2 ? (
                    <fieldset className="sa-wizard__fields">
                        <p className="sa-management-form__hint">
                            Le PATRON est le compte administrateur de l’organisation. L’organisation et son PATRON
                            sont créés en une seule transaction : son e-mail servira d’identifiant de connexion, et
                            un lien de configuration du mot de passe lui sera envoyé.
                        </p>
                        <FormField label="Nom complet du PATRON" htmlFor="wizard-patron-name" required error={fieldErrors.patronFullName}>
                            <Input id="wizard-patron-name" placeholder="Ex. : Jean MUKENDI" value={patron.fullName} onChange={(event) => changePatron('fullName', event.target.value)} maxLength={200} required fullWidth />
                        </FormField>
                        <FormField label="E-mail du PATRON" htmlFor="wizard-patron-email" required error={fieldErrors.patronEmail}>
                            <Input id="wizard-patron-email" type="email" placeholder="jean.mukendi@entreprise.com" value={patron.email} onChange={(event) => changePatron('email', event.target.value)} maxLength={180} required fullWidth />
                        </FormField>
                        <FormField label="Téléphone du PATRON" htmlFor="wizard-patron-phone" required error={fieldErrors.patronPhone}>
                            <Input id="wizard-patron-phone" placeholder="+243 000 000 000" value={patron.phone} onChange={(event) => changePatron('phone', event.target.value)} maxLength={30} required fullWidth />
                        </FormField>
                    </fieldset>
                ) : (
                    <fieldset className="sa-wizard__fields">
                        <p className="sa-management-form__hint">
                            Facultatif. Le logo est retaillé puis envoyé après la création de l’organisation ; il
                            apparaîtra dans la liste des organisations.
                        </p>
                        <OrganizationLogoPicker value={null} change={logoChange} onChange={setLogoChange} />
                    </fieldset>
                )}
            </form>
        </Modal>
    );
}