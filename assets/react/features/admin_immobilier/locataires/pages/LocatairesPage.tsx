import { useEffect, useState, type FormEvent } from 'react';
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
import { PrivateDocumentsModal } from '../../../shared/finance/components/PrivateDocumentsModal';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { useAdminCity } from '../../../../app/providers/AdminCityProvider';
import { useLocataires } from '../hooks/useLocataires';
import { LocatairesTable } from '../components/LocatairesTable';
import { ResidentialHistoryModal } from '../components/ResidentialHistoryModal';
import { fieldErrorMap } from '../../../shared/utils/actionErrors';
import { changedFields } from '../../../shared/utils/changedFields';
import { ModalActions } from '../../../shared/components/ModalActions';
import { canDo } from '../../../shared/permissions';
import type { LocataireRow } from '../types/locataire.types';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import '../../../../../styles/pages/admin_immobilier/locataires/_locataires.scss';

const TENANT_TYPE_OPTIONS = [
    { value: 'individual', label: 'Personne physique' },
    { value: 'company', label: 'Personne morale' },
];

interface TenantForm {
    cityUuid: string;
    type: 'individual' | 'company';
    firstName: string;
    lastName: string;
    companyName: string;
    phone: string;
    email: string;
    address: string;
    notes: string;
}

const EMPTY_TENANT_FORM: TenantForm = { cityUuid: '', type: 'individual', firstName: '', lastName: '', companyName: '', phone: '', email: '', address: '', notes: '' };

function tenantToForm(row: LocataireRow): TenantForm {
    const nameParts = row.name.trim().split(/\s+/).filter(Boolean);
    const firstName = row.type === 'individual' ? nameParts.shift() ?? '' : '';

    return {
        cityUuid: row.cityUuid ?? '',
        type: row.type,
        firstName,
        lastName: row.type === 'individual' ? nameParts.join(' ') : '',
        companyName: row.type === 'company' ? row.name : '',
        phone: row.phone ?? '',
        email: row.email ?? '',
        address: row.address === '—' ? '' : row.address,
        notes: row.notes ?? '',
    };
}

export function LocatairesPage({ readOnly = false }: { readOnly?: boolean } = {}) {
    const [searchParams] = useSearchParams();
    const parcelUuid = searchParams.get('parcelUuid');
    const buildingUuid = searchParams.get('buildingUuid');
    const { organizationRole, currentOrganization } = useOrganization();
    const { activeCityUuid, activeCity } = useAdminCity();
    const isCityAdmin = organizationRole === 'admin_ville';
    const localOperationsReadOnly = readOnly || !isCityAdmin;
    const {
        data, rows: allRows, isLoading, error, reload,
        search, setSearch, type, setType, housingFilter, setHousingFilter,
        pending, requestArchive, cancelArchive, confirmArchive, isArchiving,
        createTenant, updateTenant, createLease, activateLease,
    } = useLocataires();
    useEffect(() => {
        setSearch(searchParams.get('search') ?? '');
    }, [searchParams, setSearch]);
    const rows = allRows.filter((row) => (!parcelUuid || row.parcelIds.includes(parcelUuid)) && (!buildingUuid || row.buildingIds.includes(buildingUuid)));

    const [tenantModalOpen, setTenantModalOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<LocataireRow | null>(null);
    const [form, setForm] = useState<TenantForm>(EMPTY_TENANT_FORM);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const [leaseModalOpen, setLeaseModalOpen] = useState(false);
    const [leaseTarget, setLeaseTarget] = useState<LocataireRow | null>(null);
    const [leaseToActivate, setLeaseToActivate] = useState<LocataireRow | null>(null);
    const [leaseForm, setLeaseForm] = useState({
        unitUuid: '',
        additionalUnitUuids: [] as string[],
        occupants: [] as Array<{ tenantUuid: string; role: 'co_tenant' | 'occupant'; startDate: string; endDate?: string }>,
        reference: '',
        startDate: '',
        endDate: '',
        monthlyRent: '',
        depositAmount: '',
        currency: 'USD' as 'USD' | 'CDF',
        notes: '',
    });
    const [leaseFormErrors, setLeaseFormErrors] = useState<Record<string, string>>({});
    const [submittingLease, setSubmittingLease] = useState(false);
    const [documentsTarget, setDocumentsTarget] = useState<LocataireRow | null>(null);
    const [historyTarget, setHistoryTarget] = useState<LocataireRow | null>(null);
    const primaryLeaseUnit = data?.units.find((unit) => unit.id === leaseForm.unitUuid);
    const leaseUnitsInScope = (data?.units ?? []).filter((unit) => {
        const city = primaryLeaseUnit?.cityUuid ?? leaseTarget?.cityUuid;
        return !city || unit.cityUuid === city;
    });

    const openCreate = () => {
        setForm({ ...EMPTY_TENANT_FORM, cityUuid: activeCityUuid });
        setFormErrors({});
        setEditTarget(null);
        setTenantModalOpen(true);
    };

    const openEdit = (target: LocataireRow) => {
        setForm({ ...tenantToForm(target), cityUuid: tenantToForm(target).cityUuid || (isCityAdmin ? activeCityUuid : '') });
        setFormErrors({});
        setEditTarget(target);
        setTenantModalOpen(true);
    };

    const closeTenantModal = () => {
        setTenantModalOpen(false);
        setEditTarget(null);
    };

    const submitTenant = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        setFormErrors({});

        const payload = {
            ...(form.cityUuid ? { cityUuid: form.cityUuid } : {}),
            type: form.type,
            firstName: form.type === 'individual' ? form.firstName.trim() : undefined,
            lastName: form.type === 'individual' ? form.lastName.trim() : undefined,
            companyName: form.type === 'company' ? form.companyName.trim() : undefined,
            phone: form.phone.trim(),
            email: form.email.trim() || undefined,
            address: form.address.trim() || undefined,
            notes: form.notes.trim() || undefined,
        };

        try {
            if (editTarget) {
                const initial = tenantToForm(editTarget);
                const initialPayload = {
                    cityUuid: initial.cityUuid,
                    type: initial.type,
                    firstName: initial.type === 'individual' ? initial.firstName.trim() : undefined,
                    lastName: initial.type === 'individual' ? initial.lastName.trim() : undefined,
                    companyName: initial.type === 'company' ? initial.companyName.trim() : undefined,
                    phone: initial.phone.trim(),
                    email: initial.email.trim(),
                    address: initial.address.trim(),
                    notes: initial.notes.trim(),
                };
                const updatePayload = {
                    cityUuid: form.cityUuid || initial.cityUuid,
                    type: form.type,
                    firstName: form.type === 'individual' ? form.firstName.trim() : undefined,
                    lastName: form.type === 'individual' ? form.lastName.trim() : undefined,
                    companyName: form.type === 'company' ? form.companyName.trim() : undefined,
                    phone: form.phone.trim(),
                    email: form.email.trim(),
                    address: form.address.trim(),
                    notes: form.notes.trim(),
                };
                const changes = changedFields(updatePayload, initialPayload);
                const nameChanged = ['type', 'firstName', 'lastName', 'companyName'].some((key) => key in changes);
                if (nameChanged) {
                    changes.type = updatePayload.type;
                    changes.firstName = updatePayload.firstName;
                    changes.lastName = updatePayload.lastName;
                    changes.companyName = updatePayload.companyName;
                }
                if (Object.keys(changes).length > 0) await updateTenant(editTarget.id, changes);
            } else {
                if (!currentOrganization?.uuid) throw new Error('Aucune organisation active.');
                await createTenant({ ...payload, organizationUuid: currentOrganization.uuid, ...(isCityAdmin && activeCityUuid ? { cityUuid: activeCityUuid } : {}) });
            }
            closeTenantModal();
        } catch (cause) {
            setFormErrors(fieldErrorMap(cause));
        } finally {
            setSubmitting(false);
        }
    };

    const openNewLease = (target: LocataireRow) => {
        setLeaseTarget(target);
        const now = new Date();
        const startDate = new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 10);
        setLeaseForm({ unitUuid: '', additionalUnitUuids: [], occupants: [], reference: '', startDate, endDate: '', monthlyRent: '', depositAmount: '', currency: 'USD', notes: '' });
        setLeaseFormErrors({});
        setLeaseModalOpen(true);
    };

    const closeLeaseModal = () => {
        setLeaseModalOpen(false);
        setLeaseTarget(null);
    };

    const submitLease = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (submittingLease || !leaseTarget) return;
        setSubmittingLease(true);
        setLeaseFormErrors({});

        const payload = {
            tenantUuid: leaseTarget.id,
            unitUuid: leaseForm.unitUuid,
            additionalUnitUuids: leaseForm.additionalUnitUuids.filter((uuid) => uuid !== leaseForm.unitUuid),
            occupants: leaseForm.occupants,
            reference: leaseForm.reference.trim(),
            startDate: leaseForm.startDate,
            endDate: leaseForm.endDate || undefined,
            monthlyRent: leaseForm.monthlyRent.trim(),
            depositAmount: leaseForm.depositAmount.trim() || undefined,
            currency: leaseForm.currency,
            notes: leaseForm.notes.trim() || undefined,
        };

        try {
            await createLease(payload);
            closeLeaseModal();
        } catch (cause) {
            setLeaseFormErrors(fieldErrorMap(cause));
        } finally {
            setSubmittingLease(false);
        }
    };

    let body = <LocatairesTable rows={rows} role={organizationRole} onEdit={openEdit} onArchive={requestArchive} onNewLease={openNewLease} onActivateLease={setLeaseToActivate} onDocuments={setDocumentsTarget} onHistory={setHistoryTarget} readOnly={localOperationsReadOnly} />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement des locataires…" description="Récupération des fiches et de leurs baux en cours." />;
    } else if (error) {
        body = <EmptyState title="Locataires indisponibles" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />;
    } else if (rows.length === 0) {
        body = <EmptyState
            title="Aucun locataire à afficher"
            description={data && data.rows.length > 0 ? 'Aucun locataire ne correspond à la recherche ou au filtre sélectionné.' : isCityAdmin ? `Aucun locataire n’est rattaché à ${activeCity?.name ?? 'la ville active'}.` : "Votre organisation n'a encore aucun locataire enregistré."}
        />;
    }

    return <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">GESTION LOCATIVE</span><h1>Locataires</h1><p>{isCityAdmin ? `Suivez les occupants, les baux et les mouvements de ${activeCity?.name ?? 'votre ville'}.` : 'Vue consolidée des occupants, des baux et des mouvements de votre organisation.'}</p></div></header>
        {!isCityAdmin && <p className="organization-management-form__hint">Consultation et supervision uniquement. Les entrées, départs, transferts et corrections sont enregistrés par l’ADMIN_VILLE responsable de la ville.</p>}
        <OrganizationSummary items={[{ label: 'Locataires', value: <>{data?.total ?? 0}</> }, { label: 'Baux en cours', value: <>{data?.activeLeases ?? 0}</> }, { label: 'Personnes morales', value: <>{(data?.rows ?? []).filter((row) => row.type === 'company').length}</> }]} />
        <Card className="organization-table-card" padding="medium"><div className="organization-table-toolbar"><div><h2>Liste des locataires</h2><p>{data?.note ?? 'La fin du bail affichée correspond au bail actif du locataire.'}</p></div><div className="organization-filters organization-filters--tenant"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un locataire…" fullWidth /><Select aria-label="Filtrer par type" value={type} onChange={(event) => setType(event.target.value)} options={[{ value: 'all', label: 'Tous les types' }, { value: 'individual', label: 'Personnes physiques' }, { value: 'company', label: 'Personnes morales' }]} /><Select aria-label="Filtrer par occupation ou mouvement" value={housingFilter} onChange={(event) => setHousingFilter(event.target.value as typeof housingFilter)} options={[{ value: 'all', label: 'Toutes les situations' }, { value: 'currently_housed', label: 'Occupants actuels' }, { value: 'former', label: 'Sans bail actif' }, { value: 'multiple_homes', label: 'Plusieurs logements' }, { value: 'expiring_soon', label: 'Bail finissant sous 30 jours' }, { value: 'departure_pending', label: 'Départs annoncés à confirmer' }, { value: 'recent_movement', label: 'Mouvements des 30 derniers jours' }, { value: 'late_movement', label: 'Mouvements saisis tardivement' }]} />{!localOperationsReadOnly && canDo(organizationRole, 'create_tenant') && <Button onClick={openCreate}>＋ Ajouter un locataire</Button>}</div></div>{body}</Card>
        <ConfirmDialog
            isOpen={pending !== null}
            onClose={cancelArchive}
            onConfirm={() => void confirmArchive()}
            title="Archiver ce locataire ?"
            message={`${pending?.name ?? 'Ce locataire'} sera retiré des listes. Ses baux et son historique restent conservés.`}
            confirmLabel={isArchiving ? 'Archivage…' : 'Archiver'}
            cancelLabel="Annuler"
        />
        <ConfirmDialog
            isOpen={leaseToActivate !== null}
            onClose={() => setLeaseToActivate(null)}
            onConfirm={() => { const uuid = leaseToActivate?.leaseUuid; setLeaseToActivate(null); if (uuid) void activateLease(uuid); }}
            title="Activer ce bail ?"
            message={`${leaseToActivate?.name ?? 'Ce locataire'} pourra être facturé selon les conditions du bail.`}
            confirmLabel="Activer le bail"
            cancelLabel="Annuler"
        />
        <Modal
            isOpen={tenantModalOpen}
            onClose={closeTenantModal}
            title={editTarget ? `Modifier « ${editTarget.name} »` : 'Ajouter un locataire'}
            size="medium"
            footer={<ModalActions formId="tenant-form" onCancel={closeTenantModal} submitLabel={editTarget ? 'Enregistrer' : 'Créer le locataire'} loadingLabel={editTarget ? 'Enregistrement…' : 'Création…'} isLoading={submitting} />}
        >
            <form id="tenant-form" className="organization-management-form" onSubmit={submitTenant}>
                <FormField label="Ville de rattachement" htmlFor="tenant-city" required={isCityAdmin} error={formErrors.cityUuid} helpText={isCityAdmin ? 'Ce locataire sera isolé dans la ville active.' : 'Rattachez les fiches sans bail à une ville pour les isoler dans chaque espace.'}>
                    <Select
                        id="tenant-city"
                        value={isCityAdmin ? activeCityUuid : form.cityUuid}
                        onChange={(event) => setForm((current) => ({ ...current, cityUuid: event.target.value }))}
                        options={[
                            { value: '', label: 'Aucune ville' },
                            ...(data?.cities ?? []).map((city) => ({ value: city.id, label: city.name })),
                        ]}
                        disabled={isCityAdmin}
                    />
                </FormField>
                <FormField label="Type de locataire" htmlFor="tenant-type" required error={formErrors.type}>
                    <Select id="tenant-type" value={form.type} onChange={(event) => setForm((current) => ({ ...current, type: event.target.value as 'individual' | 'company' }))} options={TENANT_TYPE_OPTIONS} />
                </FormField>
                {form.type === 'individual' && (
                    <>
                        <FormField label="Prénom" htmlFor="tenant-firstname" required error={formErrors.firstName}>
                            <Input id="tenant-firstname" value={form.firstName} onChange={(event) => setForm((current) => ({ ...current, firstName: event.target.value }))} placeholder="Ex. : Amani" required fullWidth maxLength={100} />
                        </FormField>
                        <FormField label="Nom" htmlFor="tenant-lastname" required error={formErrors.lastName}>
                            <Input id="tenant-lastname" value={form.lastName} onChange={(event) => setForm((current) => ({ ...current, lastName: event.target.value }))} placeholder="Ex. : Kambale" required fullWidth maxLength={100} />
                        </FormField>
                    </>
                )}
                {form.type === 'company' && (
                    <FormField label="Raison sociale" htmlFor="tenant-companyname" required error={formErrors.companyName}>
                        <Input id="tenant-companyname" value={form.companyName} onChange={(event) => setForm((current) => ({ ...current, companyName: event.target.value }))} placeholder="Ex. : Kivu Tech SARL" required fullWidth maxLength={150} />
                    </FormField>
                )}
                <FormField label="Téléphone" htmlFor="tenant-phone" required error={formErrors.phone}>
                    <Input id="tenant-phone" value={form.phone} onChange={(event) => setForm((current) => ({ ...current, phone: event.target.value }))} placeholder="+243…" required fullWidth maxLength={30} />
                </FormField>
                <FormField label="E-mail" htmlFor="tenant-email" error={formErrors.email}>
                    <Input id="tenant-email" type="email" value={form.email} onChange={(event) => setForm((current) => ({ ...current, email: event.target.value }))} placeholder="locataire@example.cd" fullWidth maxLength={180} />
                </FormField>
                <FormField label="Adresse" htmlFor="tenant-address" error={formErrors.address}>
                    <Input id="tenant-address" value={form.address} onChange={(event) => setForm((current) => ({ ...current, address: event.target.value }))} placeholder="05, Avenue du Centre, Quartier Les Volcans" fullWidth maxLength={255} />
                </FormField>
                <FormField label="Notes" htmlFor="tenant-notes" error={formErrors.notes}>
                    <Textarea id="tenant-notes" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} rows={3} fullWidth maxLength={1000} />
                </FormField>
            </form>
        </Modal>
        <Modal
            isOpen={leaseModalOpen}
            onClose={closeLeaseModal}
            title={leaseTarget ? `Nouveau bail pour « ${leaseTarget.name} »` : 'Nouveau bail'}
            size="large"
            footer={<ModalActions formId="lease-form" onCancel={closeLeaseModal} submitLabel='Créer le bail' loadingLabel='Création…' isLoading={submittingLease} />}
        >
            <form id="lease-form" className="organization-management-form" onSubmit={submitLease}>
                <p className="organization-management-form__hint">Création d'un bail à l'état <strong>Brouillon (DRAFT)</strong>. Il devra être activé pour générer les échéances.</p>
                <FormField label="Unité" htmlFor="lease-unit" required error={leaseFormErrors.unitUuid}>
                    <Select id="lease-unit" value={leaseForm.unitUuid} onChange={(event) => setLeaseForm((current) => ({ ...current, unitUuid: event.target.value, additionalUnitUuids: [] }))} options={leaseUnitsInScope.map((unit) => ({ value: unit.id, label: `${data?.buildings.find((building) => building.id === unit.buildingId)?.name ?? 'Bâtiment'} · ${unit.reference} · ${unit.monthlyRent} ${unit.currency}` }))} placeholder="Sélectionner une unité" required />
                </FormField>
                {(data?.units.length ?? 0) > 1 && <FormField label="Autres espaces inclus (facultatif)" htmlFor="lease-extra-units" error={leaseFormErrors.additionalUnitUuids}>
                    <div id="lease-extra-units" className="locataires-lease-options">
                        {leaseUnitsInScope.filter((unit) => unit.id !== leaseForm.unitUuid).map((unit) => <label key={unit.id} className="locataires-lease-options__item">
                            <input type="checkbox" checked={leaseForm.additionalUnitUuids.includes(unit.id)} onChange={(event) => setLeaseForm((current) => ({ ...current, additionalUnitUuids: event.target.checked ? [...current.additionalUnitUuids, unit.id] : current.additionalUnitUuids.filter((id) => id !== unit.id) }))} />
                            <span>{data?.buildings.find((building) => building.id === unit.buildingId)?.name ?? 'Bâtiment'} · {unit.reference}</span>
                        </label>)}
                    </div>
                    <small>Ces espaces seront couverts par le même contrat et le même loyer global.</small>
                </FormField>}
                {leaseTarget && allRows.filter((row) => row.id !== leaseTarget.id).length > 0 && <FormField label="Autres occupants ou cotitulaires (facultatif)" htmlFor="lease-occupants" error={leaseFormErrors.occupants}>
                    <div id="lease-occupants" className="locataires-lease-options">
                        {allRows.filter((row) => row.id !== leaseTarget.id).map((row) => {
                            const selected = leaseForm.occupants.find((item) => item.tenantUuid === row.id);
                            return <div key={row.id} className="locataires-lease-options__item">
                                <label><input type="checkbox" checked={Boolean(selected)} onChange={(event) => setLeaseForm((current) => ({ ...current, occupants: event.target.checked ? [...current.occupants, { tenantUuid: row.id, role: 'occupant', startDate: current.startDate }] : current.occupants.filter((item) => item.tenantUuid !== row.id) }))} /> <span>{row.name}</span></label>
                                {selected && <div className="locataires-occupant-fields">
                                    <Select
                                        aria-label={`Rôle de ${row.name}`}
                                        value={selected.role}
                                        onChange={(event) => setLeaseForm((current) => ({
                                            ...current,
                                            occupants: current.occupants.map((item) => item.tenantUuid === row.id
                                                ? { ...item, role: event.target.value as 'co_tenant' | 'occupant' }
                                                : item),
                                        }))}
                                        options={[{ value: 'occupant', label: 'Occupant' }, { value: 'co_tenant', label: 'Cotitulaire' }]}
                                    />
                                    <Input aria-label={`Entrée de ${row.name}`} type="date" value={selected.startDate} onChange={(event) => setLeaseForm((current) => ({
                                        ...current,
                                        occupants: current.occupants.map((item) => item.tenantUuid === row.id ? { ...item, startDate: event.target.value } : item),
                                    }))} />
                                    <Input aria-label={`Sortie de ${row.name}`} type="date" value={selected.endDate ?? ''} onChange={(event) => setLeaseForm((current) => ({
                                        ...current,
                                        occupants: current.occupants.map((item) => item.tenantUuid === row.id ? { ...item, endDate: event.target.value || undefined } : item),
                                    }))} />
                                </div>}
                            </div>;
                        })}
                    </div>
                    <small>Les cotitulaires et occupants restent liés au bail; leurs dates d’entrée commencent à la prise d’effet du contrat.</small>
                </FormField>}
                <FormField label="Référence du bail" htmlFor="lease-reference" required error={leaseFormErrors.reference}>
                    <Input id="lease-reference" value={leaseForm.reference} onChange={(event) => setLeaseForm((current) => ({ ...current, reference: event.target.value }))} placeholder="Ex. : LEASE-2026-0042" required fullWidth maxLength={50} />
                </FormField>
                <FormField label="Date de début" htmlFor="lease-startdate" required error={leaseFormErrors.startDate}>
                    <Input id="lease-startdate" type="date" value={leaseForm.startDate} onChange={(event) => setLeaseForm((current) => ({ ...current, startDate: event.target.value }))} required fullWidth />
                </FormField>
                <FormField label="Date de fin (optionnel)" htmlFor="lease-enddate" error={leaseFormErrors.endDate}>
                    <Input id="lease-enddate" type="date" value={leaseForm.endDate} onChange={(event) => setLeaseForm((current) => ({ ...current, endDate: event.target.value }))} fullWidth />
                </FormField>
                <FormField label="Loyer mensuel" htmlFor="lease-rent" required error={leaseFormErrors.monthlyRent}>
                    <Input id="lease-rent" type="number" step="0.01" min="0.01" inputMode="decimal" value={leaseForm.monthlyRent} onChange={(event) => setLeaseForm((current) => ({ ...current, monthlyRent: event.target.value }))} placeholder="Ex. : 500.00" required fullWidth />
                </FormField>
                <FormField label="Dépôt de garantie (optionnel)" htmlFor="lease-deposit" error={leaseFormErrors.depositAmount}>
                    <Input id="lease-deposit" type="number" step="0.01" min="0" inputMode="decimal" value={leaseForm.depositAmount} onChange={(event) => setLeaseForm((current) => ({ ...current, depositAmount: event.target.value }))} placeholder="Ex. : 1000.00" fullWidth />
                </FormField>
                <FormField label="Devise" htmlFor="lease-currency" required error={leaseFormErrors.currency}>
                    <Select id="lease-currency" value={leaseForm.currency} onChange={(event) => setLeaseForm((current) => ({ ...current, currency: event.target.value as 'USD' | 'CDF' }))} options={[{ value: 'USD', label: 'USD' }, { value: 'CDF', label: 'CDF' }]} />
                </FormField>
                <FormField label="Notes" htmlFor="lease-notes" error={leaseFormErrors.notes}>
                    <Textarea id="lease-notes" value={leaseForm.notes} onChange={(event) => setLeaseForm((current) => ({ ...current, notes: event.target.value }))} rows={3} fullWidth maxLength={1000} />
                </FormField>
            </form>
        </Modal>
        {currentOrganization?.uuid && documentsTarget?.leaseUuid && (
            <PrivateDocumentsModal
                open
                onClose={() => setDocumentsTarget(null)}
                organizationUuid={currentOrganization.uuid}
                relatedType="lease"
                relatedUuid={documentsTarget.leaseUuid}
                cityUuid={documentsTarget.cityUuid ?? undefined}
                canDelete={canDo(organizationRole, 'delete_document')}
            />
        )}
        <ResidentialHistoryModal
            open={historyTarget !== null}
            tenant={historyTarget ? { id: historyTarget.id, name: historyTarget.name } : null}
            units={data?.units ?? []}
            leases={data?.leases ?? []}
            tenants={allRows.map(({ id, name }) => ({ id, name }))}
            canRecord={!localOperationsReadOnly && canDo(organizationRole, 'update_tenant')}
            onReload={reload}
            onClose={() => setHistoryTarget(null)}
        />
    </main>;
}
