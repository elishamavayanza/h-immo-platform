import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { Spinner } from '../../../../components/UI/Spinner';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
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
import { PrivateDocumentsModal } from '../components/PrivateDocumentsModal';
import { useAssignedCities } from '../../hooks/useAssignedCities';
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
    const {
        organizationRole, cities, activeCity, cityLoading, cityUuid, selectCity,
        from, setFrom, to, setTo, data, movements, totals, loading, error, reload,
    } = useTreasury();
    const [search, setSearch] = useState('');
    const [direction, setDirection] = useState<'all' | FinancialMovementKind>('all');
    const [method, setMethod] = useState('all');
    const [typeFilter, setTypeFilter] = useState('all');
    const [documentsMovement, setDocumentsMovement] = useState<FinancialMovement | null>(null);
    const [cashCityUuid, setCashCityUuid] = useState('');
    const [cashCurrency, setCashCurrency] = useState('USD');
    const [cashSnapshot, setCashSnapshot] = useState<CashBalanceSnapshot | null>(null);
    const [openingAmount, setOpeningAmount] = useState('');
    const [openingDate, setOpeningDate] = useState(() => new Date().toISOString().slice(0, 10));
    const [cashLoading, setCashLoading] = useState(false);
    const [cashSaving, setCashSaving] = useState(false);
    const [cashError, setCashError] = useState<string | null>(null);
    const cashCities = organizationRole === 'admin_ville' ? cities : assigned.cities.map(({ id, name }) => ({ id, name }));

    useEffect(() => {
        if (organizationRole === 'admin_ville' && cityUuid) setCashCityUuid(cityUuid);
        else if (!cashCityUuid && cashCities.length) setCashCityUuid(cashCities[0].id);
    }, [cashCityUuid, cashCities, cityUuid, organizationRole]);

    useEffect(() => {
        if (!currentOrganization?.uuid || !cashCityUuid) { setCashSnapshot(null); return; }
        let active = true;
        setCashLoading(true);
        setCashError(null);
        void fetchCashBalance(currentOrganization.uuid, cashCityUuid, cashCurrency)
            .then((snapshot) => { if (active) { setCashSnapshot(snapshot); setOpeningAmount(snapshot.openingBalance ?? ''); setOpeningDate(snapshot.openingDate ?? new Date().toISOString().slice(0, 10)); } })
            .catch((cause: unknown) => { if (active) setCashError(cause instanceof Error ? cause.message : 'Impossible de charger le solde de caisse.'); })
            .finally(() => { if (active) setCashLoading(false); });
        return () => { active = false; };
    }, [currentOrganization?.uuid, cashCityUuid, cashCurrency]);

    const saveOpening = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
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

    const columns: DataTableColumn<FinancialMovement>[] = [
        { key: 'date', title: 'Date', sortable: true, render: (row) => formatUserDate(row.date, true) },
        { key: 'kind', title: 'Flux', sortable: true, render: (row) => <span className={`treasury-direction treasury-direction--${row.kind}`}>{row.kind === 'income' ? 'Entrée' : 'Sortie'}</span> },
        { key: 'type', title: 'Type', sortable: true, render: (row) => <span>{row.type}{row.isReversed && <small className="treasury-table__note">Contre-passée</small>}</span> },
        { key: 'detail', title: 'Détail / référence', sortable: true },
        { key: 'method', title: 'Mode', sortable: true, render: (row) => formatPaymentMethod(row.method) },
        { key: 'city', title: 'Ville', sortable: true, render: (row) => row.city || '—' },
        { key: 'amount', title: 'Montant', sortable: true, render: (row) => <strong className={row.amount.startsWith('-') ? 'treasury-amount treasury-amount--negative' : 'treasury-amount treasury-amount--positive'}>{formatMoney(row.amount, row.currency)}</strong> },
        { key: 'documents', title: 'Pièces', render: (row) => <Button type="button" variant="secondary" onClick={() => setDocumentsMovement(row)}>Documents</Button> },
    ];

    const readOnly = organizationRole === 'patron';
    const canManagePayments = !readOnly && canDo(organizationRole, 'create_payment');
    const canManageExpenses = !readOnly && canDo(organizationRole, 'create_expense');
    const noCity = organizationRole === 'admin_ville' && !cityLoading && !cityUuid;

    return <main className="organization-page organization-operational-page treasury-page">
        {documentsMovement && currentOrganization?.uuid && <PrivateDocumentsModal open onClose={() => setDocumentsMovement(null)} organizationUuid={currentOrganization.uuid} relatedType={documentsMovement.relatedType} relatedUuid={documentsMovement.relatedUuid} cityUuid={documentsMovement.cityUuid} canDelete={organizationRole !== 'admin_ville'} />}
        <header className="organization-page__header treasury-page__header">
            <div><span className="organization-page__eyebrow">FINANCES</span><h1>Trésorerie</h1><p>Suivez les loyers encaissés, les dépenses réglées et les mouvements enregistrés sur votre périmètre.</p></div>
            <div className="treasury-page__actions">
                <Link className="treasury-page__link" to="/app/location/loyers">{canManagePayments ? 'Gérer les loyers' : 'Consulter les loyers'}</Link>
                <Link className="treasury-page__link treasury-page__link--secondary" to="/app/depenses/gestion">{canManageExpenses ? 'Gérer les dépenses' : 'Consulter les dépenses'}</Link>
            </div>
        </header>

        <OrganizationSummary items={[
            { label: 'Entrées nettes enregistrées', value: amountsLabel(totals.income) },
            { label: 'Dépenses nettes enregistrées', value: amountsLabel(totals.expenses) },
            { label: 'Résultat net des mouvements', value: amountsLabel(totals.net) },
        ]} />

        <Card className="treasury-cash-note" padding="small">
            <div><strong>Solde de caisse</strong><p>Solde d’ouverture augmenté des encaissements et diminué des dépenses en espèces depuis cette date.</p></div>
            <div className="treasury-cash-note__controls">
                <Select aria-label="Ville pour le solde de caisse" value={cashCityUuid} onChange={(event) => setCashCityUuid(event.target.value)} options={cashCities.map((city) => ({ value: city.id, label: city.name }))} />
                <Select aria-label="Devise du solde de caisse" value={cashCurrency} onChange={(event) => setCashCurrency(event.target.value)} options={[{ value: 'USD', label: 'USD' }, { value: 'CDF', label: 'CDF' }]} />
            </div>
            <strong className="treasury-cash-note__balance">{cashLoading ? 'Chargement…' : cashSnapshot?.balance !== null && cashSnapshot?.balance !== undefined ? formatMoney(cashSnapshot.balance, cashCurrency) : 'Solde à initialiser'}</strong>
            {cashError && <p role="alert">{cashError}</p>}
            <form className="treasury-cash-note__form" onSubmit={(event) => void saveOpening(event)}>
                <label>Solde au démarrage<input aria-label="Montant du solde d’ouverture" inputMode="decimal" required pattern="\d{1,10}(\.\d{1,2})?" value={openingAmount} onChange={(event) => setOpeningAmount(event.target.value)} placeholder="0,00" /></label>
                <label>Date du solde d’ouverture<input aria-label="Date du solde d’ouverture" type="date" required max={new Date().toISOString().slice(0, 10)} value={openingDate} onChange={(event) => setOpeningDate(event.target.value)} /></label>
                <Button type="submit" disabled={cashSaving || cashLoading || !cashCityUuid}>{cashSaving ? 'Enregistrement…' : cashSnapshot?.openingBalance === null ? 'Initialiser la caisse' : 'Mettre à jour le solde'}</Button>
            </form>
            <p>Flux en espèces sur la période sélectionnée : {amountsLabel(totals.cash)}. Les devises restent séparées.</p>
        </Card>

        <Card className="organization-table-card treasury-table-card" padding="medium">
            <div className="organization-table-toolbar treasury-toolbar">
                <div><h2>Mouvements financiers</h2><p>{data?.note ?? 'Les contre-écritures sont incluses avec leur signe. Chaque devise est totalisée séparément.'}</p></div>
                <div className="treasury-filters">
                    <label>Date de début <input type="date" value={from} max={to} onChange={(event) => setFrom(event.target.value)} /></label>
                    <label>Date de fin <input type="date" value={to} min={from} onChange={(event) => setTo(event.target.value)} /></label>
                    {organizationRole === 'admin_ville' && cities.length > 1 && <Select aria-label="Ville" value={cityUuid ?? ''} onChange={(event) => selectCity(event.target.value)} options={cities.map((city) => ({ value: city.id, label: city.name }))} />}
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
                            : <DataTable columns={columns} data={rows} pageSize={12} initialSortKey="date" />}
        </Card>
    </main>;
}
