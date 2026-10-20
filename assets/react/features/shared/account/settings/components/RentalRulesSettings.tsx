import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';

import { useOrganization } from '../../../../../app/providers/OrganizationProvider';
import { Button } from '../../../../../components/UI/Button';
import { Card } from '../../../../../components/UI/Card';
import { ConfirmDialog } from '../../../../../components/UI/ConfirmDialog';
import { FormField } from '../../../../../components/Forms/FormField';
import { Input } from '../../../../../components/Forms/Input';
import { Select } from '../../../../../components/Forms/Select';
import { ApiError, type Feedback } from '../../../../../../services/api/api.types';
import { apiClient } from '../../../../../../services/api/client';
import { administrationService } from '../../../../patron/administration/services/administrationService';
import type { AdminApiUser } from '../../../../patron/administration/types/administration.types';
import './RentalRulesSettings.scss';

type PaymentMode = 'in_advance' | 'in_arrears';
type PartialPolicy = 'clamp_regular_due_day' | 'coverage_start' | 'coverage_end';
type RentalRuleDraft = {
    uuid: string;
    effectiveFrom: string;
    dueMode: PaymentMode;
    dueDay: number;
    firstPartialPolicy: PartialPolicy;
    lastPartialPolicy: PartialPolicy;
    prorationMethod: 'actual_calendar_days';
    roundingRule: 'usd_half_up_cent';
    changeReason: string;
    published: boolean;
};
type RentalRuleVersion = Omit<RentalRuleDraft, 'changeReason' | 'published'> & {
    version: number;
    publicationReason: string;
    publishedAt: string;
};
type DraftForm = Omit<RentalRuleDraft, 'uuid' | 'published'>;
type RentalRuleCapabilities = { canView: boolean; canPrepare: boolean; canPublish: boolean; canManageDelegations: boolean };
type PublicationDelegation = { uuid: string; userUuid: string; permission: string; active: boolean; expiresAt: string | null; revokedAt: string | null; revocationReason: string | null };

const nextMonthStart = () => {
    const today = new Date();
    return `${today.getFullYear() + (today.getMonth() === 11 ? 1 : 0)}-${String((today.getMonth() + 1) % 12 + 1).padStart(2, '0')}-01`;
};

const initialDraft = (): DraftForm => ({
    effectiveFrom: nextMonthStart(),
    dueMode: 'in_advance',
    dueDay: 5,
    firstPartialPolicy: 'clamp_regular_due_day',
    lastPartialPolicy: 'clamp_regular_due_day',
    prorationMethod: 'actual_calendar_days',
    roundingRule: 'usd_half_up_cent',
    changeReason: '',
});

const paymentLabel = (value: PaymentMode) => value === 'in_advance' ? 'Payable d’avance' : 'Payable à terme échu';
const partialLabel = (value: PartialPolicy) => ({
    clamp_regular_due_day: 'Jour habituel, limité aux dates couvertes',
    coverage_start: 'Au début de la période couverte',
    coverage_end: 'À la fin de la période couverte',
}[value]);

function messageFor(error: unknown, fallback: string): string {
    if (!(error instanceof ApiError)) return error instanceof Error ? error.message : fallback;
    if (error.status === 401) return 'Votre session a expiré. Reconnectez-vous avant de continuer.';
    if (error.status === 403) return 'Votre rôle ne permet pas cette action. Les autorisations sont contrôlées par le serveur.';
    if (error.status === 404) return 'Cette règle ou cette organisation est introuvable.';
    if (error.status === 409) return `${error.message} Actualisez les règles puis réessayez.`;
    return error.message || fallback;
}

export function RentalRulesSettings() {
    const { currentOrganization, organizationRole } = useOrganization();
    const [versions, setVersions] = useState<RentalRuleVersion[]>([]);
    const [drafts, setDrafts] = useState<RentalRuleDraft[]>([]);
    const [capabilities, setCapabilities] = useState<RentalRuleCapabilities | null>(null);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [publishingUuid, setPublishingUuid] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [draftForm, setDraftForm] = useState<DraftForm>(initialDraft);
    const [editingUuid, setEditingUuid] = useState<string | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);
    const [formFieldErrors, setFormFieldErrors] = useState<Record<string, string>>({});
    const [publishTarget, setPublishTarget] = useState<RentalRuleDraft | null>(null);
    const [delegations, setDelegations] = useState<PublicationDelegation[]>([]);
    const [eligibleMembers, setEligibleMembers] = useState<AdminApiUser[]>([]);
    const [delegateUserUuid, setDelegateUserUuid] = useState('');
    const [delegationExpiry, setDelegationExpiry] = useState('');
    const [delegationError, setDelegationError] = useState<string | null>(null);
    const [delegationBusy, setDelegationBusy] = useState(false);
    const [pendingRevoke, setPendingRevoke] = useState<PublicationDelegation | null>(null);
    const loadSequence = useRef(0);
    const saveInFlight = useRef(false);
    const publishInFlight = useRef(false);
    const canPrepare = capabilities?.canPrepare ?? (organizationRole === 'patron' || organizationRole === 'admin_immobilier');
    const canPublishInUi = capabilities?.canPublish ?? organizationRole === 'patron';
    const canView = ['patron', 'admin_immobilier', 'admin_ville'].includes(organizationRole ?? '');

    const reload = useCallback(async () => {
        const requestId = ++loadSequence.current;
        if (!currentOrganization || !canView) {
            setVersions([]);
            setDrafts([]);
            setCapabilities(null);
            setError(null);
            setLoading(false);
            return;
        }
        setLoading(true);
        setError(null);
        setDelegationError(null);
        const base = `/v1/organizations/${currentOrganization.uuid}/rental-rules`;
        const [versionResult, draftResult, capabilityResult] = await Promise.allSettled([
            apiClient.get<Feedback<RentalRuleVersion[]>>(`${base}/versions`),
            canPrepare ? apiClient.get<Feedback<RentalRuleDraft[]>>(`${base}/drafts`) : Promise.resolve(null),
            apiClient.get<Feedback<RentalRuleCapabilities>>(`${base}/capabilities`),
        ]);
        if (requestId !== loadSequence.current) return;
        let loadError: string | null = null;
        if (versionResult.status === 'fulfilled') setVersions(versionResult.value.data.data);
        else {
            setVersions([]);
            loadError = messageFor(versionResult.reason, 'Impossible de charger les versions des règles locatives.');
        }
        if (draftResult.status === 'fulfilled' && draftResult.value) setDrafts(draftResult.value.data.data);
        else if (draftResult.status === 'rejected' && !loadError) loadError = messageFor(draftResult.reason, 'Impossible de charger les brouillons.');
        if (capabilityResult.status === 'fulfilled') setCapabilities(capabilityResult.value.data.data);
        else setCapabilities(null);
        const canManageDelegations = capabilityResult.status === 'fulfilled' && capabilityResult.value.data.data.canManageDelegations;
        if (canManageDelegations) {
            const [delegationResult, membersResult] = await Promise.allSettled([
                apiClient.get<Feedback<PublicationDelegation[]>>(`${base}/delegations`),
                administrationService.list(currentOrganization.uuid),
            ]);
            if (requestId !== loadSequence.current) return;
            if (delegationResult.status === 'fulfilled') setDelegations(delegationResult.value.data.data);
            else setDelegationError(messageFor(delegationResult.reason, 'Impossible de charger les délégations.'));
            if (membersResult.status === 'fulfilled') {
                const eligible = membersResult.value.filter((member) => member.isActive && member.memberships.some((membership) => membership.organizationId === currentOrganization.uuid && membership.role === 'admin_immobilier'));
                setEligibleMembers(eligible);
                setDelegateUserUuid((current) => eligible.some((member) => member.id === current) ? current : eligible[0]?.id ?? '');
            } else {
                setEligibleMembers([]);
                setDelegationError((current) => current ?? messageFor(membersResult.reason, 'Impossible de charger les membres autorisés.'));
            }
        } else {
            setDelegations([]);
            setEligibleMembers([]);
            setDelegationError(null);
        }
        setError(loadError);
        setLoading(false);
    }, [currentOrganization, canView, canPrepare]);

    useEffect(() => {
        void reload();
        return () => { loadSequence.current += 1; };
    }, [reload]);

    const startNewDraft = () => {
        setEditingUuid(null);
        setDraftForm(initialDraft());
        setFormError(null);
        setFormFieldErrors({});
        setFormOpen(true);
    };

    const editDraft = (draft: RentalRuleDraft) => {
        if (draft.published) return;
        setEditingUuid(draft.uuid);
        setDraftForm({
            effectiveFrom: draft.effectiveFrom,
            dueMode: draft.dueMode,
            dueDay: draft.dueDay,
            firstPartialPolicy: draft.firstPartialPolicy,
            lastPartialPolicy: draft.lastPartialPolicy,
            prorationMethod: draft.prorationMethod,
            roundingRule: draft.roundingRule,
            changeReason: draft.changeReason,
        });
        setFormError(null);
        setFormFieldErrors({});
        setFormOpen(true);
    };

    const saveDraft = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!currentOrganization || saveInFlight.current) return;
        saveInFlight.current = true;
        setSaving(true);
        setFormError(null);
        setFormFieldErrors({});
        try {
            const base = `/v1/organizations/${currentOrganization.uuid}/rental-rules/drafts`;
            const response = editingUuid
                ? await apiClient.put<Feedback<RentalRuleDraft>>(`${base}/${editingUuid}`, draftForm)
                : await apiClient.post<Feedback<RentalRuleDraft>>(base, draftForm);
            setDrafts((current) => editingUuid
                ? current.map((draft) => draft.uuid === editingUuid ? response.data.data : draft)
                : [...current, response.data.data]);
            setFormOpen(false);
        } catch (cause) {
            if (cause instanceof ApiError && cause.status === 422) {
                const violations = cause.violations;
                setFormFieldErrors(Object.fromEntries(Object.entries(violations).map(([field, messages]) => [field, messages.join(' ')])));
                setFormError('Corrigez les champs signalés. Vos valeurs ont été conservées.');
            } else {
                setFormError(messageFor(cause, 'Le brouillon n’a pas pu être enregistré. Vos valeurs restent disponibles.'));
            }
        } finally {
            saveInFlight.current = false;
            setSaving(false);
        }
    };

    const publishDraft = async () => {
        if (!currentOrganization || !publishTarget || publishInFlight.current) return;
        publishInFlight.current = true;
        const target = publishTarget;
        setPublishingUuid(target.uuid);
        setError(null);
        try {
            const { data } = await apiClient.post<Feedback<RentalRuleVersion>>(
                `/v1/organizations/${currentOrganization.uuid}/rental-rules/drafts/${target.uuid}/publish`,
            );
            setVersions((current) => [...current, data.data].sort((a, b) => a.effectiveFrom.localeCompare(b.effectiveFrom)));
            setDrafts((current) => current.map((draft) => draft.uuid === target.uuid ? { ...draft, published: true } : draft));
        } catch (cause) {
            setError(messageFor(cause, 'La publication n’a pas été confirmée par le serveur.'));
        } finally {
            publishInFlight.current = false;
            setPublishingUuid(null);
            setPublishTarget(null);
        }
    };

    const grantDelegation = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!currentOrganization || !delegateUserUuid || !delegationExpiry || delegationBusy) return;
        setDelegationBusy(true);
        setDelegationError(null);
        try {
            const expiresAt = new Date(delegationExpiry);
            if (!Number.isFinite(expiresAt.getTime()) || expiresAt <= new Date()) throw new Error('Choisissez une date et une heure futures.');
            const { data } = await apiClient.post<Feedback<PublicationDelegation>>(
                `/v1/organizations/${currentOrganization.uuid}/rental-rules/delegations`,
                { userUuid: delegateUserUuid, expiresAt: expiresAt.toISOString() },
            );
            setDelegations((current) => [...current.filter((entry) => entry.uuid !== data.data.uuid), data.data]);
            setDelegationExpiry('');
        } catch (cause) {
            setDelegationError(messageFor(cause, 'La délégation n’a pas été enregistrée.'));
        } finally {
            setDelegationBusy(false);
        }
    };

    const revokeDelegation = async () => {
        if (!currentOrganization || !pendingRevoke || delegationBusy) return;
        setDelegationBusy(true);
        setDelegationError(null);
        try {
            const { data } = await apiClient.post<Feedback<PublicationDelegation>>(
                `/v1/organizations/${currentOrganization.uuid}/rental-rules/delegations/${pendingRevoke.uuid}/revoke`,
                { reason: 'Retrait demandé depuis les paramètres des règles locatives.' },
            );
            setDelegations((current) => current.map((entry) => entry.uuid === pendingRevoke.uuid ? data.data : entry));
        } catch (cause) {
            setDelegationError(messageFor(cause, 'La délégation n’a pas pu être révoquée.'));
        } finally {
            setDelegationBusy(false);
            setPendingRevoke(null);
        }
    };

    if (!canView) return null;

    return <section id="settings-rental-rules" className="settings-section">
        <Card header="Règles de loyer" className="settings-card rental-rules-settings">
            <p>Consultez les versions publiées et préparez une règle qui s’appliquera aux futurs calculs à partir de sa date d’effet.</p>
            <p className="rental-rules-settings__notice">Une publication ne modifie pas les échéances déjà émises ni les paiements enregistrés. L’aperçu d’un bail est disponible dans le formulaire de création.</p>
            {loading && <p role="status">Chargement des règles locatives…</p>}
            {error && <div className="rental-rules-settings__error" role="alert"><p>{error}</p><Button variant="secondary" onClick={() => void reload()}>Réessayer</Button></div>}

            {!loading && !error && versions.length === 0 && <p className="rental-rules-settings__empty">Aucune version publiée n’est disponible pour cette organisation. Aucun choix par défaut n’est présenté comme une règle déjà enregistrée.</p>}
            {versions.length > 0 && <div className="rental-rules-settings__history" aria-label="Versions publiées des règles locatives">
                <h3>Versions publiées</h3>
                {versions.map((version) => <article className="rental-rules-settings__version" key={version.uuid}>
                    <div className="rental-rules-settings__version-heading"><strong>Version {version.version}</strong><span>Prend effet le {version.effectiveFrom}</span></div>
                    <p>{paymentLabel(version.dueMode)}, le {version.dueDay} du mois.</p>
                    <p>Premier mois partiel : {partialLabel(version.firstPartialPolicy)}. Dernier mois partiel : {partialLabel(version.lastPartialPolicy)}.</p>
                    <small>Publiée le {new Date(version.publishedAt).toLocaleDateString('fr-FR')} · {version.publicationReason}</small>
                </article>)}
            </div>}

            {canPrepare && <div className="rental-rules-settings__actions"><Button onClick={startNewDraft}>Préparer une nouvelle règle</Button></div>}
            {organizationRole === 'admin_immobilier' && <p className="rental-rules-settings__notice">La publication est affichée uniquement si le serveur confirme une délégation active pour votre compte. Le serveur revérifie cette autorisation au moment de publier.</p>}

            {capabilities?.canManageDelegations && <div className="rental-rules-settings__delegations">
                <h3>Autoriser la publication</h3>
                <p>Le PATRON peut déléguer temporairement la publication des règles à un ADMIN_IMMOBILIER actif de cette organisation. Cette autorisation est vérifiée par le serveur et peut être retirée à tout moment.</p>
                {delegationError && <div className="rental-rules-settings__error" role="alert"><p>{delegationError}</p><Button variant="secondary" onClick={() => void reload()}>Actualiser</Button></div>}
                <form className="rental-rules-settings__delegation-form" onSubmit={(event) => void grantDelegation(event)}>
                    <FormField label="Administrateur immobilier" htmlFor="rental-rule-delegate-user">
                        <Select id="rental-rule-delegate-user" value={delegateUserUuid} onChange={(event) => setDelegateUserUuid(event.target.value)} options={eligibleMembers.map((member) => ({ value: member.id, label: `${member.fullName} · ${member.email}` }))} placeholder="Choisir un membre actif" disabled={eligibleMembers.length === 0 || delegationBusy} required />
                    </FormField>
                    <FormField label="Expiration de la délégation" htmlFor="rental-rule-delegate-expiry" helpText="Une date future est obligatoire pour limiter la durée de cette autorisation.">
                        <Input id="rental-rule-delegate-expiry" type="datetime-local" value={delegationExpiry} onChange={(event) => setDelegationExpiry(event.target.value)} required fullWidth />
                    </FormField>
                    <div className="rental-rules-settings__actions"><Button type="submit" disabled={delegationBusy || !delegateUserUuid || !delegationExpiry}>{delegationBusy ? 'Traitement…' : 'Déléguer la publication'}</Button></div>
                </form>
                {eligibleMembers.length === 0 && <p className="rental-rules-settings__empty">Aucun ADMIN_IMMOBILIER actif de cette organisation n’est disponible pour recevoir la délégation.</p>}
                {delegations.length > 0 ? <ul className="rental-rules-settings__delegation-list">{delegations.map((delegation) => {
                    const member = eligibleMembers.find((candidate) => candidate.id === delegation.userUuid);
                    return <li key={delegation.uuid}><div><strong>{member?.fullName ?? 'Membre de l’organisation'}</strong><span>{delegation.active ? 'Active' : 'Inactive'} · expire {delegation.expiresAt ? new Date(delegation.expiresAt).toLocaleString('fr-FR') : 'sans date connue'}</span></div>{delegation.active && <Button variant="secondary" disabled={delegationBusy} onClick={() => setPendingRevoke(delegation)}>Retirer l’accès</Button>}</li>;
                })}</ul> : <p className="rental-rules-settings__empty">Aucune délégation de publication enregistrée.</p>}
            </div>}

            {drafts.length > 0 && <div className="rental-rules-settings__drafts">
                <h3>Brouillons</h3>
                {drafts.map((draft) => <article className="rental-rules-settings__draft" key={draft.uuid}>
                    <div><strong>{draft.published ? 'Publié · lecture seule' : 'Brouillon à compléter'}</strong><p>Effet prévu le {draft.effectiveFrom} · {paymentLabel(draft.dueMode)}, le {draft.dueDay}.</p><p>Premier mois partiel : {partialLabel(draft.firstPartialPolicy)}. Dernier mois partiel : {partialLabel(draft.lastPartialPolicy)}.</p><small>Motif : {draft.changeReason}</small></div>
                    <div className="rental-rules-settings__draft-actions">
                        {!draft.published && <Button variant="secondary" onClick={() => editDraft(draft)}>Modifier</Button>}
                        {!draft.published && canPublishInUi && <Button disabled={publishingUuid !== null} onClick={() => setPublishTarget(draft)}>{publishingUuid === draft.uuid ? 'Publication…' : 'Publier'}</Button>}
                    </div>
                </article>)}
            </div>}

            {formOpen && <form className="rental-rules-settings__form" onSubmit={(event) => void saveDraft(event)}>
                <h3>{editingUuid ? 'Modifier le brouillon' : 'Nouveau brouillon'}</h3>
                <p>Les valeurs initiales proposées sont des choix de départ du formulaire, pas une règle enregistrée. Vérifiez-les avant l’enregistrement.</p>
                {formError && <div className="rental-rules-settings__error" role="alert">{formError}</div>}
                <div className="rental-rules-settings__fields">
                    <FormField label="Quand le loyer doit-il être payé ?" htmlFor="rental-rule-mode" error={formFieldErrors.dueMode}>
                        <Select id="rental-rule-mode" value={draftForm.dueMode} onChange={(event) => setDraftForm((current) => ({ ...current, dueMode: event.target.value as PaymentMode }))} options={[{ value: 'in_advance', label: 'D’avance' }, { value: 'in_arrears', label: 'À terme échu' }]} />
                    </FormField>
                    <FormField label="Quel jour du mois ?" htmlFor="rental-rule-day" error={formFieldErrors.dueDay} helpText="Pour un mois partiel, ce jour est limité aux dates couvertes selon la règle choisie.">
                        <Input id="rental-rule-day" type="number" min="1" max="31" step="1" value={draftForm.dueDay} onChange={(event) => setDraftForm((current) => ({ ...current, dueDay: Number(event.target.value) }))} required fullWidth />
                    </FormField>
                    <FormField label="Premier mois partiel" htmlFor="rental-rule-first" error={formFieldErrors.firstPartialPolicy}>
                        <Select id="rental-rule-first" value={draftForm.firstPartialPolicy} onChange={(event) => setDraftForm((current) => ({ ...current, firstPartialPolicy: event.target.value as PartialPolicy }))} options={[{ value: 'clamp_regular_due_day', label: 'Jour habituel, limité à la période' }, { value: 'coverage_start', label: 'Début de la période' }, { value: 'coverage_end', label: 'Fin de la période' }]} />
                    </FormField>
                    <FormField label="Dernier mois partiel" htmlFor="rental-rule-last" error={formFieldErrors.lastPartialPolicy}>
                        <Select id="rental-rule-last" value={draftForm.lastPartialPolicy} onChange={(event) => setDraftForm((current) => ({ ...current, lastPartialPolicy: event.target.value as PartialPolicy }))} options={[{ value: 'clamp_regular_due_day', label: 'Jour habituel, limité à la période' }, { value: 'coverage_start', label: 'Début de la période' }, { value: 'coverage_end', label: 'Fin de la période' }]} />
                    </FormField>
                    <FormField label="Date d’entrée en vigueur" htmlFor="rental-rule-effective" error={formFieldErrors.effectiveFrom} helpText="La date doit être le premier jour d’un mois.">
                        <Input id="rental-rule-effective" type="date" value={draftForm.effectiveFrom} onChange={(event) => setDraftForm((current) => ({ ...current, effectiveFrom: event.target.value }))} required fullWidth />
                    </FormField>
                    <FormField label="Pourquoi cette règle change-t-elle ?" htmlFor="rental-rule-reason" error={formFieldErrors.changeReason}>
                        <Input id="rental-rule-reason" value={draftForm.changeReason} onChange={(event) => setDraftForm((current) => ({ ...current, changeReason: event.target.value }))} maxLength={500} required fullWidth />
                    </FormField>
                </div>
                <p className="rental-rules-settings__notice">Le calcul du prorata en USD utilise les jours calendaires réels et l’arrondi au centime. Le prorata en CDF reste bloqué tant que ses règles ne sont pas validées. Aucun montant simulé n’est affiché ici.</p>
                <div className="rental-rules-settings__actions"><Button type="button" variant="secondary" onClick={() => setFormOpen(false)}>Annuler</Button><Button type="submit" disabled={saving}>{saving ? 'Enregistrement…' : 'Enregistrer le brouillon'}</Button></div>
            </form>}
            <p className="rental-rules-settings__link"><Link to="/app/location/loyers">Consulter les échéances de loyer</Link></p>
        </Card>
        <ConfirmDialog isOpen={publishTarget !== null} onClose={() => setPublishTarget(null)} onCancel={() => setPublishTarget(null)} onConfirm={() => void publishDraft()} title="Publier cette règle ?" message={publishTarget ? <div><p>À partir du {publishTarget.effectiveFrom} : {paymentLabel(publishTarget.dueMode)}, le {publishTarget.dueDay}.</p><p>Premier mois partiel : {partialLabel(publishTarget.firstPartialPolicy)}. Dernier mois partiel : {partialLabel(publishTarget.lastPartialPolicy)}.</p><p>Cette publication concerne les calculs futurs à partir de sa date d’effet. Les échéances déjà émises et les paiements ne seront pas modifiés.</p></div> : ''} confirmLabel="Publier la règle" cancelLabel="Annuler" />
        <ConfirmDialog isOpen={pendingRevoke !== null} onClose={() => setPendingRevoke(null)} onCancel={() => setPendingRevoke(null)} onConfirm={() => void revokeDelegation()} title="Retirer cette autorisation ?" message="L’ADMIN_IMMOBILIER ne pourra plus publier les règles locatives de cette organisation. Les règles déjà publiées resteront inchangées." confirmLabel={delegationBusy ? 'Retrait…' : 'Retirer l’accès'} cancelLabel="Garder l’accès" />
    </section>;
}
