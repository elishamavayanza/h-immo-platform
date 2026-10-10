import { useState, type FormEvent } from 'react';

import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { Input } from '../../../../components/Forms/Input';
import { Textarea } from '../../../../components/Forms/Textarea';
import { FormField } from '../../../../components/Forms/FormField';
import { Modal } from '../../../../components/UI/Modal';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { usePersonnel } from '../hooks/usePersonnel';
import { PersonnelTable } from '../components/PersonnelTable';
import { fieldErrorMap } from '../../../shared/actionErrors';
import { ModalActions } from '../../../shared/components/ModalActions';
import { WORKER_ROLE_OPTIONS, CURRENCY_OPTIONS, ASSIGNMENT_ROLE_OPTIONS } from '../services/personnelService';
import type { PersonnelRow } from '../types/personnel.types';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import { canDo } from '../../../shared/permissions';
import '../../../../../styles/pages/admin_immobilier/personnel/_personnel.scss';

export function PersonnelPage() {
    const { organizationRole } = useOrganization();
    const {
        data, rows, availableCities, isLoading, error, reload,
        search, setSearch, city, setCity,
        createWorker, updateWorker, createAssignment,
    } = usePersonnel();

    const [workerModalOpen, setWorkerModalOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<PersonnelRow | null>(null);
    const [form, setForm] = useState({
        fullName: '',
        phone: '',
        email: '',
        nationalId: '',
        address: '',
        notes: '',
    });
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const [assignmentModalOpen, setAssignmentModalOpen] = useState(false);
    const [assignTarget, setAssignTarget] = useState<PersonnelRow | null>(null);
    const [assignmentForm, setAssignmentForm] = useState({
        cityUuid: '',
        role: 'gardien',
        monthlySalary: '',
        currency: 'USD' as 'USD' | 'CDF',
        startDate: new Date().toISOString().split('T')[0],
        endDate: '',
        parcelUuid: '',
        buildingUuid: '',
        unitUuid: '',
        notes: '',
    });
    const [assignmentFormErrors, setAssignmentFormErrors] = useState<Record<string, string>>({});
    const [submittingAssignment, setSubmittingAssignment] = useState(false);

    const openCreateWorker = () => {
        setForm({ fullName: '', phone: '', email: '', nationalId: '', address: '', notes: '' });
        setFormErrors({});
        setEditTarget(null);
        setWorkerModalOpen(true);
    };

    const openEditWorker = (target: PersonnelRow) => {
        setForm({ fullName: target.name, phone: target.phone ?? '', email: '', nationalId: '', address: '', notes: '' });
        setFormErrors({});
        setEditTarget(target);
        setWorkerModalOpen(true);
    };

    const closeWorkerModal = () => {
        setWorkerModalOpen(false);
        setEditTarget(null);
    };

    const submitWorker = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        setFormErrors({});

        const payload = {
            fullName: form.fullName.trim(),
            phone: form.phone.trim(),
            email: form.email.trim() || undefined,
            nationalId: form.nationalId.trim() || undefined,
            address: form.address.trim() || undefined,
            notes: form.notes.trim() || undefined,
        };

        try {
            if (editTarget) {
                await updateWorker(editTarget.id, payload);
            } else {
                await createWorker(payload);
            }
            closeWorkerModal();
        } catch (cause) {
            setFormErrors(fieldErrorMap(cause));
        } finally {
            setSubmitting(false);
        }
    };

    const openAssign = (target: PersonnelRow) => {
        setAssignTarget(target);
        setAssignmentForm({
            cityUuid: '',
            role: 'gardien',
            monthlySalary: '',
            currency: 'USD',
            startDate: new Date().toISOString().split('T')[0],
            endDate: '',
            parcelUuid: '',
            buildingUuid: '',
            unitUuid: '',
            notes: '',
        });
        setAssignmentFormErrors({});
        setAssignmentModalOpen(true);
    };

    const closeAssignmentModal = () => {
        setAssignmentModalOpen(false);
        setAssignTarget(null);
    };

    const submitAssignment = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (submittingAssignment || !assignTarget) return;
        setSubmittingAssignment(true);
        setAssignmentFormErrors({});

        const payload = {
            workerUuid: assignTarget.id,
            cityUuid: assignmentForm.cityUuid,
            role: assignmentForm.role,
            monthlySalary: assignmentForm.monthlySalary.trim(),
            currency: assignmentForm.currency,
            startDate: assignmentForm.startDate,
            endDate: assignmentForm.endDate || undefined,
            parcelUuid: assignmentForm.parcelUuid || undefined,
            buildingUuid: assignmentForm.buildingUuid || undefined,
            unitUuid: assignmentForm.unitUuid || undefined,
            notes: assignmentForm.notes.trim() || undefined,
        };

        try {
            await createAssignment(payload);
            closeAssignmentModal();
        } catch (cause) {
            setAssignmentFormErrors(fieldErrorMap(cause));
        } finally {
            setSubmittingAssignment(false);
        }
    };

    let body = <PersonnelTable rows={rows} role={organizationRole} onEdit={openEditWorker} onAssign={openAssign} />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement du personnel…" description="Récupération des travailleurs de l’organisation." />;
    } else if (error) {
        body = <EmptyState title="Personnel indisponible" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />;
    } else if (rows.length === 0) {
        body = <EmptyState
            title="Aucun membre à afficher"
            description={data && data.rows.length > 0 ? 'Aucun membre ne correspond aux filtres.' : 'Aucun travailleur enregistré pour cette organisation.'}
        />;
    }

    return <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">ÉQUIPE OPÉRATIONNELLE</span><h1>Personnel</h1><p>Consultez les ouvriers et leurs affectations sur vos biens.</p></div></header>
        <OrganizationSummary items={[{ label: 'Membres affichés', value: <>{rows.length}</> }, { label: 'Affectations visibles', value: <>{rows.reduce((total, row) => total + row.assignments, 0)}</> }, { label: 'Villes couvertes', value: <>{new Set(rows.map((row) => row.city).filter((value) => value !== '—')).size}</> }]} />
        <Card className="organization-table-card" padding="medium"><div className="organization-table-toolbar"><div><h2>Équipe</h2><p>{data?.note ?? 'La fonction et la ville indiquées proviennent de la dernière affectation.'}</p></div><div className="organization-filters organization-filters--tenant"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un membre…" fullWidth /><Select aria-label="Filtrer par ville" value={city} onChange={(event) => setCity(event.target.value)} options={[{ value: 'all', label: 'Toutes les villes' }, ...availableCities.map((value) => ({ value, label: value }))]} />{canDo(organizationRole, 'create_worker') && <Button onClick={openCreateWorker}>＋ Ajouter un ouvrier</Button>}</div></div>{body}</Card>
        <Modal
            isOpen={workerModalOpen}
            onClose={closeWorkerModal}
            title={editTarget ? `Modifier « ${editTarget.name} »` : 'Ajouter un ouvrier'}
            size="medium"
            footer={<ModalActions formId="worker-form" onCancel={closeWorkerModal} submitLabel={editTarget ? 'Enregistrer' : 'Créer l\'ouvrier'} loadingLabel={editTarget ? 'Enregistrement…' : 'Création…'} isLoading={submitting} />}
        >
            <form id="worker-form" className="organization-management-form" onSubmit={submitWorker}>
                <FormField label="Nom complet" htmlFor="worker-fullname" required error={formErrors.fullName}>
                    <Input id="worker-fullname" value={form.fullName} onChange={(event) => setForm((current) => ({ ...current, fullName: event.target.value }))} placeholder="Ex. : Jean Dupont" required fullWidth maxLength={200} />
                </FormField>
                <FormField label="Téléphone" htmlFor="worker-phone" required error={formErrors.phone}>
                    <Input id="worker-phone" value={form.phone} onChange={(event) => setForm((current) => ({ ...current, phone: event.target.value }))} placeholder="+243…" required fullWidth maxLength={30} />
                </FormField>
                <FormField label="E-mail" htmlFor="worker-email" error={formErrors.email}>
                    <Input id="worker-email" type="email" value={form.email} onChange={(event) => setForm((current) => ({ ...current, email: event.target.value }))} placeholder="jean.dupont@example.cd" fullWidth maxLength={180} />
                </FormField>
                <FormField label="N° national" htmlFor="worker-nationalid" error={formErrors.nationalId}>
                    <Input id="worker-nationalid" value={form.nationalId} onChange={(event) => setForm((current) => ({ ...current, nationalId: event.target.value }))} placeholder="Optionnel" fullWidth maxLength={50} />
                </FormField>
                <FormField label="Adresse" htmlFor="worker-address" error={formErrors.address}>
                    <Input id="worker-address" value={form.address} onChange={(event) => setForm((current) => ({ ...current, address: event.target.value }))} placeholder="Optionnel" fullWidth maxLength={255} />
                </FormField>
                <FormField label="Notes" htmlFor="worker-notes" error={formErrors.notes}>
                    <Textarea id="worker-notes" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} rows={3} fullWidth maxLength={1000} />
                </FormField>
            </form>
        </Modal>
        <Modal
            isOpen={assignmentModalOpen}
            onClose={closeAssignmentModal}
            title={assignTarget ? `Nouvelle affectation pour « ${assignTarget.name} »` : 'Nouvelle affectation'}
            size="large"
            footer={<ModalActions formId="assignment-form" onCancel={closeAssignmentModal} submitLabel="Créer l'affectation" loadingLabel="Création…" isLoading={submittingAssignment} />}
        >
            <form id="assignment-form" className="organization-management-form" onSubmit={submitAssignment}>
                <FormField label="Ville" htmlFor="assign-city" required error={assignmentFormErrors.cityUuid}>
                    <Select id="assign-city" value={assignmentForm.cityUuid} onChange={(event) => setAssignmentForm((current) => ({ ...current, cityUuid: event.target.value }))} options={[] as { value: string; label: string }[]} placeholder="Sélectionner une ville" required />
                </FormField>
                <FormField label="Fonction" htmlFor="assign-role" required error={assignmentFormErrors.role}>
                    <Select id="assign-role" value={assignmentForm.role} onChange={(event) => setAssignmentForm((current) => ({ ...current, role: event.target.value }))} options={ASSIGNMENT_ROLE_OPTIONS} />
                </FormField>
                <FormField label="Salaire mensuel" htmlFor="assign-salary" required error={assignmentFormErrors.monthlySalary}>
                    <Input id="assign-salary" type="number" step="0.01" min="0" inputMode="decimal" value={assignmentForm.monthlySalary} onChange={(event) => setAssignmentForm((current) => ({ ...current, monthlySalary: event.target.value }))} placeholder="Ex. : 300.00" required fullWidth />
                </FormField>
                <FormField label="Devise" htmlFor="assign-currency" required error={assignmentFormErrors.currency}>
                    <Select id="assign-currency" value={assignmentForm.currency} onChange={(event) => setAssignmentForm((current) => ({ ...current, currency: event.target.value as 'USD' | 'CDF' }))} options={CURRENCY_OPTIONS} />
                </FormField>
                <FormField label="Date de début" htmlFor="assign-start" required error={assignmentFormErrors.startDate}>
                    <Input id="assign-start" type="date" value={assignmentForm.startDate} onChange={(event) => setAssignmentForm((current) => ({ ...current, startDate: event.target.value }))} required fullWidth />
                </FormField>
                <FormField label="Date de fin (optionnel)" htmlFor="assign-end" error={assignmentFormErrors.endDate}>
                    <Input id="assign-end" type="date" value={assignmentForm.endDate} onChange={(event) => setAssignmentForm((current) => ({ ...current, endDate: event.target.value }))} fullWidth />
                </FormField>
                <FormField label="Parcel (optionnel)" htmlFor="assign-parcel" error={assignmentFormErrors.parcelUuid}>
                    <Select id="assign-parcel" value={assignmentForm.parcelUuid} onChange={(event) => setAssignmentForm((current) => ({ ...current, parcelUuid: event.target.value }))} options={[] as { value: string; label: string }[]} placeholder="Sélectionner une parcelle" />
                </FormField>
                <FormField label="Bâtiment (optionnel)" htmlFor="assign-building" error={assignmentFormErrors.buildingUuid}>
                    <Select id="assign-building" value={assignmentForm.buildingUuid} onChange={(event) => setAssignmentForm((current) => ({ ...current, buildingUuid: event.target.value }))} options={[] as { value: string; label: string }[]} placeholder="Sélectionner un bâtiment" />
                </FormField>
                <FormField label="Unité (optionnel)" htmlFor="assign-unit" error={assignmentFormErrors.unitUuid}>
                    <Select id="assign-unit" value={assignmentForm.unitUuid} onChange={(event) => setAssignmentForm((current) => ({ ...current, unitUuid: event.target.value }))} options={[] as { value: string; label: string }[]} placeholder="Sélectionner une unité" />
                </FormField>
                <FormField label="Notes" htmlFor="assign-notes" error={assignmentFormErrors.notes}>
                    <Textarea id="assign-notes" value={assignmentForm.notes} onChange={(event) => setAssignmentForm((current) => ({ ...current, notes: event.target.value }))} rows={3} fullWidth maxLength={1000} />
                </FormField>
            </form>
        </Modal>
    </main>;
}