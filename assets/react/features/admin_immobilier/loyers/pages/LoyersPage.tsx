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
import { useLoyers } from '../hooks/useLoyers';
import { LoyersTable } from '../components/LoyersTable';
import { fieldErrorMap } from '../../../shared/utils/actionErrors';
import { changedFields } from '../../../shared/utils/changedFields';
import { ModalActions } from '../../../shared/components/ModalActions';
import { canDo } from '../../../shared/permissions';
import type { LoyerRow } from '../types/loyer.types';
import { ActivityCalendar, activityDateKey, type ActivityCalendarEvent } from '../../../shared/components/ActivityCalendar';
import { CalendarRailPopover } from '../../../shared/components/CalendarRailPopover';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import '../../../../../styles/pages/admin_immobilier/loyers/_loyers.scss';

const PAYMENT_METHOD_OPTIONS = [
    { value: 'cash', label: 'Espèces' },
    { value: 'bank_transfer', label: 'Virement bancaire' },
    { value: 'mobile_money', label: 'Mobile Money' },
    { value: 'check', label: 'Chèque' },
    { value: 'other', label: 'Autre' },
];

const CURRENCY_OPTIONS = [
    { value: 'USD', label: 'USD' },
    { value: 'CDF', label: 'CDF' },
];

export function LoyersPage({ readOnly = false }: { readOnly?: boolean } = {}) {
    const [searchParams] = useSearchParams();
    const parcelUuid = searchParams.get('parcelUuid');
    const buildingUuid = searchParams.get('buildingUuid');
    const { organizationRole } = useOrganization();
    const {
        data, rows: allRows, isLoading, error, reload, loadPeriod,
        search, setSearch, status, setStatus,
        recordPayment, markOverdue, updateRent,
    } = useLoyers();
    const [calendarData, setCalendarData] = useState<Awaited<ReturnType<typeof loadPeriod>>>(null);
    const [selectedDate, setSelectedDate] = useState<string | null>(null);
    const monthRequest = useRef(0);

    const loadCalendarMonth = useCallback(async (month: number, year: number) => {
        const requestId = ++monthRequest.current;
        setCalendarData(null);
        const start = new Date(year, month, 1);
        const end = new Date(year, month + 1, 0);
        const toKey = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        try {
            const period = await loadPeriod(toKey(start), toKey(end));
            if (requestId === monthRequest.current) setCalendarData(period);
        } catch {
            if (requestId === monthRequest.current) setCalendarData(null);
        }
    }, [loadPeriod]);

    useEffect(() => {
        const today = new Date();
        void loadCalendarMonth(today.getMonth(), today.getFullYear());
        return () => { monthRequest.current += 1; };
    }, [loadCalendarMonth]);

    const propertyRows = (source: LoyerRow[]) => source.filter((row) => (!parcelUuid || row.parcelId === parcelUuid) && (!buildingUuid || row.buildingId === buildingUuid));
    const recentRows = propertyRows(allRows);
    const monthRents = propertyRows(calendarData?.rents.rows ?? allRows).filter((row) => status === 'all' || row.status === status);
    const monthSearch = search.trim().toLocaleLowerCase('fr');
    const filteredMonthRents = monthRents.filter((row) => !monthSearch || [row.tenant, row.unitLabel, row.periodLabel].some((value) => value.toLocaleLowerCase('fr').includes(monthSearch)));
    const paymentEvents = calendarData?.payments ?? [];
    const selectedRows = selectedDate
        ? filteredMonthRents.filter((row) => activityDateKey(row.dueDate) === selectedDate || paymentEvents.some((payment) => payment.rentId === row.id && activityDateKey(payment.paymentDate) === selectedDate))
        : recentRows;
    const calendarEvents: ActivityCalendarEvent[] = [
        ...filteredMonthRents.map((row) => ({ date: row.dueDate, title: `Échéance · ${row.tenant}`, detail: `${row.amount} ${row.currency} · ${row.status === 'overdue' ? 'En retard' : row.status}`, tone: row.status === 'overdue' ? 'error' as const : 'info' as const })),
        ...paymentEvents.map((payment) => ({ date: payment.paymentDate, title: payment.isReversal ? 'Contre-écriture de paiement' : 'Paiement de loyer', detail: `${payment.amount} ${payment.currency}`, tone: payment.isReversal ? 'warning' as const : 'success' as const })),
    ];

    const [paymentModalOpen, setPaymentModalOpen] = useState(false);
    const [paymentTarget, setPaymentTarget] = useState<LoyerRow | null>(null);
    const [paymentForm, setPaymentForm] = useState({
        amount: '',
        currency: 'USD' as 'USD' | 'CDF',
        paymentDate: '',
        method: '',
        reference: '',
        notes: '',
    });
    const [paymentFormErrors, setPaymentFormErrors] = useState<Record<string, string>>({});
    const [submittingPayment, setSubmittingPayment] = useState(false);

    const [updateModalOpen, setUpdateModalOpen] = useState(false);
    const [updateTarget, setUpdateTarget] = useState<LoyerRow | null>(null);
    const [updateForm, setUpdateForm] = useState({
        dueDate: '',
        amount: '',
        currency: 'USD' as 'USD' | 'CDF',
        notes: '',
    });
    const [updateFormErrors, setUpdateFormErrors] = useState<Record<string, string>>({});
    const [submittingUpdate, setSubmittingUpdate] = useState(false);

    const openPayment = (target: LoyerRow) => {
        setPaymentTarget(target);
        setPaymentForm({
            amount: target.amount,
            currency: target.currency as 'USD' | 'CDF',
            paymentDate: new Date().toISOString().split('T')[0],
            method: '',
            reference: '',
            notes: '',
        });
        setPaymentFormErrors({});
        setPaymentModalOpen(true);
    };

    const closePaymentModal = () => {
        setPaymentModalOpen(false);
        setPaymentTarget(null);
    };

    const submitPayment = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (submittingPayment || !paymentTarget) return;
        setSubmittingPayment(true);
        setPaymentFormErrors({});

        const payload = {
            rentUuid: paymentTarget.id,
            amount: paymentForm.amount.trim(),
            currency: paymentForm.currency,
            paymentDate: paymentForm.paymentDate,
            method: paymentForm.method.trim() || undefined,
            reference: paymentForm.reference.trim() || undefined,
            notes: paymentForm.notes.trim() || undefined,
        };

        try {
            await recordPayment(payload);
            closePaymentModal();
        } catch (cause) {
            setPaymentFormErrors(fieldErrorMap(cause));
        } finally {
            setSubmittingPayment(false);
        }
    };

    const openUpdate = (target: LoyerRow) => {
        setUpdateTarget(target);
        setUpdateForm({
            dueDate: target.dueDate,
            amount: target.amount,
            currency: target.currency as 'USD' | 'CDF',
            notes: '',
        });
        setUpdateFormErrors({});
        setUpdateModalOpen(true);
    };

    const closeUpdateModal = () => {
        setUpdateModalOpen(false);
        setUpdateTarget(null);
    };

    const submitUpdate = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (submittingUpdate || !updateTarget) return;
        setSubmittingUpdate(true);
        setUpdateFormErrors({});

        const payload = {
            dueDate: updateForm.dueDate || undefined,
            amount: updateForm.amount.trim() || undefined,
            currency: updateForm.currency,
            notes: updateForm.notes.trim() || undefined,
        };

        try {
            const initial = {
                dueDate: updateTarget.dueDate,
                amount: updateTarget.amount,
                currency: updateTarget.currency as 'USD' | 'CDF',
                notes: undefined,
            };
            const changes = changedFields(payload, initial);
            if (Object.keys(changes).length > 0) await updateRent(updateTarget.id, changes);
            closeUpdateModal();
        } catch (cause) {
            setUpdateFormErrors(fieldErrorMap(cause));
        } finally {
            setSubmittingUpdate(false);
        }
    };

    const confirmMarkOverdue = async (target: LoyerRow) => {
        if (!confirm(`Marquer l'échéance « ${target.tenant} - ${target.periodLabel} » comme impayée ?`)) return;
        await markOverdue(target.id);
    };

    let body = <LoyersTable rows={selectedRows} role={organizationRole} onRecordPayment={openPayment} onMarkOverdue={confirmMarkOverdue} onUpdate={openUpdate} readOnly={readOnly} />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement des échéances…" description="Récupération des loyers de l’organisation." />;
    } else if (error) {
        body = <EmptyState title="Échéances indisponibles" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />;
    } else if (selectedRows.length === 0) {
        body = <EmptyState
            title={selectedDate ? 'Aucune échéance ou paiement ce jour-là' : 'Aucune échéance à afficher'}
            description={data && data.rows.length > 0 ? 'Aucune échéance ne correspond à la recherche.' : 'Aucune échéance ne correspond au statut sélectionné.'}
        />;
    }

    return <>
    <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">SUIVI FINANCIER</span><h1>Loyers</h1><p>Suivez les échéances, paiements et retards de votre portefeuille.</p></div></header>
        <OrganizationSummary items={[{ label: 'Échéances listées', value: <>{selectedRows.length}</> }, { label: 'Payées', value: <>{selectedRows.filter((row) => row.status === 'paid').length}</> }, { label: 'En retard', value: <>{selectedRows.filter((row) => row.status === 'overdue').length}</> }]} />
        <Card className="organization-table-card" padding="medium"><div className="organization-table-toolbar"><div><h2>{selectedDate ? `Échéances du ${selectedDate}` : 'Échéances récentes'}</h2><p>{selectedDate ? `${selectedRows.length} échéance(s) liée(s) à cette date.` : data?.note ?? 'Les montants sont affichés dans leur devise d’origine.'}</p></div><div className="organization-filters organization-filters--tenant"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une échéance…" fullWidth /><Select aria-label="Filtrer par statut" value={status} onChange={(event) => setStatus(event.target.value)} options={[{ value: 'all', label: 'Tous les statuts' }, { value: 'pending', label: 'En attente' }, { value: 'partially_paid', label: 'Partiel' }, { value: 'paid', label: 'Payé' }, { value: 'overdue', label: 'En retard' }]} /></div></div>{body}</Card>
        <Modal
            isOpen={paymentModalOpen}
            onClose={closePaymentModal}
            title={paymentTarget ? `Enregistrer un paiement pour « ${paymentTarget.tenant} »` : 'Enregistrer un paiement'}
            size="medium"
            footer={<ModalActions formId="payment-form" onCancel={closePaymentModal} submitLabel='Enregistrer le paiement' loadingLabel='Enregistrement…' isLoading={submittingPayment} />}
        >
            <form id="payment-form" className="organization-management-form" onSubmit={submitPayment}>
                <p className="organization-management-form__hint">Le montant ne doit pas dépasser le solde dû de l'échéance.</p>
                <FormField label="Montant" htmlFor="payment-amount" required error={paymentFormErrors.amount}>
                    <Input id="payment-amount" type="number" step="0.01" min="0.01" inputMode="decimal" value={paymentForm.amount} onChange={(event) => setPaymentForm((current) => ({ ...current, amount: event.target.value }))} placeholder="Ex. : 500.00" required fullWidth />
                </FormField>
                <FormField label="Devise" htmlFor="payment-currency" required error={paymentFormErrors.currency}>
                    <Select id="payment-currency" value={paymentForm.currency} onChange={(event) => setPaymentForm((current) => ({ ...current, currency: event.target.value as 'USD' | 'CDF' }))} options={CURRENCY_OPTIONS} />
                </FormField>
                <FormField label="Date du paiement" htmlFor="payment-date" required error={paymentFormErrors.paymentDate}>
                    <Input id="payment-date" type="date" value={paymentForm.paymentDate} onChange={(event) => setPaymentForm((current) => ({ ...current, paymentDate: event.target.value }))} required fullWidth />
                </FormField>
                <FormField label="Mode de paiement" htmlFor="payment-method" error={paymentFormErrors.method}>
                    <Select id="payment-method" value={paymentForm.method} onChange={(event) => setPaymentForm((current) => ({ ...current, method: event.target.value }))} options={PAYMENT_METHOD_OPTIONS} placeholder="Sélectionner" />
                </FormField>
                <FormField label="Référence" htmlFor="payment-reference" error={paymentFormErrors.reference}>
                    <Input id="payment-reference" value={paymentForm.reference} onChange={(event) => setPaymentForm((current) => ({ ...current, reference: event.target.value }))} placeholder="Ex. : RECU-12345" fullWidth maxLength={100} />
                </FormField>
                <FormField label="Notes" htmlFor="payment-notes" error={paymentFormErrors.notes}>
                    <Textarea id="payment-notes" value={paymentForm.notes} onChange={(event) => setPaymentForm((current) => ({ ...current, notes: event.target.value }))} rows={3} fullWidth maxLength={1000} />
                </FormField>
            </form>
        </Modal>
        <Modal
            isOpen={updateModalOpen}
            onClose={closeUpdateModal}
            title={updateTarget ? `Modifier l'échéance « ${updateTarget.tenant} - ${updateTarget.periodLabel} »` : 'Modifier l\'échéance'}
            size="medium"
            footer={<ModalActions formId="update-form" onCancel={closeUpdateModal} submitLabel='Enregistrer' loadingLabel='Enregistrement…' isLoading={submittingUpdate} />}
        >
            <form id="update-form" className="organization-management-form" onSubmit={submitUpdate}>
                <FormField label="Date d'échéance" htmlFor="update-duedate" error={updateFormErrors.dueDate}>
                    <Input id="update-duedate" type="date" value={updateForm.dueDate} onChange={(event) => setUpdateForm((current) => ({ ...current, dueDate: event.target.value }))} fullWidth />
                </FormField>
                <FormField label="Montant dû" htmlFor="update-amount" error={updateFormErrors.amount}>
                    <Input id="update-amount" type="number" step="0.01" min="0.01" inputMode="decimal" value={updateForm.amount} onChange={(event) => setUpdateForm((current) => ({ ...current, amount: event.target.value }))} placeholder="Ex. : 500.00" fullWidth />
                </FormField>
                <FormField label="Devise" htmlFor="update-currency" required error={updateFormErrors.currency}>
                    <Select id="update-currency" value={updateForm.currency} onChange={(event) => setUpdateForm((current) => ({ ...current, currency: event.target.value as 'USD' | 'CDF' }))} options={CURRENCY_OPTIONS} />
                </FormField>
                <FormField label="Notes" htmlFor="update-notes" error={updateFormErrors.notes}>
                    <Textarea id="update-notes" value={updateForm.notes} onChange={(event) => setUpdateForm((current) => ({ ...current, notes: event.target.value }))} rows={3} fullWidth maxLength={1000} />
                </FormField>
            </form>
        </Modal>
    </main>
    <RightSidebar title="Calendrier des loyers" size="medium" variant="dark" collapsible collapsedWidth={48} collapsedContent={<div className="financial-calendar-rail"><CalendarRailPopover title="Activité des loyers" label="Ouvrir le calendrier des loyers"><ActivityCalendar events={calendarEvents} label="Calendrier des loyers et paiements" selectedDate={selectedDate} onSelectDate={setSelectedDate} onClearDate={() => setSelectedDate(null)} onMonthChange={(month, year) => { setSelectedDate(null); void loadCalendarMonth(month, year); }} description={calendarData === null ? 'Chargement des loyers et encaissements du mois…' : calendarData.paymentsUnavailable ? 'Les paiements ne sont pas disponibles ; les marqueurs affichent les échéances de loyer.' : calendarData.rents.note ?? (calendarData.payments.length >= 100 ? 'Historique mensuel plafonné à 100 résultats.' : 'Les marqueurs indiquent les échéances de loyer et les paiements reçus. Sélectionnez une date pour afficher les opérations correspondantes.')} emptyMessage="Aucune échéance ni paiement connu pour cette journée." /></CalendarRailPopover></div>}>
        <ActivityCalendar
            events={calendarEvents}
            label="Calendrier des loyers et paiements"
            selectedDate={selectedDate}
            onSelectDate={setSelectedDate}
            onClearDate={() => setSelectedDate(null)}
            onMonthChange={(month, year) => { setSelectedDate(null); void loadCalendarMonth(month, year); }}
            description={calendarData === null ? 'Chargement des loyers et encaissements du mois…' : calendarData.paymentsUnavailable ? 'Les paiements ne sont pas disponibles ; les marqueurs affichent les échéances de loyer.' : calendarData.rents.note ?? (calendarData.payments.length >= 100 ? 'Historique mensuel plafonné à 100 résultats.' : 'Les marqueurs indiquent les échéances de loyer et les paiements reçus. Sélectionnez une date pour afficher les opérations correspondantes.')}
            emptyMessage="Aucune échéance ni paiement connu pour cette journée."
        />
    </RightSidebar>
    </>;
}
