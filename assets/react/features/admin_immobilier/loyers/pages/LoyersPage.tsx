import { useState, type FormEvent } from 'react';
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
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { useLoyers } from '../hooks/useLoyers';
import { LoyersTable } from '../components/LoyersTable';
import { fieldErrorMap } from '../../../shared/actionErrors';
import { ModalActions } from '../../../shared/components/ModalActions';
import { canDo } from '../../../shared/permissions';
import type { LoyerRow } from '../types/loyer.types';
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

export function LoyersPage() {
    const [searchParams] = useSearchParams();
    const parcelUuid = searchParams.get('parcelUuid');
    const buildingUuid = searchParams.get('buildingUuid');
    const { organizationRole } = useOrganization();
    const {
        data, rows: allRows, isLoading, error, reload,
        search, setSearch, status, setStatus,
        recordPayment, markOverdue, updateRent,
    } = useLoyers();
    const rows = allRows.filter((row) => (!parcelUuid || row.parcelId === parcelUuid) && (!buildingUuid || row.buildingId === buildingUuid));

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
            await updateRent(updateTarget.id, payload);
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

    let body = <LoyersTable rows={rows} role={organizationRole} onRecordPayment={openPayment} onMarkOverdue={confirmMarkOverdue} onUpdate={openUpdate} />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement des échéances…" description="Récupération des loyers de l’organisation." />;
    } else if (error) {
        body = <EmptyState title="Échéances indisponibles" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />;
    } else if (rows.length === 0) {
        body = <EmptyState
            title="Aucune échéance à afficher"
            description={data && data.rows.length > 0 ? 'Aucune échéance ne correspond à la recherche.' : 'Aucune échéance ne correspond au statut sélectionné.'}
        />;
    }

    return <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">SUIVI FINANCIER</span><h1>Loyers</h1><p>Suivez les échéances, paiements et retards de votre portefeuille.</p></div></header>
        <OrganizationSummary items={[{ label: 'Échéances listées', value: <>{rows.length}</> }, { label: 'Payées', value: <>{rows.filter((row) => row.status === 'paid').length}</> }, { label: 'En retard', value: <>{rows.filter((row) => row.status === 'overdue').length}</> }]} />
        <Card className="organization-table-card" padding="medium"><div className="organization-table-toolbar"><div><h2>Échéances récentes</h2><p>{data?.note ?? 'Les montants sont affichés dans leur devise d’origine.'}</p></div><div className="organization-filters organization-filters--tenant"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une échéance…" fullWidth /><Select aria-label="Filtrer par statut" value={status} onChange={(event) => setStatus(event.target.value)} options={[{ value: 'all', label: 'Tous les statuts' }, { value: 'pending', label: 'En attente' }, { value: 'partially_paid', label: 'Partiel' }, { value: 'paid', label: 'Payé' }, { value: 'overdue', label: 'En retard' }]} /></div></div>{body}</Card>
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
    </main>;
}
