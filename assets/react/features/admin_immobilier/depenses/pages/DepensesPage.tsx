import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react';
import { useSearchParams } from 'react-router-dom';

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
import { RightSidebar } from '../../../../components/Navigation/RightSidebar';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { useDepenses } from '../hooks/useDepenses';
import { DepensesTable } from '../components/DepensesTable';
import { fieldErrorMap } from '../../../shared/actionErrors';
import { ModalActions } from '../../../shared/components/ModalActions';
import { canDo } from '../../../shared/permissions';
import { CATEGORY_OPTIONS, CURRENCY_OPTIONS, EXPENSE_METHOD_OPTIONS } from '../services/depensesService';
import type { DepenseRow } from '../types/depense.types';
import { ActivityCalendar, activityDateKey, type ActivityCalendarEvent } from '../../../shared/components/ActivityCalendar';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import '../../../../../styles/pages/admin_immobilier/depenses/_depenses.scss';

export function DepensesPage({ readOnly = false }: { readOnly?: boolean } = {}) {
    const [searchParams] = useSearchParams();
    const parcelUuid = searchParams.get('parcelUuid');
    const buildingUuid = searchParams.get('buildingUuid');
    const { organizationRole } = useOrganization();
    const {
        data, rows: allRows, availableCategories, isLoading, error, reload,
        search, setSearch, category, setCategory, loadPeriod,
        createExpense, updateExpense, cancelExpense,
    } = useDepenses();
    const [calendarRows, setCalendarRows] = useState<DepenseRow[] | null>(null);
    const [calendarNote, setCalendarNote] = useState<string | null>(null);
    const [selectedDate, setSelectedDate] = useState<string | null>(null);
    const monthRequest = useRef(0);

    const loadCalendarMonth = useCallback(async (month: number, year: number) => {
        const requestId = ++monthRequest.current;
        setCalendarRows(null);
        setCalendarNote(null);
        const start = new Date(year, month, 1);
        const end = new Date(year, month + 1, 0);
        const toKey = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        try {
            const period = await loadPeriod(toKey(start), toKey(end));
            if (requestId === monthRequest.current) {
                setCalendarRows(period?.rows ?? []);
                setCalendarNote(period?.note ?? null);
            }
        } catch {
            if (requestId === monthRequest.current) {
                setCalendarRows([]);
                setCalendarNote('Impossible de charger toutes les activités de cette période.');
            }
        }
    }, [loadPeriod]);

    useEffect(() => {
        const today = new Date();
        void loadCalendarMonth(today.getMonth(), today.getFullYear());
        return () => { monthRequest.current += 1; };
    }, [loadCalendarMonth]);

    const query = search.trim().toLocaleLowerCase('fr');
    const filterRows = (source: DepenseRow[]) => source.filter((row) =>
        (!query || [row.category, row.property, row.description, row.city].some((value) => value.toLocaleLowerCase('fr').includes(query)))
        && (category === 'all' || row.categoryCode === category)
        && (!parcelUuid || row.parcelId === parcelUuid)
        && (!buildingUuid || row.buildingId === buildingUuid),
    );
    const recentRows = filterRows(allRows);
    const monthRows = filterRows(calendarRows ?? allRows);
    const selectedRows = selectedDate ? monthRows.filter((row) => activityDateKey(row.expenseDate) === selectedDate) : recentRows;
    const calendarEvents: ActivityCalendarEvent[] = monthRows.map((row) => ({
        date: row.expenseDate,
        title: `${row.category} · ${row.property}`,
        detail: `${row.amount} ${row.currency}`,
        tone: 'warning',
    }));

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
            cityUuid: target.cityId,
            category: target.categoryCode,
            amount: target.amount,
            currency: target.currency as 'USD' | 'CDF',
            expenseDate: target.expenseDate.slice(0, 10),
            method: target.method ?? '',
            supplier: target.supplier ?? '',
            reference: target.reference ?? '',
            notes: target.notes ?? '',
            parcelUuid: target.parcelId ?? '',
            buildingUuid: target.buildingId ?? '',
            unitUuid: target.unitId ?? '',
            workerUuid: target.workerId ?? '',
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

    let body = <DepensesTable rows={selectedRows} role={organizationRole} onEdit={openEdit} onCancel={openCancel} readOnly={readOnly} />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement des dépenses…" description="Récupération des coûts de l’organisation." />;
    } else if (error) {
        body = <EmptyState title="Dépenses indisponibles" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />;
    } else if (selectedRows.length === 0) {
        body = <EmptyState
            title={selectedDate ? 'Aucune dépense ce jour-là' : 'Aucune dépense à afficher'}
            description={data && data.rows.length > 0 ? 'Aucune dépense ne correspond aux filtres.' : 'Aucune dépense enregistrée pour cette organisation.'}
        />;
    }

    return <>
        <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">SUIVI FINANCIER</span><h1>Dépenses</h1><p>Consultez les coûts liés à vos biens et à leur fonctionnement.</p></div></header>
        <OrganizationSummary items={[{ label: 'Dépenses affichées', value: <>{selectedRows.length}</> }, { label: 'Villes concernées', value: <>{new Set(selectedRows.map((row) => row.city)).size}</> }, { label: 'Catégories visibles', value: <>{new Set(selectedRows.map((row) => row.category)).size}</> }]} />
        <Card className="organization-table-card" padding="medium"><div className="organization-table-toolbar"><div><h2>{selectedDate ? `Dépenses du ${selectedDate}` : 'Historique des dépenses'}</h2><p>{selectedDate ? `${selectedRows.length} opération(s) à cette date.` : data?.note ?? 'Les montants sont affichés dans leur devise d’origine.'}</p></div><div className="organization-filters organization-filters--tenant"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une dépense…" fullWidth /><Select aria-label="Filtrer par catégorie" value={category} onChange={(event) => setCategory(event.target.value)} options={[{ value: 'all', label: 'Toutes catégories' }, ...availableCategories.map(([value, label]) => ({ value, label }))]} />{!readOnly && canDo(organizationRole, 'create_expense') && <Button onClick={openCreate}>＋ Enregistrer une dépense</Button>}</div></div>{body}</Card>
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
                    <Select id="expense-city" value={form.cityUuid} onChange={(event) => setForm((current) => ({ ...current, cityUuid: event.target.value }))} options={(data?.cities ?? []).map((city) => ({ value: city.id, label: city.name }))} placeholder="Sélectionner une ville" required />
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
        </main>
        <RightSidebar title="Calendrier des dépenses" size="medium" variant="dark" collapsible>
            <ActivityCalendar
                events={calendarEvents}
                label="Calendrier des dépenses"
                selectedDate={selectedDate}
                onSelectDate={setSelectedDate}
                onClearDate={() => setSelectedDate(null)}
                onMonthChange={(month, year) => { setSelectedDate(null); void loadCalendarMonth(month, year); }}
                description={calendarRows === null ? 'Chargement des dépenses du mois…' : calendarNote ? `${calendarNote} Les marqueurs indiquent les dépenses enregistrées pour le mois choisi.` : 'Les jours marqués correspondent aux dépenses enregistrées. Sélectionnez un jour pour afficher ses opérations.'}
                emptyMessage="Aucune dépense connue pour cette journée."
            />
        </RightSidebar>
    </>;
}
