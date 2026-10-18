import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { Button } from '../../../../components/UI/Button';
import { Card } from '../../../../components/UI/Card';
import { Modal } from '../../../../components/UI/Modal';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { Select } from '../../../../components/Forms/Select';
import { DatePicker } from '../../../../components/Forms/DatePicker';
import { Spinner } from '../../../../components/UI/Spinner';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { useAdminCity } from '../../../../app/providers/AdminCityProvider';
import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useAssignedCities } from '../../hooks/useAssignedCities';
import { formatCivilDate, localDateInputValue } from '../../../../utils/dateUtils';
import { fetchPropertyCatalog, fetchWorkerAssignments, fetchWorkers } from '../../../admin_immobilier/shared/services/referenceService';
import type { BuildingItem, ParcelItem, PropertyCatalog, UnitItem, WorkerAssignmentItem, WorkerItem } from '../../../admin_immobilier/shared/types/reference.types';
import { createMaintenanceWorkOrder, editMaintenanceWorkOrder, listMaintenanceWorkOrders, updateMaintenanceStatus, type MaintenanceWorkOrder, type MaintenanceWorkOrderInput } from '../services/maintenanceService';
import '../../../../../styles/pages/shared/operations/maintenance.scss';

type Target = { kind: 'parcel' | 'building' | 'unit'; uuid: string; cityUuid: string; label: string };
const STATUS_LABEL: Record<MaintenanceWorkOrder['status'], string> = { open: 'À traiter', in_progress: 'En cours', completed: 'Terminée', cancelled: 'Annulée' };
const PRIORITIES: { value: MaintenanceWorkOrderInput['priority']; label: string }[] = [
    { value: 'low', label: 'Faible' }, { value: 'normal', label: 'Normale' }, { value: 'high', label: 'Élevée' }, { value: 'urgent', label: 'Urgente' },
];

export function MaintenancePage() {
    const { currentOrganization, organizationRole } = useOrganization();
    const { push } = useToast();
    const { cities: assignedCities, isLoading: assignedCitiesLoading, hasAssignedCities: hasAssignedCitiesInHook } = useAssignedCities();
    const { cities: adminCities, activeCityUuid, selectCity, isLoading: adminCitiesLoading } = useAdminCity();
    const isCityAdmin = organizationRole === 'admin_ville';
    const cities = isCityAdmin ? adminCities : assignedCities;
    const citiesLoading = isCityAdmin ? adminCitiesLoading : assignedCitiesLoading;
    const hasAssignedCities = isCityAdmin ? adminCities.length > 0 : hasAssignedCitiesInHook;
    const [catalog, setCatalog] = useState<PropertyCatalog | null>(null);
    const [assignments, setAssignments] = useState<WorkerAssignmentItem[]>([]);
    const [workers, setWorkers] = useState<WorkerItem[]>([]);
    const [selectedCityUuid, setSelectedCityUuid] = useState('');
    const cityUuid = isCityAdmin ? activeCityUuid : selectedCityUuid;
    const [rows, setRows] = useState<MaintenanceWorkOrder[]>([]);
    const [targetUuid, setTargetUuid] = useState('');
    const [targetKind, setTargetKind] = useState<Target['kind']>('unit');
    const [assignmentUuid, setAssignmentUuid] = useState('');
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [priority, setPriority] = useState<MaintenanceWorkOrderInput['priority']>('normal');
    const [dueDate, setDueDate] = useState('');
    const [reportedAt, setReportedAt] = useState(() => localDateInputValue());
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [listError, setListError] = useState('');
    const [editingUuid, setEditingUuid] = useState<string | null>(null);
    const [completionTarget, setCompletionTarget] = useState<MaintenanceWorkOrder | null>(null);
    const [completionDate, setCompletionDate] = useState('');
    const listRequestId = useRef(0);

    useEffect(() => {
        if (!isCityAdmin && !selectedCityUuid && cities.length) setSelectedCityUuid(cities[0].id);
    }, [cities, isCityAdmin, selectedCityUuid]);

    useEffect(() => {
        setTargetUuid('');
        setAssignmentUuid('');
    }, [cityUuid]);

    useEffect(() => {
        const org = currentOrganization?.uuid;
        if (!org) { setCatalog(null); return; }
        let active = true;
        Promise.all([fetchPropertyCatalog(org), fetchWorkerAssignments(), fetchWorkers(org)]).then(([value, assigned, personnel]) => {
            if (active) { setCatalog(value); setAssignments(assigned.items); setWorkers(personnel.items); }
        }).catch(() => { if (active) { setCatalog(null); setAssignments([]); setWorkers([]); } });
        return () => { active = false; };
    }, [currentOrganization?.uuid]);

    const reload = useCallback(async () => {
        const requestId = ++listRequestId.current;
        if (!currentOrganization?.uuid || !cityUuid) { setRows([]); setLoading(false); return; }
        setLoading(true); setListError('');
        try {
            const result = await listMaintenanceWorkOrders(currentOrganization.uuid, cityUuid);
            if (requestId === listRequestId.current) setRows(result);
        } catch (reason) {
            if (requestId === listRequestId.current) setListError(reason instanceof Error ? reason.message : 'Impossible de charger les interventions.');
        } finally {
            if (requestId === listRequestId.current) setLoading(false);
        }
    }, [currentOrganization?.uuid, cityUuid]);
    useEffect(() => { void reload(); }, [reload]);

    const targets = useMemo<Target[]>(() => {
        if (!catalog || !cityUuid) return [];
        const parcels = catalog.parcels.items.filter((item: ParcelItem) => item.cityId === cityUuid);
        const parcelMap = new Map(parcels.map((item) => [item.id, item]));
        const buildings = catalog.buildings.items.filter((item: BuildingItem) => parcelMap.has(item.parcelId));
        const buildingMap = new Map(buildings.map((item) => [item.id, item]));
        const units = catalog.units.items.filter((item: UnitItem) => buildingMap.has(item.buildingId));
        return [
            ...parcels.map((item) => ({ kind: 'parcel' as const, uuid: item.id, cityUuid, label: `Parcelle · ${item.name}` })),
            ...buildings.map((item) => ({ kind: 'building' as const, uuid: item.id, cityUuid, label: `Bâtiment · ${item.name}` })),
            ...units.map((item) => ({ kind: 'unit' as const, uuid: item.id, cityUuid, label: `Unité · ${item.reference}` })),
        ];
    }, [catalog, cityUuid]);
    const selectedTarget = targets.find((item) => item.uuid === targetUuid && item.kind === targetKind);
    const targetOptions = targets.filter((item) => item.kind === targetKind).map((item) => ({ value: item.uuid, label: item.label }));
    const assignmentOptions = assignments.filter((item) => item.cityId === cityUuid).map((item) => ({
        value: item.id,
        label: `${workers.find((worker) => worker.id === item.workerId)?.fullName ?? 'Personnel'} · ${item.role}`,
    }));
    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!currentOrganization?.uuid || !cityUuid || !selectedTarget) { push('warning', 'Choisissez la ville et le bien concernés.'); return; }
        setSaving(true);
        try {
            const input: MaintenanceWorkOrderInput = {
                organizationUuid: currentOrganization.uuid, cityUuid, title: title.trim(), description: description.trim(), priority,
                parcelUuid: targetKind === 'parcel' ? targetUuid : null,
                buildingUuid: targetKind === 'building' ? targetUuid : null,
                unitUuid: targetKind === 'unit' ? targetUuid : null,
                workerAssignmentUuid: assignmentUuid || null, dueDate: dueDate || null, reportedAt,
            };
            if (editingUuid) await editMaintenanceWorkOrder(editingUuid, input);
            else await createMaintenanceWorkOrder(input);
            setEditingUuid(null); setTitle(''); setDescription(''); setDueDate(''); setAssignmentUuid(''); setReportedAt(localDateInputValue());
            push('success', editingUuid
                ? `L’intervention « ${input.title} » a été modifiée.`
                : `L’intervention « ${input.title} » a été ajoutée.`);
            await reload();
        } catch (reason) { push('error', reason instanceof Error ? reason.message : 'Impossible d’enregistrer l’intervention.'); }
        finally { setSaving(false); }
    }

    async function changeStatus(item: MaintenanceWorkOrder, status: MaintenanceWorkOrder['status']) {
        try {
            await updateMaintenanceStatus(item, status);
            const statusLabels: Record<MaintenanceWorkOrder['status'], string> = {
                open: 'à traiter', in_progress: 'en cours', completed: 'terminée', cancelled: 'annulée',
            };
            push('success', `L’intervention « ${item.title} » est maintenant ${statusLabels[status]}.`);
            await reload();
        } catch (reason) { push('error', reason instanceof Error ? `Impossible de modifier l’état de l’intervention « ${item.title} » : ${reason.message}` : `Impossible de modifier l’état de l’intervention « ${item.title} ».`); }
    }

    async function completeMaintenance(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!completionTarget || !completionDate) return;
        try {
            await updateMaintenanceStatus(completionTarget, 'completed', completionDate);
            setCompletionTarget(null);
            push('success', `L’intervention « ${completionTarget.title} » a été terminée le ${completionDate}.`);
            await reload();
        } catch (reason) { push('error', reason instanceof Error ? `Impossible de terminer l’intervention « ${completionTarget.title} » : ${reason.message}` : `Impossible de terminer l’intervention « ${completionTarget.title} ».`); }
    }

    function beginEdit(item: MaintenanceWorkOrder) {
        setEditingUuid(item.uuid); setTitle(item.title); setDescription(item.description); setPriority(item.priority); setDueDate(item.dueDate ?? '');
        setReportedAt(item.reportedAt);
        setAssignmentUuid(item.workerAssignmentUuid ?? '');
        if (item.unitUuid) { setTargetKind('unit'); setTargetUuid(item.unitUuid); }
        else if (item.buildingUuid) { setTargetKind('building'); setTargetUuid(item.buildingUuid); }
        else if (item.parcelUuid) { setTargetKind('parcel'); setTargetUuid(item.parcelUuid); }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    if (citiesLoading || (loading && !cityUuid)) return <div className="main-layout__page-loading" aria-busy="true"><Spinner size="large" className="spinner--page" /><span>Chargement des opérations…</span></div>;
    if (!hasAssignedCities) return <main className="organization-page"><EmptyState title="Aucune ville accessible" description="Aucune ville n’est rattachée à votre périmètre." /></main>;

    const renderActions = (item: MaintenanceWorkOrder) => <div className="maintenance-actions">
        {![ 'completed', 'cancelled' ].includes(item.status) && <Button size="small" variant="outline" onClick={() => beginEdit(item)}>Modifier</Button>}
        {item.status === 'open' && <Button size="small" onClick={() => void changeStatus(item, 'in_progress')}>Démarrer</Button>}
        {item.status === 'in_progress' && <Button size="small" onClick={() => { setCompletionTarget(item); setCompletionDate(localDateInputValue()); }}>Terminer</Button>}
        {['open', 'in_progress'].includes(item.status) && <Button size="small" variant="outline" onClick={() => { if (window.confirm('Annuler cette intervention ?')) void changeStatus(item, 'cancelled'); }}>Annuler</Button>}
    </div>;

    return <main className="organization-page maintenance-page">
        <header className="organization-page__header"><div><span className="organization-page__eyebrow">OPÉRATIONS</span><h1>Maintenance</h1><p>Planifiez et suivez les interventions sur les biens de vos villes autorisées.</p></div></header>
        <div className="maintenance-page__city">
            <label htmlFor="maintenance-city">Ville</label>
            <Select id="maintenance-city" aria-label="Ville" value={cityUuid} onChange={(event) => { if (isCityAdmin) selectCity(event.target.value); else setSelectedCityUuid(event.target.value); setTargetUuid(''); setAssignmentUuid(''); }} options={cities.map((city) => ({ value: city.id, label: city.name }))} fullWidth />
        </div>
        <Card padding="medium" className="organization-table-card maintenance-page__card maintenance-page__form-card">
            <h2>Nouvelle intervention</h2>
            <form onSubmit={(event) => void submit(event)} className="maintenance-form">
                <div className="maintenance-form__field"><label htmlFor="maintenance-level">Niveau du bien</label><Select id="maintenance-level" aria-label="Niveau du bien" value={targetKind} onChange={(event) => { setTargetKind(event.target.value as Target['kind']); setTargetUuid(''); }} options={[{ value: 'unit', label: 'Unité' }, { value: 'building', label: 'Bâtiment' }, { value: 'parcel', label: 'Parcelle' }]} fullWidth /></div>
                <div className="maintenance-form__field"><label htmlFor="maintenance-target">Bien concerné</label><Select id="maintenance-target" aria-label="Bien concerné" value={targetUuid} onChange={(event) => setTargetUuid(event.target.value)} options={targetOptions} placeholder="Sélectionner un bien" required fullWidth /></div>
                <div className="maintenance-form__field"><label htmlFor="maintenance-title">Titre</label><input id="maintenance-title" value={title} onChange={(event) => setTitle(event.target.value)} placeholder="Ex. : Fuite d’eau dans le hall d’entrée" maxLength={180} required /></div>
                <div className="maintenance-form__field maintenance-form__field--wide"><label htmlFor="maintenance-description">Description</label><textarea id="maintenance-description" value={description} onChange={(event) => setDescription(event.target.value)} placeholder="Ex. : Une fuite est visible près de l’entrée. Prévoir le remplacement du joint." maxLength={5000} required rows={4} /></div>
                <div className="maintenance-form__field"><label htmlFor="maintenance-priority">Priorité</label><Select id="maintenance-priority" aria-label="Priorité" value={priority} onChange={(event) => setPriority(event.target.value as MaintenanceWorkOrderInput['priority'])} options={PRIORITIES} fullWidth /></div>
                <div className="maintenance-form__field"><label htmlFor="maintenance-assignee">Responsable</label><Select id="maintenance-assignee" aria-label="Responsable" value={assignmentUuid} onChange={(event) => setAssignmentUuid(event.target.value)} options={assignmentOptions} placeholder="Aucun responsable" fullWidth /></div>
                <div className="maintenance-form__field"><label htmlFor="maintenance-due-date">Date prévue</label><DatePicker id="maintenance-due-date" value={dueDate} min={localDateInputValue()} onChange={(event) => setDueDate(event.target.value)} fullWidth /></div>
                <div className="maintenance-form__field"><label htmlFor="maintenance-reported-date">Date réelle du signalement</label><DatePicker id="maintenance-reported-date" value={reportedAt} max={localDateInputValue()} onChange={(event) => setReportedAt(event.target.value)} required fullWidth /><small>Vous pouvez choisir le jour où le problème a réellement été constaté. L’enregistrement technique reste daté automatiquement.</small></div>
                <div className="maintenance-form__actions"><Button type="submit" disabled={saving || !targetUuid}>{saving ? 'Enregistrement…' : editingUuid ? 'Enregistrer les changements' : 'Ajouter l’intervention'}</Button>
                {editingUuid && <Button type="button" variant="outline" onClick={() => { setEditingUuid(null); setTitle(''); setDescription(''); setDueDate(''); }}>Annuler la modification</Button>}
                </div>
            </form>
        </Card>
        <Card padding="medium" className="organization-table-card maintenance-page__card">
            <div className="organization-table-toolbar"><div><h2>Interventions</h2><p>{rows.length} intervention(s) pour la ville sélectionnée.</p></div><Button variant="outline" onClick={() => void reload()}>Actualiser</Button></div>
            {loading ? <div aria-busy="true"><Spinner size="medium" /> Chargement des interventions…</div> : listError ? <EmptyState title="Liste indisponible" description={listError} action={<Button onClick={() => void reload()}>Réessayer</Button>} /> : rows.length === 0 ? <EmptyState title="Aucune intervention" description="Les interventions créées pour cette ville apparaîtront ici." /> : <div className="maintenance-list">
                <div className="organization-table-scroll"><table className="organization-table maintenance-table"><thead><tr><th>Intervention</th><th>Bien</th><th>Priorité</th><th>Signalée le</th><th>Échéance</th><th>État</th><th>Actions</th></tr></thead><tbody>{rows.map((item) => <tr key={item.uuid}><td><strong>{item.title}</strong><small>{item.description}<br />Enregistrée le {new Date(item.createdAt).toLocaleString('fr-FR')} par {item.createdBy}</small></td><td>{item.targetLabel}</td><td>{PRIORITIES.find((value) => value.value === item.priority)?.label}</td><td>{formatCivilDate(item.reportedAt)}</td><td>{item.dueDate ? formatCivilDate(item.dueDate) : '—'}</td><td>{STATUS_LABEL[item.status]}</td><td>{renderActions(item)}</td></tr>)}</tbody></table></div>
                <div className="maintenance-list__cards">{rows.map((item) => <article className="maintenance-item" key={item.uuid}>
                    <div className="maintenance-item__heading"><div><h3>{item.title}</h3><p>{item.description}</p></div><span className={`maintenance-item__status maintenance-item__status--${item.status}`}>{STATUS_LABEL[item.status]}</span></div>
                    <dl><div><dt>Bien</dt><dd>{item.targetLabel}</dd></div><div><dt>Priorité</dt><dd>{PRIORITIES.find((value) => value.value === item.priority)?.label}</dd></div><div><dt>Signalée le</dt><dd>{formatCivilDate(item.reportedAt)}</dd></div><div><dt>Échéance</dt><dd>{item.dueDate ? formatCivilDate(item.dueDate) : '—'}</dd></div><div><dt>Enregistrée le</dt><dd>{new Date(item.createdAt).toLocaleString('fr-FR')} · {item.createdBy}</dd></div>{item.completedAt && <div><dt>Terminée le</dt><dd>{formatCivilDate(item.completedAt)}</dd></div>}</dl>
                    {renderActions(item)}
                </article>)}</div>
            </div>}
        </Card>
        <Modal isOpen={completionTarget !== null} onClose={() => setCompletionTarget(null)} title="Terminer l’intervention" size="small" footer={<><Button variant="outline" onClick={() => setCompletionTarget(null)}>Annuler</Button><Button type="submit" form="maintenance-completion-form">Confirmer</Button></>}>
            <form id="maintenance-completion-form" className="maintenance-form" onSubmit={(event) => void completeMaintenance(event)}>
                <p>Indiquez la date à laquelle l’intervention a réellement été terminée. L’enregistrement et votre identité restent horodatés automatiquement.</p>
                <div className="maintenance-form__field"><label htmlFor="maintenance-completed-date">Date réelle d’achèvement</label><DatePicker id="maintenance-completed-date" min={completionTarget?.reportedAt} max={localDateInputValue()} value={completionDate} onChange={(event) => setCompletionDate(event.target.value)} required fullWidth /></div>
            </form>
        </Modal>
    </main>;
}
