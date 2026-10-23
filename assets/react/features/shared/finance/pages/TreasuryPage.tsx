import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { Spinner } from '../../../../components/UI/Spinner';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { DatePicker } from '../../../../components/Forms/DatePicker';
import { Select } from '../../../../components/Forms/Select';
import { ConfirmDialog } from '../../../../components/UI/ConfirmDialog/ConfirmDialog';
import { OrganizationSummary } from '../../components/OrganizationSummary';
import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { formatMoney } from '../../../../../utils/format.utils';
import { formatUserDate } from '../../../../services/userPreferences';
import { canDo } from '../../permissions';
import { fetchCashBalance, formatPaymentMethod, saveCashOpeningBalance, type CashBalanceSnapshot } from '../services/treasuryService';
import { useTreasury } from '../hooks/useTreasury';
import type { FinancialMovement, FinancialMovementKind } from '../types/finance.types';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { localDateInputValue } from '../../../../utils/dateUtils';
import { PrivateDocumentsModal } from '../components/PrivateDocumentsModal';
import { MovementOriginModal, type MovementOrigin } from '../components/MovementOriginModal';
import { useAssignedCities } from '../../hooks/useAssignedCities';
import { requiresCashOpeningConfirmation, resolveCashCityUuid } from '../utils/treasuryUi';
import '../../../../../styles/pages/shared/finance/_treasury.scss';

const METHOD_OPTIONS = [{ value: 'cash', label: 'Espèces' }, { value: 'bank_transfer', label: 'Virement bancaire' }, { value: 'mobile_money', label: 'Mobile Money' }, { value: 'card', label: 'Carte' }, { value: 'check', label: 'Chèque' }, { value: 'other', label: 'Autre' }];

function amountsLabel(amounts: Map<string, string>): string {
    if (amounts.size === 0) return '0';
    return [...amounts.entries()]
        .sort(([currencyA], [currencyB]) => currencyA.localeCompare(currencyB))
        .map(([currency, amount]) => formatMoney(amount, currency))
        .join(' · ');
}

export function TreasuryPage() {
    const { currentOrganization } = useOrganization();
    const assigned = useAssignedCities();
    const [movementCityUuid, setMovementCityUuid] = useState('');
    const {
        organizationRole, cities, activeCity, cityLoading, cityUuid, selectCity,
        from, setFrom, to, setTo, data, movements, totals, loading, error, reload,
    } = useTreasury(movementCityUuid);
    const [search, setSearch] = useState('');
    const [direction, setDirection] = useState<'all' | FinancialMovementKind>('all');
    const [method, setMethod] = useState('all');
    const [typeFilter, setTypeFilter] = useState('all');
    const [mobilePage, setMobilePage] = useState(1);
    const [documentsMovement, setDocumentsMovement] = useState<FinancialMovement | null>(null);
    const [movementOrigin, setMovementOrigin] = useState<MovementOrigin | null>(null);
    const [cashCityUuid, setCashCityUuid] = useState('');
    const [cashCurrency, setCashCurrency] = useState('USD');
    const [cashSnapshot, setCashSnapshot] = useState<CashBalanceSnapshot | null>(null);
    const [openingAmount, setOpeningAmount] = useState('');
    const [openingDate, setOpeningDate] = useState(() => localDateInputValue());
    const [cashLoading, setCashLoading] = useState(false);
    const [cashSaving, setCashSaving] = useState(false);
    const [cashError, setCashError] = useState<string | null>(null);
    const [cashConfirmationOpen, setCashConfirmationOpen] = useState(false);
    const cashCities = organizationRole === 'admin_ville' ? cities : assigned.cities.map(({ id, name }) => ({ id, name }));
    const canFilterMovementCity = organizationRole === 'patron' || organizationRole === 'admin_immobilier';
    const movementCities = canFilterMovementCity ? assigned.cities : [];
    const selectedCashCityName = cashCities.find((city) => city.id === cashCityUuid)?.name ?? 'Ville indisponible';
    const cashContextKey = `${currentOrganization?.uuid ?? ''}:${organizationRole ?? ''}:${cashCityUuid}:${cashCurrency}`;
    const cashContextKeyRef = useRef(cashContextKey);
    const confirmationContextRef = useRef<string | null>(null);
    cashContextKeyRef.current = cashContextKey;
    const canManageCashBalance = canDo(organizationRole, 'manage_treasury_balance');
    const canUploadDocuments = canDo(organizationRole, 'create_document');
    const canDeleteDocuments = canDo(organizationRole, 'delete_document');
    const cashBalanceLabel = cashLoading
        ? 'Chargement…'
        : cashError
            ? 'Solde indisponible'
            : !cashCityUuid
                ? 'Aucune ville disponible'
                : cashSnapshot?.balance !== null && cashSnapshot?.balance !== undefined
                    ? formatMoney(cashSnapshot.balance, cashCurrency)
                    : canManageCashBalance
                        ? 'Solde à initialiser'
                        : 'Solde d’ouverture non configuré';

    useEffect(() => {
        const nextCityUuid = resolveCashCityUuid(organizationRole, cashCityUuid, cashCities, cityUuid);
        if (cashCityUuid !== nextCityUuid) setCashCityUuid(nextCityUuid);
    }, [cashCityUuid, cashCities, cityUuid, organizationRole]);

    useEffect(() => {
        setMovementCityUuid('');
    }, [currentOrganization?.uuid]);

    useEffect(() => {
        if (movementCityUuid && !movementCities.some((city) => city.id === movementCityUuid)) {
            setMovementCityUuid('');
        }
    }, [movementCityUuid, movementCities]);

    useEffect(() => {
        setCashConfirmationOpen(false);
        confirmationContextRef.current = null;
    }, [currentOrganization?.uuid, organizationRole, cashCityUuid, cashCurrency]);

    useEffect(() => {
        setCashSnapshot(null);
        setOpeningAmount('');
        setOpeningDate(localDateInputValue());
        setCashError(null);
        if (!currentOrganization?.uuid || !cashCityUuid) { setCashLoading(false); return; }
        let active = true;
        setCashLoading(true);
        void fetchCashBalance(currentOrganization.uuid, cashCityUuid, cashCurrency)
            .then((snapshot) => { if (active) { setCashSnapshot(snapshot); setOpeningAmount(snapshot.openingBalance ?? ''); setOpeningDate(snapshot.openingDate ?? localDateInputValue()); } })
            .catch((cause: unknown) => { if (active) setCashError(cause instanceof Error ? cause.message : 'Impossible de charger le solde de caisse.'); })
            .finally(() => { if (active) setCashLoading(false); });
        return () => { active = false; };
    }, [currentOrganization?.uuid, cashCityUuid, cashCurrency]);

    const persistOpening = async () => {
        if (!currentOrganization?.uuid || !cashCityUuid || !/^\d{1,10}(?:\.\d{1,2})?$/.test(openingAmount) || !openingDate) return;
        setCashSaving(true);
        setCashError(null);
        try {
            const snapshot = await saveCashOpeningBalance(currentOrganization.uuid, cashCityUuid, { currency: cashCurrency, openingBalance: openingAmount, openingDate });
            setCashSnapshot(snapshot);
        } catch (cause) {
            setCashError(cause instanceof Error ? cause.message : 'Impossible d’enregistrer le solde d’ouverture.');
        } finally {
            setCashSaving(false);
        }
    };

    const submitOpening = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!currentOrganization?.uuid || !cashCityUuid || !/^\d{1,10}(?:\.\d{1,2})?$/.test(openingAmount) || !openingDate) return;
        if (requiresCashOpeningConfirmation(cashSnapshot?.openingBalance)) {
            confirmationContextRef.current = cashContextKey;
            setCashConfirmationOpen(true);
            return;
        }
        void persistOpening();
    };

    const categories = useMemo(() => [...new Set(movements.map((row) => row.type))].sort((a, b) => a.localeCompare(b, 'fr')), [movements]);
    const rows = useMemo(() => {
        const query = search.trim().toLocaleLowerCase('fr');
        return movements.filter((row) =>
            (direction === 'all' || row.kind === direction)
            && (method === 'all' || row.method === method)
            && (typeFilter === 'all' || row.type === typeFilter)
            && (!query || [row.type, row.detail, row.city, row.currency, formatPaymentMethod(row.method)].some((value) => value.toLocaleLowerCase('fr').includes(query))),
        );
    }, [movements, direction, method, typeFilter, search]);
    const mobilePageSize = 12;
    const mobilePageCount = Math.max(1, Math.ceil(rows.length / mobilePageSize));
    const mobileRows = rows.slice((mobilePage - 1) * mobilePageSize, mobilePage * mobilePageSize);

    useEffect(() => { setMobilePage(1); }, [search, direction, method, typeFilter, from, to, cityUuid]);
    useEffect(() => { setMobilePage((page) => Math.min(page, mobilePageCount)); }, [mobilePageCount]);

    const columns: DataTableColumn<FinancialMovement>[] = [
        { key: 'date', title: 'Date', sortable: true, render: (row) => formatUserDate(row.date, true) },
        { key: 'kind', title: 'Flux', sortable: true, render: (row) => <span className={`treasury-direction treasury-direction--${row.kind}`}>{row.kind === 'income' ? 'Entrée' : 'Sortie'}</span> },
        { key: 'type', title: 'Type', sortable: true, render: (row) => <span>{row.type}{row.isReversed && <small className="treasury-table__note">Contre-passée</small>}</span> },
        { key: 'detail', title: 'Détail / référence', sortable: true, render: (row) => <div className="treasury-movement-reference">{row.detail}{row.isReversal && row.reversalOfUuid && <Button type="button" variant="secondary" onClick={() => setMovementOrigin({ type: row.relatedType, uuid: row.reversalOfUuid! })}>Voir l’opération d’origine</Button>}</div> },
        { key: 'method', title: 'Mode', sortable: true, render: (row) => formatPaymentMethod(row.method) },
        { key: 'city', title: 'Ville', sortable: true, render: (row) => row.city || '—' },
        { key: 'amount', title: 'Montant', sortable: true, render: (row) => <strong className={row.amount.startsWith('-') ? 'treasury-amount treasury-amount--negative' : 'treasury-amount treasury-amount--positive'}>{formatMoney(row.amount, row.currency)}</strong> },
        { key: 'documents', title: 'Pièces', render: (row) => <Button type="button" variant="secondary" onClick={() => setDocumentsMovement(row)}>Documents</Button> },
    ];

    const canManagePayments = canDo(organizationRole, 'create_payment');
    const canManageExpenses = canDo(organizationRole, 'create_expense');
    const noCity = organizationRole === 'admin_ville' && !cityLoading && !cityUuid;
    const selectedMovementCityName = movementCities.find((city) => city.id === movementCityUuid)?.name;
    const scopeLabel = organizationRole === 'admin_ville'
        ? activeCity?.name ?? 'Ville autorisée'
        : selectedMovementCityName ?? 'Toutes les villes accessibles de l’organisation';
    const openDocuments = (movement: FinancialMovement) => setDocumentsMovement(movement);

    return <main className="organization-page organization-operational-page treasury-page">
        {documentsMovement && currentOrganization?.uuid && <PrivateDocumentsModal open onClose={() => setDocumentsMovement(null)} organizationUuid={currentOrganization.uuid} relatedType={documentsMovement.relatedType} relatedUuid={documentsMovement.relatedUuid} cityUuid={documentsMovement.cityUuid} canUpload={canUploadDocuments} canDelete={canDeleteDocuments} />}
        <MovementOriginModal origin={movementOrigin} onClose={() => setMovementOrigin(null)} />
        <ConfirmDialog
            isOpen={cashConfirmationOpen}
            onClose={() => setCashConfirmationOpen(false)}
            onConfirm={() => {
                if (confirmationContextRef.current !== cashContextKeyRef.current) {
                    setCashError('Le contexte de caisse a changé. Vérifiez la ville et la devise avant de réessayer.');
                    setCashConfirmationOpen(false);
                    return;
                }
                confirmationContextRef.current = null;
                setCashConfirmationOpen(false);
                void persistOpening();
            }}
            title="Confirmer la modification du solde d’ouverture"
            confirmLabel="Confirmer la modification"
            message={<div className="treasury-confirmation">
                <p>Cette modification recalculera le solde de caisse à partir de la date indiquée.</p>
                <dl>
                    <div><dt>Organisation</dt><dd>{currentOrganization?.name ?? 'Organisation active'}</dd></div>
                    <div><dt>Ville</dt><dd>{selectedCashCityName}</dd></div>
                    <div><dt>Devise</dt><dd>{cashCurrency}</dd></div>
                    <div><dt>Ancienne date d’effet</dt><dd>{cashSnapshot?.openingDate ? formatUserDate(cashSnapshot.openingDate) : '—'}</dd></div>
                    <div><dt>Nouvelle date d’effet</dt><dd>{formatUserDate(openingDate)}</dd></div>
                    <div><dt>Ancien solde d’ouverture</dt><dd>{formatMoney(cashSnapshot?.openingBalance ?? '0', cashCurrency)}</dd></div>
                    <div><dt>Nouveau solde d’ouverture</dt><dd>{formatMoney(openingAmount, cashCurrency)}</dd></div>
                </dl>
            </div>}
        />
        <header className="organization-page__header treasury-page__header">
            <div><span className="organization-page__eyebrow">FINANCES</span><h1>Trésorerie</h1><p>Suivez les loyers encaissés, les dépenses réglées et les mouvements enregistrés.</p><p className="treasury-page__scope">{currentOrganization?.name ?? 'Organisation active'} · {scopeLabel} · Du {formatUserDate(from)} au {formatUserDate(to)}</p></div>
            <div className="treasury-page__actions">
                <Link className="treasury-page__link" to="/app/location/loyers">{canManagePayments ? 'Gérer les loyers' : 'Consulter les loyers'}</Link>
                <Link className="treasury-page__link treasury-page__link--secondary" to="/app/depenses/gestion">{canManageExpenses ? 'Gérer les dépenses' : 'Consulter les dépenses'}</Link>
            </div>
        </header>

        <OrganizationSummary items={[
            { label: 'Entrées nettes enregistrées', value: error ? 'Indisponible' : loading ? 'Chargement…' : amountsLabel(totals.income) },
            { label: 'Sorties nettes enregistrées', value: error ? 'Indisponible' : loading ? 'Chargement…' : amountsLabel(totals.expenses) },
            { label: 'Résultat net des mouvements', value: error ? 'Indisponible' : loading ? 'Chargement…' : amountsLabel(totals.net) },
        ]} />

        <Card className="treasury-cash-note" padding="small">
            <div><strong>Solde de caisse</strong><p>Solde d’ouverture augmenté des encaissements et diminué des dépenses en espèces depuis cette date.</p></div>
            <div className="treasury-cash-note__controls">
                <Select aria-label="Ville pour le solde de caisse" value={cashCityUuid} onChange={(event) => setCashCityUuid(event.target.value)} options={cashCities.map((city) => ({ value: city.id, label: city.name }))} />
                <Select aria-label="Devise du solde de caisse" value={cashCurrency} onChange={(event) => setCashCurrency(event.target.value)} options={[{ value: 'USD', label: 'USD' }, { value: 'CDF', label: 'CDF' }]} />
            </div>
            <strong className="treasury-cash-note__balance">{cashBalanceLabel}</strong>
            {cashError && <p role="alert">{cashError}</p>}
            {!cashLoading && !cashError && cashCityUuid && cashSnapshot?.balance === null && <p className="treasury-cash-note__status">
                {canManageCashBalance
                    ? 'Définissez le solde d’ouverture pour calculer la caisse à partir de cette date.'
                    : 'Aucun solde d’ouverture n’est défini pour cette ville et cette devise. Demandez à un ADMIN_VILLE autorisé de l’initialiser.'}
            </p>}
            {canManageCashBalance && <form className="treasury-cash-note__form" onSubmit={submitOpening}>
                <label>Solde au démarrage<input aria-label="Montant du solde d’ouverture" inputMode="decimal" required pattern="\d{1,10}(\.\d{1,2})?" value={openingAmount} onChange={(event) => setOpeningAmount(event.target.value)} placeholder="Ex. : 250.00" /></label>
                <label>Date du solde d’ouverture<DatePicker aria-label="Date du solde d’ouverture" required max={localDateInputValue()} value={openingDate} onChange={(event) => setOpeningDate(event.target.value)} fullWidth /></label>
                <Button className="treasury-cash-note__submit" type="submit" disabled={cashSaving || cashLoading || !cashCityUuid}>{cashSaving ? 'Enregistrement…' : cashSnapshot?.openingBalance === null ? 'Initialiser la caisse' : 'Mettre à jour le solde'}</Button>
            </form>}
            <p>Flux en espèces sur la période sélectionnée : {amountsLabel(totals.cash)}. Les devises restent séparées.</p>
        </Card>

        <Card className="organization-table-card treasury-table-card" padding="medium">
            <div className="organization-table-toolbar treasury-toolbar">
                <div><h2>Mouvements financiers</h2><p>{data?.note ?? (data ? `Chargement complet : ${data.paymentTotal + data.expenseTotal} opérations. Les contre-écritures sont présentées dans le sens de leur impact; les devises restent séparées.` : 'Les contre-écritures sont présentées dans le sens de leur impact; les devises restent séparées.')}</p></div>
                <div className="treasury-filters">
                    <label>Date de début <DatePicker value={from} max={to} onChange={(event) => setFrom(event.target.value)} fullWidth /></label>
                    <label>Date de fin <DatePicker value={to} min={from} onChange={(event) => setTo(event.target.value)} fullWidth /></label>
                    {organizationRole === 'admin_ville' && cities.length > 1 && <Select aria-label="Ville" value={cityUuid ?? ''} onChange={(event) => selectCity(event.target.value)} options={cities.map((city) => ({ value: city.id, label: city.name }))} />}
                    {canFilterMovementCity && movementCities.length > 1 && <Select aria-label="Filtrer les mouvements par ville" value={movementCityUuid} onChange={(event) => setMovementCityUuid(event.target.value)} options={[{ value: '', label: 'Toutes les villes' }, ...movementCities.map((city) => ({ value: city.id, label: city.name }))]} />}
                    <SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un mouvement…" fullWidth />
                    <Select aria-label="Type de flux" value={direction} onChange={(event) => setDirection(event.target.value as 'all' | FinancialMovementKind)} options={[{ value: 'all', label: 'Entrées et sorties' }, { value: 'income', label: 'Entrées' }, { value: 'expense', label: 'Sorties' }]} />
                    <Select aria-label="Mode de règlement" value={method} onChange={(event) => setMethod(event.target.value)} options={[{ value: 'all', label: 'Tous les modes' }, ...METHOD_OPTIONS]} />
                    <Select aria-label="Type d’opération" value={typeFilter} onChange={(event) => setTypeFilter(event.target.value)} options={[{ value: 'all', label: 'Tous les types' }, ...categories.map((value) => ({ value, label: value }))]} />
                </div>
            </div>

            {loading || cityLoading ? <EmptyState icon={<Spinner size="large" />} title="Chargement des mouvements…" description="Récupération des entrées et sorties de la période." />
                : error ? <EmptyState title="Trésorerie indisponible" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />
                    : noCity ? <EmptyState title="Aucune ville disponible" description="Aucune ville autorisée n’est associée à votre compte." />
                        : rows.length === 0 ? <EmptyState title="Aucun mouvement à afficher" description={movements.length ? 'Aucune opération ne correspond aux filtres.' : 'Aucune entrée ni sortie n’a été enregistrée sur cette période.'} />
                : <>
                    <div className="treasury-movement-cards" aria-label="Mouvements financiers">
                        {mobileRows.map((row) => <article className="treasury-movement-card" key={row.id}>
                            <div className="treasury-movement-card__top">
                                <span>{formatUserDate(row.date, true)}</span>
                                <strong className={row.amount.startsWith('-') ? 'treasury-amount treasury-amount--negative' : 'treasury-amount treasury-amount--positive'}>{formatMoney(row.amount, row.currency)}</strong>
                            </div>
                            <div className="treasury-movement-card__title">
                                <span className={`treasury-direction treasury-direction--${row.kind}`}>{row.kind === 'income' ? 'Entrée' : 'Sortie'}</span>
                                <strong>{row.type}</strong>
                                {row.isReversed && <small>Contre-passée</small>}
                            </div>
                            <dl>
                                <div><dt>Détail</dt><dd>{row.detail || '—'}</dd></div>
                                <div><dt>Ville</dt><dd>{row.city || '—'}</dd></div>
                                <div><dt>Mode</dt><dd>{formatPaymentMethod(row.method)}</dd></div>
                            </dl>
                            {row.isReversal && row.reversalOfUuid && <Button type="button" variant="secondary" onClick={() => setMovementOrigin({ type: row.relatedType, uuid: row.reversalOfUuid! })}>Voir l’opération d’origine</Button>}
                            <Button type="button" variant="secondary" onClick={() => openDocuments(row)}>Pièces justificatives</Button>
                        </article>)}
                    </div>
                    {mobilePageCount > 1 && <nav className="treasury-mobile-pagination" aria-label="Pagination des mouvements">
                        <Button type="button" variant="secondary" disabled={mobilePage <= 1} onClick={() => setMobilePage((page) => Math.max(1, page - 1))}>Précédent</Button>
                        <span>Page {mobilePage} sur {mobilePageCount}</span>
                        <Button type="button" variant="secondary" disabled={mobilePage >= mobilePageCount} onClick={() => setMobilePage((page) => Math.min(mobilePageCount, page + 1))}>Suivant</Button>
                    </nav>}
                    <div className="treasury-movement-table"><DataTable columns={columns} data={rows} pageSize={12} initialSortKey="date" /></div>
                </>}
        </Card>
    </main>;
}
