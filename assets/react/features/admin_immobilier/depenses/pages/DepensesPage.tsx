import { useState, type FormEvent } from 'react';

import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { ConfirmDialog } from '../../../../components/UI/ConfirmDialog';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { Input } from '../../../../components/Forms/Input';
import { Textarea } from '../../../../components/Forms/Textarea';
import { FormField } from '../../../../components/Forms/FormField';
import { Modal } from '../../../../components/UI/Modal';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { useDepenses } from '../hooks/useDepenses';
import { DepensesTable } from '../components/DepensesTable';
import { fieldErrorMap } from '../../../shared/actionErrors';
import { ModalActions } from '../../../shared/components/ModalActions';
import { canDo } from '../../../shared/permissions';
import { CATEGORY_OPTIONS, CURRENCY_OPTIONS, EXPENSE_METHOD_OPTIONS } from '../services/depensesService';
import type { DepenseRow } from '../types/depense.types';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import '../../../../../styles/pages/admin_immobilier/depenses/_depenses.scss';

export function DepensesPage() {
    const { organizationRole } = useOrganization();
    const {
        data, rows, availableCategories, isLoading, error, reload,
        search, setSearch, category, setCategory,
        createExpense, updateExpense, cancelExpense,
    } = useDepenses();

    const [expenseModalOpen, setExpenseModalOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<DepenseRow | null>(null);
    const [form, setForm] = useState({
        cityUuid: '',
        category: '',
        amount: '',
        currency: 'USD' as 'USD' | 'CDF',
        expenseDate: new Date().toISOString().split('T')[0],
        method: '',
        supplier: '',
        reference: '',
        notes: '',
        parcelUuid: '',
        buildingUuid: '',
        unitUuid: '',
        workerUuid: '',
    });
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const [cancelTarget, setCancelTarget] = useState<DepenseRow | null>(null);
    const [cancelReason, setCancelReason] = useState('');
    const [cancelling, setCancelling] = useState(false);

    const openCreate = () => {
        setForm({
            cityUuid: '',
            category: '',
            amount: '',
            currency: 'USD',
            expenseDate: new Date().toISOString().split('T')[0],
            method: '',
            supplier: '',
            reference: '',
            notes: '',
            parcelUuid: '',
            buildingUuid: '',
            unitUuid: '',
            workerUuid: '',
        });
        setFormErrors({});
        setEditTarget(null);
        setExpenseModalOpen(true);
    };

    const openEdit = (target: DepenseRow) => {
        setForm({
            cityUuid: '',
            category: target.categoryCode,
            amount: target.amount,
            currency: target.currency as 'USD' | 'CDF',
            expenseDate: target.date,
            method: '',
            supplier: '',
            reference: '',
            notes: '',
            parcelUuid: '',
            buildingUuid: '',
            unitUuid: '',
            workerUuid: '',
        });
        setFormErrors({});
        setEditTarget(target);
        setExpenseModalOpen(true);
    };

    const closeExpenseModal = () => {
        setExpenseModalOpen(false);
        setEditTarget(null);
    };

    const submitExpense = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        setFormErrors({});

        const payload = {
            cityUuid: form.cityUuid,
            category: form.category,
            amount: form.amount.trim(),
            currency: form.currency,
            expenseDate: form.expenseDate,
            method: form.method.trim() || undefined,
            supplier: form.supplier.trim() || undefined,
            reference: form.reference.trim() || undefined,
            notes: form.notes.trim() || undefined,
            parcelUuid: form.parcelUuid || undefined,
            buildingUuid: form.buildingUuid || undefined,
            unitUuid: form.unitUuid || undefined,
            workerUuid: form.workerUuid || undefined,
        };

        try {
            if (editTarget) {
                await updateExpense(editTarget.id, payload);
            } else {
                await createExpense(payload);
            }
            closeExpenseModal();
        } catch (cause) {
            setFormErrors(fieldErrorMap(cause));
        } finally {
            setSubmitting(false);
        }
    };

    const openCancel = (target: DepenseRow) => {
        setCancelTarget(target);
        setCancelReason('');
    };

    const closeCancelModal = () => {
        setCancelTarget(null);
    };

    const submitCancel = async (): Promise<void> => {
        if (cancelling || !cancelTarget) return;
        setCancelling(true);
        try {
            await cancelExpense(cancelTarget.id, cancelReason.trim() || 'Annulation sans motif précisé.');
            closeCancelModal();
        } catch (cause) {
            // toast already shown by hook
        } finally {
            setCancelling(false);
        }
    };

    let body = <DepensesTable rows={rows} role={organizationRole} onEdit={openEdit} onCancel={openCancel} />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement des dépenses…" description="Récupération des coûts de l’organisation." />;
    } else if (error) {
        body = <EmptyState title="Dépenses indisponibles" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />;
    } else if (rows.length === 0) {
        body = <EmptyState
            title="Aucune dépense à afficher"
            description={data && data.rows.length > 0 ? 'Aucune dépense ne correspond aux filtres.' : 'Aucune dépense enregistrée pour cette organisation.'}
        />;
    }

    return <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">SUIVI FINANCIER</span><h1>Dépenses</h1><p>Consultez les coûts liés à vos biens et à leur fonctionnement.</p></div></header>
        <OrganizationSummary items={[{ label: 'Dépenses affichées', value: <>{rows.length}</> }, { label: 'Villes concernées', value: <>{new Set(rows.map((row) => row.city)).size}</> }, { label: 'Catégories visibles', value: <>{new Set(rows.map((row) => row.category)).size}</> }]} />
        <Card className="organization-table-card" padding="medium"><div className="organization-table-toolbar"><div><h2>Historique des dépenses</h2><p>{data?.note ?? 'Les montants sont affichés dans leur devise d’origine.'}</p></div><div className="organization-filters organization-filters--tenant"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une dépense…" fullWidth /><Select aria-label="Filtrer par catégorie" value={category} onChange={(event) => setCategory(event.target.value)} options={[{ value: 'all', label: 'Toutes catégories' }, ...availableCategories.map(([value, label]) => ({ value, label }))]} />{canDo(organizationRole, 'create_expense') && <Button onClick={openCreate}>＋ Enregistrer une dépense</Button>}</div></div>{body}</Card>
        <ConfirmDialog
            isOpen={cancelTarget !== null}
            onClose={closeCancelModal}
            onConfirm={() => void submitCancel()}
            title="Annuler cette dépense ?"
            message={`${cancelTarget?.description ?? 'Cette dépense'} sera annulée par une contre-écriture comptable. Le motif est requis.`}
            confirmLabel={cancelling ? 'Annulation…' : 'Annuler la dépense'}
            cancelLabel="Annuler"
        />
        <Modal
            isOpen={expenseModalOpen}
            onClose={closeExpenseModal}
            title={editTarget ? `Modifier « ${editTarget.description} »` : 'Enregistrer une dépense'}
            size="large"
            footer={<ModalActions formId="expense-form" onCancel={closeExpenseModal} submitLabel={editTarget ? 'Enregistrer' : 'Créer la dépense'} loadingLabel={editTarget ? 'Enregistrement…' : 'Création…'} isLoading={submitting} />}
        >
            <form id="expense-form" className="organization-management-form" onSubmit={submitExpense}>
                <FormField label="Ville" htmlFor="expense-city" required error={formErrors.cityUuid}>
                    <Select id="expense-city" value={form.cityUuid} onChange={(event) => setForm((current) => ({ ...current, cityUuid: event.target.value }))} options={[] as { value: string; label: string }[]} placeholder="Sélectionner une ville" required />
                </FormField>
                <FormField label="Catégorie" htmlFor="expense-category" required error={formErrors.category}>
                    <Select id="expense-category" value={form.category} onChange={(event) => setForm((current) => ({ ...current, category: event.target.value }))} options={CATEGORY_OPTIONS} required />
                </FormField>
                <FormField label="Montant" htmlFor="expense-amount" required error={formErrors.amount}>
                    <Input id="expense-amount" type="number" step="0.01" min="0.01" inputMode="decimal" value={form.amount} onChange={(event) => setForm((current) => ({ ...current, amount: event.target.value }))} placeholder="Ex. : 1500.00" required fullWidth />
                </FormField>
                <FormField label="Devise" htmlFor="expense-currency" required error={formErrors.currency}>
                    <Select id="expense-currency" value={form.currency} onChange={(event) => setForm((current) => ({ ...current, currency: event.target.value as 'USD' | 'CDF' }))} options={CURRENCY_OPTIONS} />
                </FormField>
                <FormField label="Date de la dépense" htmlFor="expense-date" required error={formErrors.expenseDate}>
                    <Input id="expense-date" type="date" value={form.expenseDate} onChange={(event) => setForm((current) => ({ ...current, expenseDate: event.target.value }))} required fullWidth />
                </FormField>
                <FormField label="Mode de paiement" htmlFor="expense-method" error={formErrors.method}>
                    <Select id="expense-method" value={form.method} onChange={(event) => setForm((current) => ({ ...current, method: event.target.value }))} options={EXPENSE_METHOD_OPTIONS} placeholder="Sélectionner" />
                </FormField>
                <FormField label="Fournisseur" htmlFor="expense-supplier" error={formErrors.supplier}>
                    <Input id="expense-supplier" value={form.supplier} onChange={(event) => setForm((current) => ({ ...current, supplier: event.target.value }))} placeholder="Ex. : Eau du Kivu SA" fullWidth maxLength={150} />
                </FormField>
                <FormField label="Référence" htmlFor="expense-reference" error={formErrors.reference}>
                    <Input id="expense-reference" value={form.reference} onChange={(event) => setForm((current) => ({ ...current, reference: event.target.value }))} placeholder="Ex. : FACT-2026-042" fullWidth maxLength={100} />
                </FormField>
                <FormField label="Notes" htmlFor="expense-notes" error={formErrors.notes}>
                    <Textarea id="expense-notes" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} rows={3} fullWidth maxLength={1000} />
                </FormField>
            </form>
        </Modal>
    </main>;
}