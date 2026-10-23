import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Badge } from '../../../../components/UI/Badge';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { Spinner } from '../../../../components/UI/Spinner';
import { Input } from '../../../../components/Forms/Input';
import { Select } from '../../../../components/Forms/Select';
import { Textarea } from '../../../../components/Forms/Textarea';
import { FormField } from '../../../../components/Forms/FormField';
import { Modal } from '../../../../components/UI/Modal';
import { RightSidebar } from '../../../../components/Navigation/RightSidebar';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { useAdminCity } from '../../../../app/providers/AdminCityProvider';
import { ModalActions } from '../../../shared/components/ModalActions';
import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { PhotoGallery } from '../../../shared/media/components/PhotoGallery';
import { ActivityCalendar, type ActivityCalendarEvent } from '../../../shared/components/ActivityCalendar';
import { CalendarRailPopover } from '../../../shared/components/CalendarRailPopover';
import { canDo } from '../../../shared/permissions';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import { fetchListingCandidates } from '../services/listingWorkflowService';
import { useListingWorkflow } from '../hooks/useListingWorkflow';
import type { ListingCandidate, ListingDraftPayload, ListingStatus, ListingWorkflowItem } from '../types/listingWorkflow.types';
import { fetchListing } from '../services/listingWorkflowService';
import '../../../../../styles/pages/admin_immobilier/vitrine/_vitrine.scss';

const STATUS_LABEL: Record<ListingStatus, string> = {
    draft: 'Brouillon', pending_review: 'À vérifier', corrections_requested: 'Corrections demandées',
    pending_publication: 'Prête à publier', published: 'Publiée', rejected: 'Rejetée', withdrawn: 'Retirée',
};
const STATUS_VARIANT: Record<ListingStatus, 'secondary' | 'warning' | 'success' | 'error'> = {
    draft: 'secondary', pending_review: 'warning', corrections_requested: 'warning',
    pending_publication: 'warning', published: 'success', rejected: 'error', withdrawn: 'secondary',
};
const blankDraft = (): ListingDraftPayload => ({ title: '', description: '', offerType: 'rent', price: '', currency: 'USD' });
const UNIT_TYPE_LABEL: Record<string, string> = {
    apartment: 'Appartement', house: 'Maison', shop: 'Commerce', office: 'Bureau', restaurant: 'Restaurant',
    room: 'Chambre', warehouse: 'Entrepôt', workshop: 'Atelier', storage: 'Dépôt', parking: 'Parking', other: 'Autre',
};
const defaultTitle = (unit: ListingCandidate, offerType: ListingDraftPayload['offerType'] = 'rent'): string => `${UNIT_TYPE_LABEL[unit.type] ?? unit.type} à ${offerType === 'rent' ? 'louer' : 'vendre'} · ${unit.city}`;

interface Props { role: OrganizationRole | null; readOnly?: boolean; }

/** Présentation commune du workflow ; seules les actions autorisées par le rôle actif sont proposées. */
export function ListingWorkflowPanel({ role, readOnly = false }: Props) {
    const { currentOrganization } = useOrganization();
    const { activeCity, activeCityUuid, isLoading: cityLoading } = useAdminCity();
    const selectedCityUuid = role === 'admin_ville' ? activeCityUuid || null : null;
    const hasCityScope = role !== 'admin_ville' || Boolean(selectedCityUuid);
    const workflow = useListingWorkflow(role, selectedCityUuid);
    const [candidates, setCandidates] = useState<ListingCandidate[]>([]);
    const [candidateError, setCandidateError] = useState<string | null>(null);
    const [candidatePage, setCandidatePage] = useState(1);
    const [candidatePages, setCandidatePages] = useState(1);
    const [candidateTotal, setCandidateTotal] = useState(0);
    const [candidateLoading, setCandidateLoading] = useState(false);
    const [unitUuid, setUnitUuid] = useState('');
    const [target, setTarget] = useState<ListingWorkflowItem | null>(null);
    const [draft, setDraft] = useState<ListingDraftPayload>(blankDraft());
    const [modalOpen, setModalOpen] = useState(false);
    const [historyItem, setHistoryItem] = useState<ListingWorkflowItem | null>(null);
    const [historyLoading, setHistoryLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [reasonTarget, setReasonTarget] = useState<{ item: ListingWorkflowItem; action: 'request-corrections' | 'reject' | 'withdraw' } | null>(null);
    const [reason, setReason] = useState('');
    const [reasonSubmitting, setReasonSubmitting] = useState(false);
    const [activityDate, setActivityDate] = useState<string | null>(null);
    const candidateRequestId = useRef(0);

    const activityEvents = useMemo<ActivityCalendarEvent[]>(() => workflow.items.map((item) => ({
        date: item.updatedAt,
        title: `${STATUS_LABEL[item.status]} · ${item.title}`,
        detail: `${item.city} · dernière mise à jour ${new Date(item.updatedAt).toLocaleString('fr-FR')}`,
        tone: item.status === 'published' ? 'success' : item.status === 'rejected' || item.status === 'withdrawn' ? 'error' : item.status === 'pending_review' || item.status === 'pending_publication' ? 'warning' : 'info',
    })), [workflow.items]);
    const activityCalendar = <ActivityCalendar
        events={activityEvents}
        label="Calendrier du suivi des annonces"
        selectedDate={activityDate}
        onSelectDate={setActivityDate}
        onClearDate={() => setActivityDate(null)}
        description={role === 'admin_ville'
            ? `Les marqueurs montrent la dernière mise à jour des annonces de ${activeCity?.name ?? 'la ville active'}.`
            : 'Les marqueurs montrent la dernière mise à jour des annonces de cette organisation.'}
        emptyMessage="Aucune annonce n’a été mise à jour ce jour-là."
    />;

    const reloadCandidates = useCallback(async (page = 1, append = false) => {
        const currentRequestId = ++candidateRequestId.current;
        if (!canDo(role, 'create_listing') || !currentOrganization?.uuid) return;
        if (role === 'admin_ville' && !selectedCityUuid) {
            setCandidates([]); setCandidateTotal(0); setCandidatePage(1); setCandidatePages(1); setCandidateError(null); setCandidateLoading(false);
            return;
        }
        setCandidateError(null);
        setCandidateLoading(true);
        try {
            const result = await fetchListingCandidates(currentOrganization.uuid, page, selectedCityUuid ?? undefined);
            if (currentRequestId !== candidateRequestId.current) return;
            setCandidates((current) => append ? [...current, ...result.items] : result.items);
            setCandidatePage(result.page);
            setCandidatePages(result.pages);
            setCandidateTotal(result.total);
        }
        catch (cause) { if (currentRequestId === candidateRequestId.current) setCandidateError(cause instanceof Error ? cause.message : 'Impossible de charger les biens disponibles.'); }
        finally { if (currentRequestId === candidateRequestId.current) setCandidateLoading(false); }
    }, [currentOrganization?.uuid, role, selectedCityUuid]);
    useEffect(() => {
        setCandidates([]); setCandidateTotal(0); setCandidatePage(1); setCandidatePages(1);
        setUnitUuid(''); setTarget(null); setDraft(blankDraft()); setModalOpen(false); setHistoryItem(null); setReasonTarget(null); setReason('');
        void reloadCandidates();
    }, [reloadCandidates]);

    const availableUnits = useMemo(() => candidates.filter((unit) => !workflow.items.some((item) => item.unitUuid === unit.uuid && !['rejected', 'withdrawn'].includes(item.status))), [candidates, workflow.items]);
    const selectedUnit = candidates.find((unit) => unit.uuid === unitUuid) ?? null;
    const galleryUnitUuid = target?.unitUuid ?? selectedUnit?.uuid ?? '';
    const chooseUnit = (uuid: string) => {
        const unit = candidates.find((candidate) => candidate.uuid === uuid);
        setUnitUuid(uuid);
        if (unit) setDraft((value) => ({ ...value, title: !value.title || value.title === (selectedUnit ? defaultTitle(selectedUnit, value.offerType) : '') ? defaultTitle(unit, value.offerType) : value.title, price: value.offerType === 'rent' ? unit.monthlyRent : '', currency: unit.currency }));
    };
    const startCreate = () => {
        const first = availableUnits[0];
        setTarget(null);
        setDraft({ ...blankDraft(), title: first ? defaultTitle(first) : '', price: first?.monthlyRent ?? '', currency: first?.currency ?? 'USD' });
        setUnitUuid(first?.uuid ?? '');
        setModalOpen(true);
    };
    const startEdit = (item: ListingWorkflowItem) => {
        setTarget(item);
        setDraft({ title: item.title, description: item.description, offerType: item.offerType, price: item.price, currency: item.currency });
        setModalOpen(true);
    };
    const showHistory = async (item: ListingWorkflowItem) => {
        setHistoryItem(item);
        setHistoryLoading(true);
        try { setHistoryItem(await fetchListing(item.uuid)); }
        catch { /* L’erreur de lecture est présentée dans la fenêtre. */ }
        finally { setHistoryLoading(false); }
    };
    const saveDraft = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (saving) return;
        setSaving(true);
        try {
            if (target) await workflow.update(target, draft);
            else if (unitUuid) await workflow.create(unitUuid, draft);
            setModalOpen(false);
        } catch { /* Le hook affiche le retour API ; le brouillon reste ouvert pour correction. */ }
        finally { setSaving(false); }
    };
    const runWithReason = (item: ListingWorkflowItem, action: 'request-corrections' | 'reject' | 'withdraw') => {
        setReason('');
        setReasonTarget({ item, action });
    };
    const submitReason = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!reasonTarget || !reason.trim() || reasonSubmitting) return;
        const { item, action } = reasonTarget;
        setReasonSubmitting(true);
        try {
            await workflow.act(item, action,
                action === 'request-corrections' ? 'La demande de correction a été envoyée.' : action === 'reject' ? 'L’annonce a été rejetée.' : 'L’annonce a été retirée.',
                reason.trim());
            setReasonTarget(null);
            setReason('');
        } catch { /* Le hook affiche l’erreur et laisse le motif disponible. */ }
        finally { setReasonSubmitting(false); }
    };
    const actionButton = (item: ListingWorkflowItem, action: 'submit' | 'queue-publication' | 'publish', label: string, success: string) => (
        <Button size="small" key={action} disabled={workflow.pendingUuid === item.uuid} onClick={() => void workflow.act(item, action, success).catch(() => {})}>{label}</Button>
    );

    let content;
    if (workflow.isLoading) content = <EmptyState icon={<Spinner size="large" />} title="Chargement des annonces…" description="Récupération des annonces et de leur statut." />;
    else if (workflow.error) content = <EmptyState title="Annonces indisponibles" description={workflow.error} action={<Button onClick={() => void workflow.reload()}>Réessayer</Button>} />;
    else if (workflow.items.length === 0) content = <EmptyState title="Aucune annonce dans le circuit" description={canDo(role, 'create_listing') ? `Choisissez une unité${role === 'admin_ville' && activeCity ? ` à ${activeCity.name}` : ''} pour créer un brouillon.` : 'Les annonces apparaîtront ici dès qu’un brouillon sera soumis.'} />;
    else content = <div className="listing-workflow__list">{workflow.items.map((item) => <article className="listing-workflow__item" key={item.uuid}>
        <div className="listing-workflow__heading"><div><h3>{item.title}</h3><p>{item.city} · {item.buildingName ?? 'Bâtiment'} · {UNIT_TYPE_LABEL[item.unitType ?? ''] ?? item.unitReference} · {item.surface ?? '—'} m²</p><p>{item.offerType === 'rent' ? 'Location' : 'Vente'} · {item.price} {item.currency} · {item.photoCount ?? 0} photo(s)</p></div><Badge variant={STATUS_VARIANT[item.status]}>{STATUS_LABEL[item.status]}</Badge></div>
        {item.coverPhotoUrl && <img className="listing-workflow__cover" src={item.coverPhotoUrl} alt={`Photo de couverture : ${item.title}`} />}
        <p className="listing-workflow__description">{item.description}</p>
        <p className="listing-workflow__feedback">Dernière mise à jour : {new Date(item.updatedAt).toLocaleDateString('fr-FR')}</p>
        <p className="listing-workflow__next-step">{item.status === 'draft' || item.status === 'corrections_requested' ? 'Prochaine étape : compléter le bien et soumettre l’annonce au contrôle.' : item.status === 'pending_review' ? 'Prochaine étape : contrôle par l’administrateur immobilier.' : item.status === 'pending_publication' ? 'Prochaine étape : publication par l’administrateur immobilier.' : item.status === 'published' ? 'L’annonce est visible sur la vitrine publique si celle-ci est activée pour l’organisation.' : item.status === 'rejected' ? 'Consultez le motif dans l’historique avant de préparer une nouvelle annonce.' : 'Cette annonce a été retirée de la vitrine.'}</p>
        <div className="listing-workflow__actions"><Button size="small" variant="outline" onClick={() => void showHistory(item)}>Voir l’historique et les motifs</Button></div>
        {!readOnly && <div className="listing-workflow__actions">
            {canDo(role, 'update_listing') && ['draft', 'corrections_requested'].includes(item.status) && <Button size="small" variant="outline" onClick={() => startEdit(item)}>Modifier le brouillon</Button>}
            {canDo(role, 'submit_listing') && ['draft', 'corrections_requested'].includes(item.status) && actionButton(item, 'submit', 'Soumettre au contrôle', 'L’annonce a été soumise au contrôle immobilier.')}
            {canDo(role, 'review_listing') && item.status === 'pending_review' && <>
                <Button size="small" variant="outline" onClick={() => runWithReason(item, 'request-corrections')}>Demander des corrections</Button>
                {actionButton(item, 'queue-publication', 'Valider pour publication', 'L’annonce est prête à être publiée.')}
                <Button size="small" variant="danger" onClick={() => runWithReason(item, 'reject')}>Rejeter</Button>
            </>}
            {canDo(role, 'publish_listing') && item.status === 'pending_publication' && <>{actionButton(item, 'publish', 'Publier', 'L’annonce est publiée sur la vitrine.')}
                <Button size="small" variant="danger" onClick={() => runWithReason(item, 'reject')}>Rejeter</Button></>}
            {canDo(role, 'review_listing') && item.status === 'published' && <Button size="small" variant="outline" onClick={() => runWithReason(item, 'withdraw')}>Retirer de la vitrine</Button>}
        </div>}
    </article>)}</div>;

    return <>
        <OrganizationSummary items={[{ label: 'Annonces', value: <>{workflow.items.length}</> }, { label: 'À vérifier', value: <>{workflow.items.filter((item) => item.status === 'pending_review').length}</> }, { label: 'Publiées', value: <>{workflow.items.filter((item) => item.status === 'published').length}</> }]} />
        <Card className="organization-table-card listing-workflow" padding="medium">
        <div className="organization-table-toolbar"><div><h2>Circuit des annonces{role === 'admin_ville' && activeCity ? ` · ${activeCity.name}` : ''}</h2><p>{role === 'admin_ville' ? `Les annonces et les biens affichés sont limités à la ville active${activeCity ? ` ${activeCity.name}` : ''}.` : role === 'admin_immobilier' ? 'Vérifiez les soumissions, demandez des corrections et publiez les annonces validées.' : 'Consultez l’avancement des annonces de votre organisation.'}</p></div>
            {canDo(role, 'create_listing') && <Button onClick={startCreate} disabled={availableUnits.length === 0}>Créer une annonce</Button>}
        </div>
        {candidateError && canDo(role, 'create_listing') && <p className="listing-workflow__candidate-error" role="alert">{candidateError} <Button size="small" variant="outline" onClick={() => void reloadCandidates()}>Réessayer</Button></p>}
        {role === 'admin_ville' && !hasCityScope && <p className="listing-workflow__candidate-empty">{cityLoading ? 'Chargement de vos villes…' : 'Aucune ville active. Choisissez une ville attribuée dans le sélecteur d’espace.'}</p>}
        {canDo(role, 'create_listing') && hasCityScope && !candidateLoading && !candidateError && availableUnits.length === 0 && <p className="listing-workflow__candidate-empty">Aucun bien disponible{role === 'admin_ville' && activeCity ? ` à ${activeCity.name}` : ''}. Vérifiez le statut du bien, les baux actifs et les annonces déjà en cours.</p>}
        {hasCityScope && content}
        <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={target ? 'Modifier le brouillon' : 'Créer un brouillon'} size="medium" footer={<><Button variant="outline" onClick={() => setModalOpen(false)}>Annuler</Button><Button type="submit" form="listing-draft-form" disabled={saving || (!target && !unitUuid)}>{saving ? 'Enregistrement…' : 'Enregistrer le brouillon'}</Button></>}>
            <form id="listing-draft-form" className="organization-management-form" onSubmit={(event) => void saveDraft(event)}>
                {!target && <><p className="listing-workflow__candidate-note">Chaque annonce décrit une unité existante du Patrimoine : maison, chambre, appartement ou local. Le bâtiment et la parcelle indiquent son emplacement.</p><FormField label="Bien du Patrimoine" htmlFor="listing-unit"><Select id="listing-unit" value={unitUuid} onChange={(event) => chooseUnit(event.target.value)} options={availableUnits.map((unit) => ({ value: unit.uuid, label: `${UNIT_TYPE_LABEL[unit.type] ?? unit.type} · ${unit.reference} · ${unit.building} · ${unit.city}` }))} /></FormField><p className="listing-workflow__candidate-count">{role === 'admin_ville' ? `Ville active · ${activeCity?.name ?? '—'}` : 'Toutes les villes de l’organisation'} · {candidateTotal} bien(s) éligible(s).</p>{candidatePage < candidatePages && <Button type="button" variant="outline" size="small" disabled={candidateLoading} onClick={() => void reloadCandidates(candidatePage + 1, true)}>{candidateLoading ? 'Chargement…' : 'Charger plus de biens'}</Button>}</>}
                {selectedUnit && <section className="listing-workflow__property-summary" aria-label="Caractéristiques du bien sélectionné">
                    <h3>{UNIT_TYPE_LABEL[selectedUnit.type] ?? selectedUnit.type} · {selectedUnit.reference}</h3>
                    <p>{selectedUnit.building} · {selectedUnit.parcel} · {selectedUnit.city}</p>
                    <dl><div><dt>Surface</dt><dd>{selectedUnit.surface} m²</dd></div>{selectedUnit.floorLabel && <div><dt>Niveau</dt><dd>{selectedUnit.floorLabel}</dd></div>}{selectedUnit.bedrooms !== null && <div><dt>Chambres</dt><dd>{selectedUnit.bedrooms}</dd></div>}{selectedUnit.rooms !== null && <div><dt>Pièces</dt><dd>{selectedUnit.rooms}</dd></div>}{selectedUnit.bathrooms !== null && <div><dt>Salles de bain</dt><dd>{selectedUnit.bathrooms}</dd></div>}</dl>
                    <p className="listing-workflow__photo-note">Ajoutez au moins une photo à la galerie du Patrimoine avant de soumettre cette annonce. La première photo est utilisée comme couverture.</p>
                </section>}
                {target && <section className="listing-workflow__property-summary" aria-label="Bien associé à l’annonce">
                    <h3>{UNIT_TYPE_LABEL[target.unitType ?? ''] ?? 'Bien'} · {target.unitReference}</h3>
                    <p>{target.buildingName ?? 'Bâtiment'} · {target.parcelName ?? 'Parcelle'} · {target.city}{target.surface ? ` · ${target.surface} m²` : ''}</p>
                    <p>Caractéristiques du bien issues du Patrimoine. Modifiez-les dans le menu Patrimoine si elles sont incorrectes.</p>
                </section>}
                {galleryUnitUuid && <section className="listing-workflow__gallery" aria-label="Photos du bien"><h3>Photos du bien</h3><p>La première photo de la galerie est la couverture de l’annonce. Au moins une photo est exigée avant la soumission.</p><PhotoGallery entityUuid={galleryUnitUuid} entityType="unit" mode="multiple" onChange={() => void reloadCandidates()} /></section>}
                <FormField label="Titre de l’annonce" htmlFor="listing-title"><Input id="listing-title" value={draft.title} onChange={(event) => setDraft((value) => ({ ...value, title: event.target.value }))} required maxLength={160} fullWidth /></FormField>
                <FormField label="Description" htmlFor="listing-description"><Textarea id="listing-description" value={draft.description} onChange={(event) => setDraft((value) => ({ ...value, description: event.target.value }))} required rows={4} maxLength={4000} fullWidth /></FormField>
                <FormField label="Type d’offre" htmlFor="listing-offer"><Select id="listing-offer" value={draft.offerType} onChange={(event) => setDraft((value) => { const offerType = event.target.value as ListingDraftPayload['offerType']; return { ...value, offerType, title: selectedUnit && value.title === defaultTitle(selectedUnit, value.offerType) ? defaultTitle(selectedUnit, offerType) : value.title, price: offerType === 'rent' ? (value.price || selectedUnit?.monthlyRent || '') : '' }; })} options={[{ value: 'rent', label: 'Location' }, { value: 'sale', label: 'Vente' }]} /></FormField>
                <FormField label="Prix" htmlFor="listing-price"><Input id="listing-price" type="number" min="0.01" step="0.01" value={draft.price} onChange={(event) => setDraft((value) => ({ ...value, price: event.target.value }))} required fullWidth /></FormField>
                <FormField label="Devise" htmlFor="listing-currency"><Select id="listing-currency" value={draft.currency} onChange={(event) => setDraft((value) => ({ ...value, currency: event.target.value as ListingDraftPayload['currency'] }))} options={[{ value: 'USD', label: 'USD' }, { value: 'CDF', label: 'CDF' }]} /></FormField>
            </form>
        </Modal>
        <Modal isOpen={historyItem !== null} onClose={() => setHistoryItem(null)} title={historyItem ? `Historique · ${historyItem.title}` : 'Historique de l’annonce'} size="medium" footer={<Button variant="outline" onClick={() => setHistoryItem(null)}>Fermer</Button>}>
            {historyLoading ? <EmptyState icon={<Spinner size="large" />} title="Chargement de l’historique…" description="Récupération des décisions et commentaires." /> : historyItem?.history?.length ? <ol className="listing-workflow__history">{historyItem.history.map((entry) => <li key={entry.uuid}><strong>{STATUS_LABEL[entry.newStatus as ListingStatus] ?? entry.newStatus}</strong><span>{entry.actor} · {new Date(entry.createdAt).toLocaleString('fr-FR')}</span>{entry.reason && <p>{entry.reason}</p>}</li>)}</ol> : <EmptyState title="Historique indisponible" description="Aucune décision ou aucun commentaire n’est disponible pour cette annonce." />}
        </Modal>
        <Modal
            isOpen={reasonTarget !== null}
            onClose={() => { if (!reasonSubmitting) setReasonTarget(null); }}
            title={reasonTarget?.action === 'request-corrections' ? 'Demander des corrections' : reasonTarget?.action === 'reject' ? 'Rejeter l’annonce' : 'Retirer l’annonce'}
            size="medium"
            footer={<ModalActions formId="listing-reason-form" onCancel={() => setReasonTarget(null)} submitLabel={reasonTarget?.action === 'request-corrections' ? 'Envoyer les corrections' : 'Confirmer'} loadingLabel="Envoi…" isLoading={reasonSubmitting} disabled={!reason.trim()} />}
        >
            <form id="listing-reason-form" className="organization-management-form" onSubmit={(event) => void submitReason(event)}>
                <FormField
                    label={reasonTarget?.action === 'request-corrections' ? 'Corrections à effectuer' : 'Motif'}
                    htmlFor="listing-reason"
                    required
                    helpText={reasonTarget?.action === 'request-corrections' ? 'Ce message sera transmis à l’administrateur de la ville.' : undefined}
                >
                    <Textarea id="listing-reason" value={reason} onChange={(event) => setReason(event.target.value)} placeholder={reasonTarget?.action === 'request-corrections' ? 'Précisez les informations ou les photos à corriger…' : 'Expliquez le motif…'} required minLength={1} maxLength={1000} rows={4} autoFocus fullWidth />
                </FormField>
            </form>
        </Modal>
        </Card>
        <RightSidebar
            title="Calendrier des annonces"
            size="medium"
            variant="dark"
            collapsible
            collapsedWidth={48}
            collapsedContent={<div className="financial-calendar-rail"><CalendarRailPopover title="Activité des annonces" label="Ouvrir le calendrier des annonces">{activityCalendar}</CalendarRailPopover></div>}
        >
            {activityCalendar}
        </RightSidebar>
    </>;
}
