import { useEffect, useRef, useState, type FormEvent } from 'react';

import { Button } from '../../../../components/UI/Button';
import { FormField } from '../../../../components/Forms/FormField';
import { Input } from '../../../../components/Forms/Input';
import { Select } from '../../../../components/Forms/Select';
import { Textarea } from '../../../../components/Forms/Textarea';
import { Modal } from '../../../../components/UI/Modal';
import { ConfirmDialog } from '../../../../components/UI/ConfirmDialog';
import { ModalActions } from '../../../shared/components/ModalActions';
import { fieldErrorMap } from '../../../shared/utils/actionErrors';
import type { LeaseItem } from '../../shared/types/reference.types';
import { addLeaseOccupant, correctResidentialMovement, fetchResidentialMovements, recordLeaseOccupantDeparture, recordResidentialMovement, type MovementKind, type MovementState, type ResidentialMovementItem } from '../services/locatairesService';
import './ResidentialHistoryModal.scss';

interface Props {
    open: boolean;
    tenant: { id: string; name: string } | null;
    units: Array<{ id: string; reference: string }>;
    leases: LeaseItem[];
    canRecord: boolean;
    onReload: () => Promise<unknown>;
    tenants: Array<{ id: string; name: string }>;
    onClose: () => void;
}

const TYPE_OPTIONS = [
    { value: 'move_in', label: 'Nouvelle entrée' },
    { value: 'transfer', label: 'Changement de logement' },
    { value: 'departure_announced', label: 'Départ annoncé' },
    { value: 'departure_completed', label: 'Départ effectif' },
    { value: 'temporary_leave', label: 'Départ temporaire' },
    { value: 'return', label: 'Retour / réintégration' },
    { value: 'occupants_changed', label: 'Modification des occupants' },
    { value: 'correction', label: 'Correction' },
];
const STATUS_OPTIONS = [
    { value: 'recorded', label: 'Enregistré' },
    { value: 'announced', label: 'Annoncé' },
    { value: 'planned', label: 'Planifié' },
    { value: 'postponed', label: 'Reporté' },
    { value: 'cancelled', label: 'Annulé' },
    { value: 'completed', label: 'Réalisé' },
];

const DATE_LABEL = new Intl.DateTimeFormat('fr-FR');
function displayDate(value: string | null): string {
    if (!value) return 'Non renseignée';
    const date = new Date(`${value}T00:00:00`);
    return Number.isNaN(date.getTime()) ? value : DATE_LABEL.format(date);
}

export function ResidentialHistoryModal({ open, tenant, units, leases, canRecord, onReload, tenants, onClose }: Props) {
    const [items, setItems] = useState<ResidentialMovementItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [occupantSubmitting, setOccupantSubmitting] = useState(false);
    const [returnSubmitting, setReturnSubmitting] = useState(false);
    const [movementConfirmationOpen, setMovementConfirmationOpen] = useState(false);
    const [editingMovement, setEditingMovement] = useState<ResidentialMovementItem | null>(null);
    const [correctionReason, setCorrectionReason] = useState('');
    const movementConfirmed = useRef(false);
    const movementFormRef = useRef<HTMLFormElement>(null);
    const [pendingOccupantDeparture, setPendingOccupantDeparture] = useState<{ leaseUuid: string; occupantUuid: string; name: string; date: string; reason: string } | null>(null);
    const [occupantForm, setOccupantForm] = useState({ occupantUuid: '', effectiveDate: '', reason: '' });
    const [returnForm, setReturnForm] = useState({ presenceKey: '', startDate: '' });
    const [error, setError] = useState('');
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [form, setForm] = useState({
        type: 'move_in' as MovementKind,
        status: 'recorded' as MovementState,
        leaseUuid: '',
        nextLeaseUuid: '',
        previousUnitUuid: '',
        nextUnitUuid: '',
        plannedDate: '',
        effectiveDate: '',
        effectiveDateEstimated: false,
        reason: '',
        details: '',
    });

    const load = async () => {
        if (!tenant) return;
        setLoading(true);
        setError('');
        try {
            setItems(await fetchResidentialMovements(tenant.id));
        } catch (cause) {
            setError(cause instanceof Error ? cause.message : 'Impossible de charger l’historique.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (open) void load();
    }, [open, tenant?.id]);

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!tenant || submitting) return;
        const isContractTransition = form.status === 'completed'
            && (form.type === 'transfer' || form.type === 'departure_completed');
        if (isContractTransition && !movementConfirmed.current) {
            setMovementConfirmationOpen(true);
            return;
        }
        movementConfirmed.current = false;
        setSubmitting(true);
        setError('');
        setFieldErrors({});
        try {
            const payload = {
                type: form.type,
                status: form.status,
                ...(form.leaseUuid ? { leaseUuid: form.leaseUuid } : {}),
                ...(form.nextLeaseUuid ? { nextLeaseUuid: form.nextLeaseUuid } : {}),
                ...(form.previousUnitUuid ? { previousUnitUuid: form.previousUnitUuid } : {}),
                ...(form.nextUnitUuid ? { nextUnitUuid: form.nextUnitUuid } : {}),
                ...(form.plannedDate ? { plannedDate: form.plannedDate } : {}),
                ...(form.effectiveDate ? { effectiveDate: form.effectiveDate } : {}),
                effectiveDateEstimated: form.effectiveDateEstimated,
                ...(form.reason.trim() ? { reason: form.reason.trim() } : {}),
                ...(form.details.trim() ? { details: form.details.trim() } : {}),
            };
            if (editingMovement) {
                await correctResidentialMovement(tenant.id, editingMovement.uuid, { ...payload, correctionReason: correctionReason.trim() });
            } else {
                await recordResidentialMovement(tenant.id, payload);
            }
            setEditingMovement(null);
            setCorrectionReason('');
            setForm({ type: 'move_in', status: 'recorded', leaseUuid: '', nextLeaseUuid: '', previousUnitUuid: '', nextUnitUuid: '', plannedDate: '', effectiveDate: '', effectiveDateEstimated: false, reason: '', details: '' });
            await Promise.all([load(), onReload()]);
        } catch (cause) {
            setFieldErrors(fieldErrorMap(cause));
            setError(cause instanceof Error ? cause.message : 'Le mouvement n’a pas pu être enregistré.');
        } finally {
            setSubmitting(false);
        }
    };

    const startCorrection = (item: ResidentialMovementItem) => {
        setEditingMovement(item);
        setCorrectionReason('');
        setForm({
            type: item.type,
            status: item.status,
            leaseUuid: item.leaseUuid ?? '',
            nextLeaseUuid: item.nextLeaseUuid ?? '',
            previousUnitUuid: item.previousUnitUuid ?? '',
            nextUnitUuid: item.nextUnitUuid ?? '',
            plannedDate: item.plannedDate ?? '',
            effectiveDate: item.effectiveDate ?? '',
            effectiveDateEstimated: item.effectiveDateEstimated,
            reason: item.reason ?? '',
            details: item.details ?? '',
        });
        setFieldErrors({});
        setError('');
    };

    const sourceLeases = leases.filter((lease) => lease.tenantId === tenant?.id && lease.status === 'active');
    const destinationLeases = leases.filter((lease) => lease.tenantId === tenant?.id && lease.status === 'draft');
    const selectedDestinationLease = destinationLeases.find((lease) => lease.id === form.nextLeaseUuid);
    const isCompletedTransfer = form.type === 'transfer' && form.status === 'completed';
    const showPreviousUnit = ['transfer', 'departure_announced', 'departure_completed', 'temporary_leave', 'return', 'occupants_changed', 'correction'].includes(form.type);
    const showNextUnit = ['transfer', 'move_in', 'return', 'occupants_changed', 'correction'].includes(form.type);
    const requirePreviousUnit = ['transfer', 'departure_announced', 'departure_completed', 'temporary_leave'].includes(form.type);
    const requireNextUnit = ['transfer', 'move_in', 'return'].includes(form.type);
    const showPlannedDate = ['announced', 'planned', 'postponed'].includes(form.status) || Boolean(form.plannedDate);
    const showEffectiveDate = ['recorded', 'completed'].includes(form.status) || Boolean(form.effectiveDate);
    const activeOccupants = leases.flatMap((lease) => lease.status === 'active'
        ? lease.occupants.filter((occupant) => occupant.endDate === null).map((occupant) => ({
            leaseUuid: lease.id,
            occupantUuid: occupant.id,
            tenantUuid: occupant.tenantId,
            leaseReference: lease.reference,
            unitReference: units.find((unit) => unit.id === lease.unitId)?.reference ?? 'logement',
            name: tenants.find((item) => item.id === occupant.tenantId)?.name ?? 'Occupant',
        }))
        : []);
    const formerOccupants = leases.flatMap((lease) => lease.status === 'active'
        ? lease.occupants.filter((occupant) => occupant.endDate !== null).map((occupant) => ({
            leaseUuid: lease.id,
            tenantUuid: occupant.tenantId,
            role: occupant.role,
            presenceKey: `${lease.id}:${occupant.id}`,
            leaseReference: lease.reference,
            unitReference: units.find((unit) => unit.id === lease.unitId)?.reference ?? 'logement',
            name: tenants.find((item) => item.id === occupant.tenantId)?.name ?? 'Occupant',
        }))
        : []);
    const selectedFormerOccupant = formerOccupants.find((occupant) => occupant.presenceKey === returnForm.presenceKey);
    const selectedOccupant = activeOccupants.find((occupant) => occupant.occupantUuid === occupantForm.occupantUuid);

    const confirmOccupantDeparture = async () => {
        if (!pendingOccupantDeparture || occupantSubmitting) return;
        setOccupantSubmitting(true);
        setError('');
        try {
            await recordLeaseOccupantDeparture(pendingOccupantDeparture.leaseUuid, pendingOccupantDeparture.occupantUuid, pendingOccupantDeparture.date, pendingOccupantDeparture.reason);
            setPendingOccupantDeparture(null);
            setOccupantForm({ occupantUuid: '', effectiveDate: '', reason: '' });
            await Promise.all([load(), onReload()]);
        } catch (cause) {
            setError(cause instanceof Error ? cause.message : 'La sortie de l’occupant n’a pas pu être enregistrée.');
        } finally {
            setOccupantSubmitting(false);
        }
    };

    const reinstateOccupant = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!selectedFormerOccupant || !returnForm.startDate || returnSubmitting) return;
        setReturnSubmitting(true);
        setError('');
        try {
            await addLeaseOccupant(selectedFormerOccupant.leaseUuid, selectedFormerOccupant.tenantUuid, selectedFormerOccupant.role, returnForm.startDate);
            setReturnForm({ presenceKey: '', startDate: '' });
            await Promise.all([load(), onReload()]);
        } catch (cause) {
            setError(cause instanceof Error ? cause.message : 'La réintégration n’a pas pu être enregistrée.');
        } finally {
            setReturnSubmitting(false);
        }
    };

    return <Modal isOpen={open} onClose={onClose} title={tenant ? `Historique résidentiel · ${tenant.name}` : 'Historique résidentiel'} size="large" footer={canRecord ? <ModalActions formId="residential-movement-form" onCancel={editingMovement ? () => { setEditingMovement(null); setCorrectionReason(''); setForm({ type: 'move_in', status: 'recorded', leaseUuid: '', nextLeaseUuid: '', previousUnitUuid: '', nextUnitUuid: '', plannedDate: '', effectiveDate: '', effectiveDateEstimated: false, reason: '', details: '' }); } : onClose} submitLabel={editingMovement ? 'Enregistrer la correction' : 'Enregistrer le mouvement'} loadingLabel="Enregistrement…" isLoading={submitting} /> : undefined}>
        <div className="residential-history">
            <p className="residential-history__notice">Un départ effectif clôt le bail actif du logement quitté. Pour un transfert effectif, préparez d’abord un nouveau bail brouillon avec ses dates et conditions financières : la confirmation clôture l’ancien bail et active le nouveau dans la même opération. Les paiements restent dans leurs baux respectifs.</p>
            {error && <p className="residential-history__error" role="alert">{error}</p>}
            {loading ? <p>Chargement de l’historique…</p> : items.length === 0 ? <p className="residential-history__empty">Aucun mouvement enregistré.</p> : <ol className="residential-history__list">
                {items.map((item) => <li key={item.uuid}>
                    <div className="residential-history__event"><strong>{TYPE_OPTIONS.find((option) => option.value === item.type)?.label ?? item.type}</strong><span>{STATUS_OPTIONS.find((option) => option.value === item.status)?.label ?? item.status}</span></div>
                    <p>{item.previousUnitReference && `Depuis ${item.previousUnitReference}`}{item.previousUnitReference && item.nextUnitReference && ' → '}{item.nextUnitReference && `Vers ${item.nextUnitReference}`}</p>
                    {item.nextLeaseUuid && <small>Nouveau bail : {leases.find((lease) => lease.id === item.nextLeaseUuid)?.reference ?? item.nextLeaseUuid}</small>}
                    <small>{item.effectiveDate ? `Date réelle${item.effectiveDateEstimated ? ' estimée' : ''} : ${displayDate(item.effectiveDate)}` : `Date prévue : ${displayDate(item.plannedDate)}`} · Saisi le {item.recordedAt} par {item.recordedBy}</small>
                    {item.reason && <p>Motif : {item.reason}</p>}{item.details && <p>{item.details}</p>}
                    {item.correctionReason && <p className="residential-history__correction">Corrigé : {item.correctionReason}</p>}
                    {canRecord && item.status !== 'completed' && <Button type="button" variant="outline" onClick={() => startCorrection(item)}>Corriger</Button>}
                </li>)}
            </ol>}
            {canRecord && <form ref={movementFormRef} id="residential-movement-form" className="organization-management-form" onSubmit={submit}>
                <h3>{editingMovement ? 'Corriger le mouvement' : 'Enregistrer un mouvement'}</h3>
                {editingMovement && <p className="residential-history__notice">La correction d’un mouvement réalisé est verrouillée, car elle pourrait modifier un bail ou des montants déjà enregistrés. Utilisez le statut « Annulé » pour annuler un mouvement non réalisé.</p>}
                <FormField label="Type de mouvement" htmlFor="movement-type" required error={fieldErrors.type}>
                    <Select id="movement-type" value={form.type} onChange={(event) => setForm((current) => ({ ...current, type: event.target.value as MovementKind }))} options={TYPE_OPTIONS} />
                </FormField>
                <FormField label="État" htmlFor="movement-status" required error={fieldErrors.status}>
                    <Select id="movement-status" value={form.status} onChange={(event) => setForm((current) => ({ ...current, status: event.target.value as MovementState }))} options={editingMovement ? STATUS_OPTIONS.filter((option) => option.value !== 'completed') : STATUS_OPTIONS} />
                </FormField>
                {isCompletedTransfer && <>
                    <FormField label="Bail actuel à clôturer" htmlFor="movement-source-lease" required error={fieldErrors.leaseUuid}>
                        <Select id="movement-source-lease" value={form.leaseUuid} required onChange={(event) => {
                            const lease = sourceLeases.find((item) => item.id === event.target.value);
                            setForm((current) => ({ ...current, leaseUuid: event.target.value, previousUnitUuid: lease?.unitId ?? current.previousUnitUuid }));
                        }} options={sourceLeases.map((lease) => ({ value: lease.id, label: `${lease.reference} · ${units.find((unit) => unit.id === lease.unitId)?.reference ?? 'logement'}` }))} placeholder="Choisir le bail actif" />
                    </FormField>
                    <FormField label="Nouveau bail préparé" htmlFor="movement-destination-lease" required error={fieldErrors.nextLeaseUuid}>
                        <Select id="movement-destination-lease" value={form.nextLeaseUuid} required onChange={(event) => {
                            const lease = destinationLeases.find((item) => item.id === event.target.value);
                            setForm((current) => ({ ...current, nextLeaseUuid: event.target.value, nextUnitUuid: lease?.unitId ?? current.nextUnitUuid }));
                        }} options={destinationLeases.map((lease) => ({ value: lease.id, label: `${lease.reference} · ${units.find((unit) => unit.id === lease.unitId)?.reference ?? 'logement'} · ${lease.monthlyRent} ${lease.currency}` }))} placeholder="Choisir un bail brouillon" />
                    </FormField>
                </>}
                {showPreviousUnit && <FormField label="Logement quitté" htmlFor="movement-previous-unit" required={requirePreviousUnit} error={fieldErrors.previousUnitUuid}>
                    <Select id="movement-previous-unit" value={form.previousUnitUuid} required={requirePreviousUnit} onChange={(event) => setForm((current) => ({ ...current, previousUnitUuid: event.target.value }))} options={units.map((unit) => ({ value: unit.id, label: unit.reference }))} placeholder="Non renseigné" disabled={isCompletedTransfer && Boolean(form.leaseUuid)} />
                </FormField>}
                {showNextUnit && <FormField label="Nouveau logement" htmlFor="movement-next-unit" required={requireNextUnit} error={fieldErrors.nextUnitUuid}>
                    <Select id="movement-next-unit" value={form.nextUnitUuid} required={requireNextUnit} onChange={(event) => setForm((current) => ({ ...current, nextUnitUuid: event.target.value }))} options={(selectedDestinationLease?.unitIds?.length ? units.filter((unit) => selectedDestinationLease.unitIds.includes(unit.id)) : units).map((unit) => ({ value: unit.id, label: unit.reference }))} placeholder="Non renseigné" disabled={isCompletedTransfer && Boolean(form.nextLeaseUuid)} />
                </FormField>}
                {showPlannedDate && <FormField label="Date prévue" htmlFor="movement-planned-date" error={fieldErrors.plannedDate}>
                    <Input id="movement-planned-date" type="date" value={form.plannedDate} onChange={(event) => setForm((current) => ({ ...current, plannedDate: event.target.value }))} fullWidth />
                </FormField>}
                {showEffectiveDate && <FormField label="Date réelle" htmlFor="movement-effective-date" error={fieldErrors.effectiveDate}>
                    <Input id="movement-effective-date" type="date" value={form.effectiveDate} onChange={(event) => setForm((current) => ({ ...current, effectiveDate: event.target.value }))} fullWidth />
                </FormField>}
                {showEffectiveDate && <label className="residential-history__estimated"><input type="checkbox" checked={form.effectiveDateEstimated} onChange={(event) => setForm((current) => ({ ...current, effectiveDateEstimated: event.target.checked }))} /> La date réelle est estimée</label>}
                <FormField label="Motif" htmlFor="movement-reason" error={fieldErrors.reason}>
                    <Input id="movement-reason" value={form.reason} onChange={(event) => setForm((current) => ({ ...current, reason: event.target.value }))} placeholder="Ex. : changement de logement" fullWidth maxLength={100} />
                </FormField>
                <FormField label="Précisions" htmlFor="movement-details" error={fieldErrors.details}>
                    <Textarea id="movement-details" value={form.details} onChange={(event) => setForm((current) => ({ ...current, details: event.target.value }))} placeholder="Ex. : Départ confirmé lors de l’état des lieux." rows={3} fullWidth maxLength={5000} />
                </FormField>
                {editingMovement && <FormField label="Motif obligatoire de correction" htmlFor="movement-correction-reason" required error={fieldErrors.correctionReason}>
                    <Input id="movement-correction-reason" value={correctionReason} onChange={(event) => setCorrectionReason(event.target.value)} placeholder="Ex. : Correction de la date après vérification du dossier." required maxLength={255} fullWidth />
                </FormField>}
                <Button type="submit" disabled={submitting || (Boolean(editingMovement) && !correctionReason.trim())} className="residential-history__mobile-submit">{submitting ? 'Enregistrement…' : editingMovement ? 'Enregistrer la correction' : 'Enregistrer le mouvement'}</Button>
            </form>}
            {canRecord && activeOccupants.length > 0 && <form className="organization-management-form residential-history__occupant-form" onSubmit={(event) => {
                event.preventDefault();
                if (!selectedOccupant || !occupantForm.effectiveDate || !occupantForm.reason.trim()) return;
                setPendingOccupantDeparture({ leaseUuid: selectedOccupant.leaseUuid, occupantUuid: selectedOccupant.occupantUuid, name: selectedOccupant.name, date: occupantForm.effectiveDate, reason: occupantForm.reason.trim() });
            }}>
                <h3>Enregistrer le départ d’un occupant</h3>
                <p className="residential-history__notice">Cela clôt uniquement la présence de la personne choisie. Le bail, les obligations financières et les autres occupants ne changent pas.</p>
                <FormField label="Occupant et bail" htmlFor="occupant-departure-person" required>
                    <Select id="occupant-departure-person" value={occupantForm.occupantUuid} onChange={(event) => setOccupantForm((current) => ({ ...current, occupantUuid: event.target.value }))} options={activeOccupants.map((occupant) => ({ value: occupant.occupantUuid, label: `${occupant.name} · ${occupant.leaseReference} · ${occupant.unitReference}` }))} placeholder="Choisir une présence active" />
                </FormField>
                <FormField label="Date réelle de sortie" htmlFor="occupant-departure-date" required>
                    <Input id="occupant-departure-date" type="date" value={occupantForm.effectiveDate} onChange={(event) => setOccupantForm((current) => ({ ...current, effectiveDate: event.target.value }))} required fullWidth />
                </FormField>
                <FormField label="Motif" htmlFor="occupant-departure-reason" required>
                    <Input id="occupant-departure-reason" value={occupantForm.reason} onChange={(event) => setOccupantForm((current) => ({ ...current, reason: event.target.value }))} placeholder="Ex. : Fin d’occupation à la demande du locataire." required maxLength={255} fullWidth />
                </FormField>
                <Button type="submit" disabled={occupantSubmitting || !selectedOccupant || !occupantForm.effectiveDate || !occupantForm.reason.trim()}>{occupantSubmitting ? 'Enregistrement…' : 'Vérifier la sortie'}</Button>
            </form>}
            {canRecord && formerOccupants.length > 0 && <form className="organization-management-form residential-history__occupant-form" onSubmit={reinstateOccupant}>
                <h3>Réintégrer un ancien occupant</h3>
                <p className="residential-history__notice">La réintégration crée une nouvelle période dans un bail actif. Les dates et l’historique précédents sont conservés.</p>
                <FormField label="Personne et bail actif" htmlFor="occupant-return-person" required>
                    <Select id="occupant-return-person" value={returnForm.presenceKey} onChange={(event) => setReturnForm((current) => ({ ...current, presenceKey: event.target.value }))} options={formerOccupants.map((occupant) => ({ value: occupant.presenceKey, label: `${occupant.name} · ${occupant.leaseReference} · ${occupant.unitReference}` }))} placeholder="Choisir une ancienne présence" />
                </FormField>
                <FormField label="Nouvelle date d’entrée" htmlFor="occupant-return-date" required>
                    <Input id="occupant-return-date" type="date" value={returnForm.startDate} onChange={(event) => setReturnForm((current) => ({ ...current, startDate: event.target.value }))} required fullWidth />
                </FormField>
                <Button type="submit" disabled={returnSubmitting || !selectedFormerOccupant || !returnForm.startDate}>{returnSubmitting ? 'Enregistrement…' : 'Enregistrer la réintégration'}</Button>
            </form>}
        </div>
        <ConfirmDialog
            isOpen={movementConfirmationOpen}
            onClose={() => setMovementConfirmationOpen(false)}
            onCancel={() => setMovementConfirmationOpen(false)}
            onConfirm={() => {
                movementConfirmed.current = true;
                setMovementConfirmationOpen(false);
                movementFormRef.current?.requestSubmit();
            }}
            title={form.type === 'transfer' ? 'Confirmer le transfert' : 'Confirmer le départ définitif'}
            message={form.type === 'transfer'
                ? `Locataire : ${tenant?.name ?? '—'}. Logement quitté : ${units.find((unit) => unit.id === form.previousUnitUuid)?.reference ?? '—'}. Nouveau logement : ${units.find((unit) => unit.id === form.nextUnitUuid)?.reference ?? '—'}. Date réelle : ${displayDate(form.effectiveDate || null)}. L’ancien bail sera clôturé et le nouveau activé. Les loyers et paiements resteront dans leurs baux.`
                : `Locataire : ${tenant?.name ?? '—'}. Logement quitté : ${units.find((unit) => unit.id === form.previousUnitUuid)?.reference ?? '—'}. Date réelle : ${displayDate(form.effectiveDate || null)}. Le bail sera clôturé; les loyers et paiements déjà enregistrés seront conservés.`}
            confirmLabel={form.type === 'transfer' ? 'Confirmer le transfert' : 'Confirmer le départ'}
            cancelLabel="Vérifier les informations"
        />
        <ConfirmDialog isOpen={pendingOccupantDeparture !== null} onClose={() => setPendingOccupantDeparture(null)} onCancel={() => setPendingOccupantDeparture(null)} onConfirm={() => void confirmOccupantDeparture()} title="Confirmer le départ individuel" message={pendingOccupantDeparture ? `${pendingOccupantDeparture.name} quittera le bail à la date du ${displayDate(pendingOccupantDeparture.date)}. Le contrat et les autres occupants resteront actifs.` : ''} confirmLabel="Confirmer la sortie" cancelLabel="Modifier" />
    </Modal>;
}
