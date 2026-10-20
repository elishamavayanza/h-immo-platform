import { useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Button } from '../../../../../components/UI/Button';
import { Card } from '../../../../../components/UI/Card';
import { Modal } from '../../../../../components/UI/Modal/Modal';
import { FormField } from '../../../../../components/Forms/FormField';
import { Input } from '../../../../../components/Forms/Input';
import { Select } from '../../../../../components/Forms/Select';
import { RightSidebar } from '../../../../../components/Navigation/RightSidebar';
import { Calendar } from '../../../../../components/Calendars/Calendar';
import { CalendarRailPopover } from '../../../components/CalendarRailPopover';
import { NavigationRailPopover } from '../../../components/NavigationRailPopover';
import { useAuth } from '../../../../../app/providers/AuthProvider';
import { useOrganization } from '../../../../../app/providers/OrganizationProvider';
import { useToast } from '../../../../../app/layout/MainLayout/contexts/ToastContext';
import { apiClient } from '../../../../../../services/api/client';
import { ApiError, type Feedback } from '../../../../../../services/api/api.types';
import { useAccountSettings } from '../hooks/useAccountSettings';
import { usePwaInstallation } from '../hooks/usePwaInstallation';
import { OrganizationLogoPicker, type OrganizationLogoChange } from '../../../../super_admin/organizations/components/OrganizationLogoPicker';
import { organizationsService } from '../../../../super_admin/organizations/services/organizationsService';
import { RentalRulesSettings } from '../components/RentalRulesSettings';
import './settings.scss';

type FxRate = { baseCurrency: string; quoteCurrency: string; rate: string; effectiveFrom: string };
type FxCity = { uuid: string; name: string; rate: FxRate | null };
type FxAdmin = { uuid: string; name: string; allowed: boolean; cities: Array<{ uuid: string; name: string }> };
type HistoricalRate = { scope: string; source?: string; rate: FxRate | null };
type OrganizationCapacity = { limits: Record<'cities' | 'parcels' | 'buildings' | 'units' | 'members', number | null>; usage: Record<'cities' | 'parcels' | 'buildings' | 'units' | 'members', number>; showcaseEnabled: boolean };

const localDateKey = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

export function SettingsPage() {
    const location = useLocation();
    const isOrganizationPage = location.pathname === '/settings/organisation';
    const { user } = useAuth();
    const { currentOrganization, organizationRole } = useOrganization();
    const { push } = useToast();
    const { settings, update, isLoading, error, reload } = useAccountSettings();
    const pwaInstallation = usePwaInstallation();
    const [installingPwa, setInstallingPwa] = useState(false);
    const [installHelpOpen, setInstallHelpOpen] = useState(false);
    const [retentionDraft, setRetentionDraft] = useState<string | null>(null);
    const [retentionError, setRetentionError] = useState<string | null>(null);
    const isSuperAdmin = user?.platformRole === 'super_admin';
    const [fxSettings, setFxSettings] = useState<{scope: string; organizationCurrency: 'USD' | 'CDF'; organizationRate: FxRate | null; cityAdmins: FxAdmin[]; enabled?: boolean; cities?: FxCity[]} | null>(null);
    const [fxLoading, setFxLoading] = useState(false);
    const [fxError, setFxError] = useState<string | null>(null);
    const [fxBusy, setFxBusy] = useState(false);
    const [fxRateDraft, setFxRateDraft] = useState('');
    const [organizationCurrencyDraft, setOrganizationCurrencyDraft] = useState<'USD' | 'CDF'>('CDF');
    const [activeSection, setActiveSection] = useState('');
    const [historyDate, setHistoryDate] = useState(() => localDateKey(new Date()));
    const [historicalRates, setHistoricalRates] = useState<HistoricalRate[]>([]);
    const [historyLoading, setHistoryLoading] = useState(false);
    const [organizationLogo, setOrganizationLogo] = useState<string | null>(null);
    const [organizationLogoChange, setOrganizationLogoChange] = useState<OrganizationLogoChange>(null);
    const [logoLoading, setLogoLoading] = useState(false);
    const [logoBusy, setLogoBusy] = useState(false);
    const [logoError, setLogoError] = useState<string | null>(null);
    const [capacity, setCapacity] = useState<OrganizationCapacity | null>(null);
    const [capacityError, setCapacityError] = useState<string | null>(null);
    const [capacityLoading, setCapacityLoading] = useState(false);

    useEffect(() => {
        if (!isOrganizationPage || !currentOrganization || !['patron', 'admin_immobilier'].includes(organizationRole ?? '')) { setCapacity(null); setCapacityError(null); return; }
        let active = true;
        setCapacityLoading(true);
        setCapacityError(null);
        void apiClient.get<Feedback<OrganizationCapacity>>(`/v1/identity/organizations/${currentOrganization.uuid}/capacity`)
            .then(({ data }) => { if (active) setCapacity(data.data); })
            .catch((cause: unknown) => { if (active) setCapacityError(cause instanceof ApiError ? cause.message : 'Impossible de charger les capacités de l’organisation.'); })
            .finally(() => { if (active) setCapacityLoading(false); });
        return () => { active = false; };
    }, [currentOrganization, isOrganizationPage, organizationRole]);

    useEffect(() => {
        if (!isOrganizationPage || organizationRole !== 'admin_immobilier' || !currentOrganization) return;
        let active = true;
        setOrganizationLogo(null);
        setOrganizationLogoChange(null);
        setLogoLoading(true);
        setLogoError(null);
        void apiClient.get<Feedback<{ id: string; logo: string | null }>>(`/v1/identity/organizations/${currentOrganization.uuid}`)
            .then(({ data }) => { if (active) setOrganizationLogo(data.data.logo); })
            .catch((cause: unknown) => {
                if (!active) return;
                setLogoError(cause instanceof ApiError ? cause.message : 'Impossible de charger le logo de l’organisation.');
            })
            .finally(() => { if (active) setLogoLoading(false); });
        return () => { active = false; };
    }, [isOrganizationPage, organizationRole, currentOrganization]);

    useEffect(() => {
        const content = document.getElementById('main-layout__content');
        const sections = Array.from(document.querySelectorAll<HTMLElement>('.settings-section[id]'));
        if (!content || sections.length === 0) return;
        const observer = new IntersectionObserver((entries) => {
            const visible = entries.filter((entry) => entry.isIntersecting)
                .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
            if (visible) setActiveSection((visible.target as HTMLElement).id);
        }, { root: content, rootMargin: '-12% 0px -72% 0px', threshold: 0 });
        sections.forEach((section) => observer.observe(section));
        return () => observer.disconnect();
    }, [isOrganizationPage, fxSettings, isLoading]);

    useEffect(() => {
        if (!currentOrganization || !['admin_immobilier', 'admin_ville'].includes(organizationRole ?? '')) { setFxSettings(null); setFxError(null); return; }
        let active = true;
        setFxLoading(true);
        setFxError(null);
        void apiClient.get<Feedback<NonNullable<typeof fxSettings>>>(`/v1/exchange-rates/organizations/${currentOrganization.uuid}/settings`)
            .then(({ data }) => { if (active) { setFxSettings(data.data); setOrganizationCurrencyDraft(data.data.organizationCurrency); } })
            .catch((cause: unknown) => {
                if (!active) return;
                setFxSettings(null);
                setFxError(cause instanceof ApiError ? cause.message : 'Impossible de charger les paramètres du taux de change.');
            })
            .finally(() => { if (active) setFxLoading(false); });
        return () => { active = false; };
    }, [currentOrganization, organizationRole]);

    useEffect(() => {
        if (!isOrganizationPage || !currentOrganization || !['admin_immobilier', 'admin_ville'].includes(organizationRole ?? '')) return;
        let active = true;
        setHistoryLoading(true);
        void apiClient.get<Feedback<{ rates: HistoricalRate[] }>>(`/v1/exchange-rates/organizations/${currentOrganization.uuid}/history?date=${historyDate}`)
            .then(({ data }) => { if (active) setHistoricalRates(data.data.rates); })
            .catch((cause: unknown) => {
                if (!active) return;
                setHistoricalRates([]);
                push('error', cause instanceof ApiError ? cause.message : 'Impossible de charger le taux applicable à cette date.');
            })
            .finally(() => { if (active) setHistoryLoading(false); });
        return () => { active = false; };
    }, [currentOrganization, historyDate, isOrganizationPage, organizationRole, push]);

    const saveFxRate = async (scope: 'organization' | FxCity) => {
        if (!currentOrganization || !fxRateDraft.trim()) return;
        setFxBusy(true);
        try {
            const endpoint = scope === 'organization'
                ? `/v1/exchange-rates/organizations/${currentOrganization.uuid}/organization`
                : `/v1/exchange-rates/cities/${scope.uuid}`;
            const { data } = await apiClient.put<Feedback<FxRate>>(endpoint, { baseCurrency: 'USD', quoteCurrency: 'CDF', rate: fxRateDraft.trim() });
            setFxRateDraft('');
            setFxSettings((previous) => previous ? scope === 'organization'
                ? { ...previous, organizationRate: data.data }
                : { ...previous, cities: previous.cities?.map((city) => city.uuid === scope.uuid ? { ...city, rate: data.data } : city) }
                : previous);
            setFxError(null);
            push('success', scope === 'organization' ? 'Le taux de change de l’organisation a été enregistré.' : `Le taux de change de ${scope.name} a été enregistré.`);
        } catch (cause) {
            const message = cause instanceof ApiError ? cause.message : 'Impossible d’enregistrer le taux de change.';
            setFxError(message);
            push('error', message);
        } finally { setFxBusy(false); }
    };

    const saveOrganizationCurrency = async () => {
        if (!currentOrganization || !fxSettings || organizationCurrencyDraft === fxSettings.organizationCurrency) return;
        setFxBusy(true);
        try {
            await apiClient.put(`/v1/exchange-rates/organizations/${currentOrganization.uuid}/currency`, { currency: organizationCurrencyDraft });
            setFxSettings((previous) => previous ? { ...previous, organizationCurrency: organizationCurrencyDraft } : previous);
            setFxError(null);
            push('success', 'La devise de référence de l’organisation a été mise à jour.');
        } catch (cause) {
            const message = cause instanceof ApiError ? cause.message : 'Impossible de modifier la devise de référence.';
            setFxError(message);
            push('error', message);
        } finally { setFxBusy(false); }
    };

    const saveOrganizationLogo = async () => {
        if (!currentOrganization || !organizationLogoChange) return;
        setLogoBusy(true);
        setLogoError(null);
        try {
            if (organizationLogoChange.kind === 'new') {
                const path = await organizationsService.uploadLogo(currentOrganization.uuid, organizationLogoChange.file);
                setOrganizationLogo(path);
                push('success', 'Le logo de l’organisation a été mis à jour.');
            } else {
                await organizationsService.deleteLogo(currentOrganization.uuid);
                setOrganizationLogo(null);
                push('success', 'Le logo de l’organisation a été retiré.');
            }
            setOrganizationLogoChange(null);
        } catch (cause) {
            const message = cause instanceof ApiError ? cause.message : 'Impossible d’enregistrer le logo de l’organisation.';
            setLogoError(message);
            push('error', message);
        } finally { setLogoBusy(false); }
    };

    const setFxPermission = async (admin: FxAdmin, allowed: boolean) => {
        if (!currentOrganization) return;
        setFxBusy(true);
        try {
            await apiClient.put(`/v1/exchange-rates/organizations/${currentOrganization.uuid}/city-permission`, { organizationUserUuid: admin.uuid, allowed });
            const { data } = await apiClient.get<Feedback<NonNullable<typeof fxSettings>>>(`/v1/exchange-rates/organizations/${currentOrganization.uuid}/settings`);
            setFxSettings(data.data);
            setFxError(null);
            push('success', `L’autorisation de gestion du taux a été ${allowed ? 'accordée' : 'retirée'} pour ${admin.name}.`);
        } catch (cause) {
            const message = cause instanceof ApiError ? cause.message : 'Impossible de modifier cette autorisation.';
            setFxError(message);
            push('error', message);
        } finally { setFxBusy(false); }
    };

    const apply = async (key: keyof typeof settings, value: unknown) => {
        await update(key, value);
    };

    const toggle = (key: keyof typeof settings) => {
        const value = !settings[key];
        void apply(key, value);
    };

    const installSoftImmo = async () => {
        setInstallingPwa(true);
        try {
            const outcome = await pwaInstallation.install();
            if (outcome === 'accepted') push('success', 'Soft-IMMO a été ajoutée à cet appareil.');
            else if (outcome === 'dismissed') push('info', 'Installation annulée. Vous pourrez la relancer depuis les paramètres.');
            else setInstallHelpOpen(true);
        } catch {
            setInstallHelpOpen(true);
        } finally {
            setInstallingPwa(false);
        }
    };

    const handleSelectChange = (key: keyof typeof settings) => (event: React.ChangeEvent<HTMLSelectElement>) => {
        const rawValue = event.target.value;
        const value = key === 'defaultPageSize' ? Number(rawValue) : rawValue;
        void apply(key, value);
    };

    const saveRetention = () => {
        if (retentionDraft === null) return;
        const value = Number(retentionDraft);
        if (!Number.isInteger(value) || value < 30 || value > 2555) {
            setRetentionError('Choisissez une durée entre 30 et 2555 jours.');
            return;
        }
        setRetentionError(null);
        void apply('auditLogRetentionDays', value);
        setRetentionDraft(null);
    };

    if (isLoading) return <div className="account-page__spinner"><span>Chargement des préférences…</span></div>;

    if (error) {
        return (
            <div className="settings-error">
                <p>Erreur : {error}</p>
                <Button variant="secondary" onClick={reload}>Réessayer</Button>
            </div>
        );
    }

    return (
        <main className="account-page settings-page">
            <header className="account-page__header">
                <div>
                    <span className="account-page__eyebrow">{isOrganizationPage ? 'ORGANISATION' : 'COMPTE'}</span>
                    <h1>{isOrganizationPage ? 'Paramètres de l’organisation' : 'Paramètres'}</h1>
                    <p>{isOrganizationPage ? 'Gérez les réglages partagés selon votre rôle.' : 'Personnalisez votre expérience H-Immo.'}</p>
                </div>
            </header>

            <div className="settings-page__sections">
                {isOrganizationPage && <RentalRulesSettings />}
                {isOrganizationPage && ['patron', 'admin_immobilier'].includes(organizationRole ?? '') && <section id="settings-organization-capacity" className="settings-section"><Card header="Offre et capacités" className="settings-card">
                    <p>Cette configuration est attribuée manuellement par l’administration de la plateforme. Pour la modifier, contactez votre SUPER_ADMIN. Aucun paiement en ligne n’est géré ici.</p>
                    {capacityLoading && <p role="status">Chargement des capacités…</p>}
                    {capacityError && <p className="settings-error" role="alert">{capacityError}</p>}
                    {capacity && <><div className="settings-capacity-grid">{([['cities', 'Villes'], ['parcels', 'Parcelles'], ['buildings', 'Bâtiments'], ['units', 'Unités'], ['members', 'Membres actifs']] as const).map(([key, label]) => <div className="settings-capacity-item" key={key}><strong>{label}</strong><span>{capacity.usage[key]} / {capacity.limits[key] ?? 'Sans limite'}</span>{capacity.limits[key] !== null && capacity.usage[key] > capacity.limits[key] && <small>Plafond dépassé : les données restent accessibles, mais les nouvelles créations sont bloquées.</small>}</div>)}</div><div className="settings-capacity-item settings-capacity-item--feature"><strong>Vitrine immobilière</strong><span>{capacity.showcaseEnabled ? 'Autorisée par la plateforme' : 'Non autorisée'}</span></div></>}
                </Card></section>}
                {isOrganizationPage && organizationRole === 'admin_immobilier' && (
                    <section id="settings-organization-logo" className="settings-section"><Card header="Logo de l’organisation" className="settings-card settings-card--logo">
                        <p>Choisissez l’image qui représente votre organisation. Avant l’enregistrement, vous pouvez la déplacer, la zoomer et la faire pivoter.</p>
                        {logoLoading && <p role="status">Chargement du logo…</p>}
                        {logoError && <p className="settings-error" role="alert">{logoError}</p>}
                        {!logoLoading && <OrganizationLogoPicker value={organizationLogo} change={organizationLogoChange} onChange={setOrganizationLogoChange} />}
                        {organizationLogoChange && <div className="settings-logo__save"><Button disabled={logoBusy} onClick={() => void saveOrganizationLogo()}>{logoBusy ? 'Enregistrement…' : 'Enregistrer le logo'}</Button></div>}
                    </Card></section>
                )}
                {isOrganizationPage && organizationRole === 'admin_immobilier' && (
                    <section id="settings-exchange-rate" className="settings-section"><Card header="Taux de change de l’organisation" className="settings-card">
                        <p>La devise de référence est utilisée pour les synthèses financières de toute l’organisation. Les montants comptabilisés gardent leur devise d’origine.</p>
                        {fxLoading && <p role="status">Chargement des paramètres de change…</p>}
                        {fxError && <p className="settings-error" role="alert">{fxError}</p>}
                        {fxSettings?.scope === 'organization' && <>
                        <div className="setting-row"><div><strong>Devise de référence</strong><p>Les rapports de l’organisation et de ses villes utiliseront cette devise pour leurs totaux.</p></div><FormField label="Devise de l’organisation" htmlFor="organization-currency"><Select id="organization-currency" value={organizationCurrencyDraft} onChange={(event) => setOrganizationCurrencyDraft(event.target.value as 'USD' | 'CDF')} options={[{ value: 'USD', label: 'Dollar américain (USD)' }, { value: 'CDF', label: 'Franc congolais (CDF)' }]} /></FormField><Button disabled={fxBusy || organizationCurrencyDraft === fxSettings.organizationCurrency} onClick={() => void saveOrganizationCurrency()}>Enregistrer</Button></div>
                        <p>Le taux ci-dessous reste exprimé en 1 USD = X CDF et sert aux conversions historiques des rapports, sauf dérogation locale autorisée.</p>
                        <div className="setting-row"><div><strong>Taux utilisé actuellement : 1 USD = {fxSettings.organizationRate?.rate ?? '—'} CDF</strong><p>{fxSettings.organizationRate ? `En vigueur depuis le ${fxSettings.organizationRate.effectiveFrom}` : 'Aucun taux organisationnel enregistré.'}</p></div></div>
                        <div className="settings-rate-history" aria-live="polite"><h3>Taux appliqué à la date sélectionnée</h3><p>Valeur applicable en fin de journée, selon l’historique enregistré.</p>{historyLoading ? <p>Chargement…</p> : historicalRates.map((item) => <div className="setting-row" key={item.scope}><div><strong>{item.scope} · {item.rate ? `1 ${item.rate.baseCurrency} = ${item.rate.rate} ${item.rate.quoteCurrency}` : 'Aucun taux applicable'}</strong><p>{item.rate ? `En vigueur depuis le ${item.rate.effectiveFrom}` : 'Le rapport ne dispose pas de taux pour cette date.'}</p></div></div>)}</div>
                        <div className="setting-row"><FormField label="Nouveau taux (CDF pour 1 USD)"><Input fullWidth inputMode="decimal" value={fxRateDraft} onChange={(event) => setFxRateDraft(event.target.value)} placeholder="Ex. 2850.00000000" /></FormField><Button disabled={fxBusy || !fxRateDraft.trim()} onClick={() => void saveFxRate('organization')}>Enregistrer</Button></div>
                        <h3>Autorisation par administrateur ville</h3>
                        {fxSettings.cityAdmins.length === 0 ? <p>Aucun administrateur ville n’est rattaché à cette organisation.</p> : fxSettings.cityAdmins.map((admin) => <div className="setting-row" key={admin.uuid}><div><strong>{admin.name}</strong><p>{admin.cities.map((city) => city.name).join(', ') || 'Aucune ville attribuée'}</p></div><button type="button" role="switch" aria-checked={admin.allowed} disabled={fxBusy || admin.cities.length === 0} className="setting-switch" onClick={() => void setFxPermission(admin, !admin.allowed)}><span /></button></div>)}
                        </>}
                    </Card></section>
                )}
                {isOrganizationPage && organizationRole === 'admin_ville' && fxSettings?.scope === 'city' && fxSettings.enabled && (
                    <section id="settings-exchange-rate" className="settings-section"><Card header="Taux de change de mes villes" className="settings-card">
                        <p>Devise de référence de l’organisation : <strong>{fxSettings.organizationCurrency}</strong>. Votre autorisation est limitée aux villes qui vous sont attribuées. Sans taux local, le taux de l’organisation s’applique.</p>
                        <div className="settings-rate-history" aria-live="polite"><h3>Taux appliqué à la date sélectionnée</h3><p>Valeur applicable en fin de journée, avec repli sur le taux de l’organisation.</p>{historyLoading ? <p>Chargement…</p> : historicalRates.map((item) => <div className="setting-row" key={item.scope}><div><strong>{item.scope} · {item.rate ? `1 ${item.rate.baseCurrency} = ${item.rate.rate} ${item.rate.quoteCurrency}` : 'Aucun taux applicable'}</strong><p>{item.source ?? ''}{item.rate ? ` · En vigueur depuis le ${item.rate.effectiveFrom}` : ''}</p></div></div>)}</div>
                        {fxSettings.cities?.map((city) => <div className="setting-row" key={city.uuid}><div><strong>{city.name} · Taux utilisé : 1 USD = {city.rate?.rate ?? 'taux de l’organisation'} CDF</strong><p>{city.rate ? `Dérogation locale en vigueur depuis le ${city.rate.effectiveFrom}` : 'Application du taux organisationnel.'}</p></div><FormField label={`Nouveau taux pour ${city.name}`}><Input fullWidth inputMode="decimal" value={fxRateDraft} onChange={(event) => setFxRateDraft(event.target.value)} placeholder="CDF pour 1 USD" /></FormField><Button disabled={fxBusy || !fxRateDraft.trim()} onClick={() => void saveFxRate(city)}>Enregistrer</Button></div>)}
                    </Card></section>
                )}
                {isOrganizationPage && organizationRole === 'admin_ville' && !(fxSettings?.scope === 'city' && fxSettings.enabled) && (
                    <section className="settings-section"><Card header="Taux de change" className="settings-card">
                        {fxLoading ? <p role="status">Vérification de votre autorisation…</p> : <p>Aucun réglage local n’est actif pour votre compte. Les rapports utilisent le taux défini par l’organisation.</p>}
                        {fxError && <p className="settings-error" role="alert">{fxError}</p>}
                    </Card></section>
                )}
                {isOrganizationPage && !['patron', 'admin_immobilier', 'admin_ville'].includes(organizationRole ?? '') && (
                    <section className="settings-section"><Card header="Accès non autorisé" className="settings-card">
                        <p>Les paramètres d’organisation sont réservés aux rôles autorisés. Vous pouvez gérer vos préférences personnelles depuis « Mon compte ».</p>
                    </Card></section>
                )}
                {!isOrganizationPage && <section id="settings-display" className="settings-section"><Card header="Affichage" className="settings-card settings-card--display">
                    <div className="setting-row">
                        <div>
                            <strong>Thème</strong>
                            <p>Choisissez l'apparence de l'interface.</p>
                        </div>
                        <Select
                            aria-label="Thème de l’interface"
                            value={settings.theme}
                            onChange={handleSelectChange('theme')}
                            options={[
                                { value: 'system', label: 'Système' },
                                { value: 'light', label: 'Clair' },
                                { value: 'dark', label: 'Sombre' },
                            ]}
                        />
                    </div>
                    <fieldset className="setting-palette">
                        <legend>Couleur d’accent</legend>
                        <p>Personnalisez les boutons, les liens et les contours de focus.</p>
                        <div className="setting-palette__options">
                            {([
                                ['ocean', 'Océan'],
                                ['violet', 'Violet'],
                                ['sage', 'Sauge'],
                                ['amber', 'Ambre'],
                                ['coral', 'Corail'],
                                ['slate', 'Ardoise'],
                            ] as const).map(([palette, label]) => (
                                <label className="setting-palette__option" key={palette}>
                                    <input
                                        type="radio"
                                        name="accentPalette"
                                        value={palette}
                                        checked={settings.accentPalette === palette}
                                        onChange={() => void apply('accentPalette', palette)}
                                    />
                                    <span className={`setting-palette__choice setting-palette__choice--${palette}`} aria-hidden="true">
                                        <span className="setting-palette__swatch" />
                                        <span>{settings.accentPalette === palette ? '✓' : ''}</span>
                                    </span>
                                    <span className="setting-palette__label">{label}</span>
                                </label>
                            ))}
                        </div>
                    </fieldset>
                    <div className="setting-row">
                        <div>
                            <strong>Langue</strong>
                            <p>Langue de l'interface.</p>
                            <small className="setting-row__note">Français est actuellement la langue disponible. Les autres traductions ne sont pas encore activées.</small>
                        </div>
                        <span className="setting-row__current-value">Français</span>
                    </div>
                    <div className="setting-row">
                        <div>
                            <strong>Format de date</strong>
                            <p>Format d'affichage des dates.</p>
                        </div>
                        <Select
                            aria-label="Format d’affichage des dates"
                            value={settings.dateFormat}
                            onChange={handleSelectChange('dateFormat')}
                            options={[
                                { value: 'DD/MM/YYYY', label: 'JJ/MM/AAAA' },
                                { value: 'MM/DD/YYYY', label: 'MM/JJ/AAAA' },
                                { value: 'YYYY-MM-DD', label: 'AAAA-MM-JJ' },
                            ]}
                        />
                    </div>
                    <div className="setting-row">
                        <div>
                            <strong>Tableaux compacts</strong>
                            <p>{settings.compactTables
                                ? 'Activé : les lignes des tableaux prennent moins de hauteur.'
                                : 'Désactivé : activez cette option pour afficher plus de lignes à l’écran.'}</p>
                        </div>
                        <button
                            className="setting-switch"
                            type="button"
                            role="switch"
                            aria-label="Afficher des tableaux compacts"
                            aria-checked={settings.compactTables}
                            onClick={() => toggle('compactTables')}
                        >
                            <span />
                        </button>
                    </div>
                    <div className="setting-row">
                        <div>
                            <strong>Réduire les animations</strong>
                            <p>Réduit les animations et transitions dans l’application.</p>
                        </div>
                        <button
                            className="setting-switch"
                            type="button"
                            role="switch"
                            aria-label="Réduire les animations"
                            aria-checked={settings.reducedMotion}
                            onClick={() => toggle('reducedMotion')}
                        >
                            <span />
                        </button>
                    </div>
                    <div className="setting-row">
                        <div>
                            <strong>Menu latéral replié au démarrage</strong>
                            <p>Gagnez de la place sur ordinateur. Le menu reste ouvrable à tout moment.</p>
                        </div>
                        <button
                            className="setting-switch"
                            type="button"
                            role="switch"
                            aria-label="Replier le menu latéral par défaut"
                            aria-checked={settings.sidebarCollapsed}
                            onClick={() => toggle('sidebarCollapsed')}
                        >
                            <span />
                        </button>
                    </div>
                    <div className="setting-row">
                        <div>
                            <strong>Lignes par page</strong>
                            <p>Nombre d’éléments affichés à la fois dans les tableaux.</p>
                        </div>
                        <Select
                            aria-label="Nombre de lignes par page"
                            value={settings.defaultPageSize ?? 20}
                            onChange={handleSelectChange('defaultPageSize')}
                            options={[
                                { value: 10, label: '10 lignes' },
                                { value: 20, label: '20 lignes' },
                                { value: 50, label: '50 lignes' },
                                { value: 100, label: '100 lignes' },
                            ]}
                        />
                    </div>
                </Card></section>}

                {!isOrganizationPage && <section id="settings-shortcut" className="settings-section"><Card header="Accès rapide" className="settings-card settings-card--shortcut">
                    <div className="setting-row">
                        <div>
                            <strong>Afficher l’option d’installation</strong>
                            <p>Choisissez si Soft-IMMO doit vous proposer son raccourci sur cet appareil. L’installation ne démarre qu’après votre action et la confirmation du navigateur.</p>
                        </div>
                        <button
                            className="setting-switch"
                            type="button"
                            role="switch"
                            aria-label="Afficher l’option d’installation de Soft-IMMO"
                            aria-checked={settings.allowInstallPrompt}
                            onClick={() => toggle('allowInstallPrompt')}
                        ><span /></button>
                    </div>
                    {pwaInstallation.installed ? <p className="settings-install-status" role="status">Soft-IMMO est déjà installée sur cet appareil. Pour la retirer, utilisez les réglages de votre appareil ou de votre navigateur.</p>
                        : settings.allowInstallPrompt ? <div className="settings-install-help">
                            <Button onClick={() => void installSoftImmo()} isLoading={installingPwa} disabled={installingPwa}>Installer ou créer un raccourci</Button>
                            <p>{pwaInstallation.canPrompt
                                ? 'Le navigateur vous demandera de confirmer. Vous pouvez refuser sans modifier votre compte.'
                                : 'Si votre navigateur ne prend pas en charge l’installation directe, ce bouton vous indiquera comment créer un raccourci depuis son menu.'}</p>
                            <p>Votre choix est enregistré sur votre compte ; chaque installation ou raccourci concerne uniquement cet appareil et ce navigateur.</p>
                        </div> : <p className="settings-install-status">L’option d’installation est masquée. Vous pouvez la réactiver à tout moment depuis cette page.</p>}
                </Card></section>}

                {!isOrganizationPage && <section id="settings-notifications" className="settings-section"><Card header="Notifications" className="settings-card">
                    <div className="setting-row">
                        <div>
                            <strong>Notifications par e-mail</strong>
                            <p>Recevoir les alertes importantes par e-mail.</p>
                        </div>
                        <button
                            className="setting-switch"
                            type="button"
                            role="switch"
                            aria-label="Recevoir les notifications importantes par e-mail"
                            aria-checked={settings.emailNotifications}
                            onClick={() => toggle('emailNotifications')}
                        >
                            <span />
                        </button>
                    </div>
                    <div className="setting-row">
                        <div>
                            <strong>Notifications dans l'application</strong>
                            <p>Afficher les confirmations et messages d’état temporaires dans l’interface.</p>
                        </div>
                        <button
                            className="setting-switch"
                            type="button"
                            role="switch"
                            aria-label="Afficher les confirmations et messages d’état dans l’application"
                            aria-checked={settings.inAppNotifications}
                            onClick={() => toggle('inAppNotifications')}
                        >
                            <span />
                        </button>
                    </div>
                </Card></section>}

                {!isOrganizationPage && <section id="settings-security" className="settings-section"><Card header="Sécurité" className="settings-card">
                    <div className="setting-row">
                        <div>
                            <strong>Mot de passe</strong>
                            <p>Pour modifier votre mot de passe, utilisez la procédure de réinitialisation sécurisée.</p>
                        </div>
                        <Button as="a" href="/forgot-password" variant="secondary">
                            Réinitialiser
                        </Button>
                    </div>
                </Card></section>}


                {!isOrganizationPage && isSuperAdmin && (
                    <section id="settings-platform" className="settings-section"><Card header="Préférences plateforme" className="settings-card settings-card--platform">
                        <div className="setting-row">
                            <div>
                                <strong>Période d'affichage du journal (jours)</strong>
                                <p>Limiter les résultats affichés aux événements récents (30–2555 jours).</p>
                            </div>
                            <FormField label="" htmlFor="auditRetention">
                                <input
                                    id="auditRetention"
                                    type="number"
                                    min="30"
                                    max="2555"
                                    value={retentionDraft ?? String(settings.auditLogRetentionDays ?? '')}
                                    placeholder="Ex. : 365"
                                    onChange={(e) => setRetentionDraft(e.target.value)}
                                    onBlur={saveRetention}
                                    onKeyDown={(event) => { if (event.key === 'Enter') { event.currentTarget.blur(); } }}
                                />
                                {retentionError && <small className="setting-row__error">{retentionError}</small>}
                            </FormField>
                        </div>
                    </Card></section>
                )}
            </div>
            <RightSidebar title="Paramètres" size="small" variant="dark" collapsible defaultCollapsed={false} collapsedWidth={48} closeOnSelect className="settings-rightbar" collapsedContent={<div className="settings-rightbar__rail" aria-label="Raccourcis paramètres"><NavigationRailPopover title="Pages des paramètres" label="Ouvrir le menu des paramètres"><nav className="settings-rightbar__nav" aria-label="Pages des paramètres"><Link to="/settings" aria-current={!isOrganizationPage ? 'page' : undefined}>Mon compte</Link>{(['patron', 'admin_immobilier', 'admin_ville'].includes(organizationRole ?? '') || (organizationRole === 'admin_ville' && fxSettings?.scope === 'city' && fxSettings.enabled)) && <Link to="/settings/organisation" aria-current={isOrganizationPage ? 'page' : undefined}>Organisation</Link>}<Link to="/settings/manuel">Manuel d’utilisation</Link></nav></NavigationRailPopover>{isOrganizationPage && <CalendarRailPopover title="Historique des taux" label="Ouvrir le calendrier des taux"><Calendar selectedDate={new Date(`${historyDate}T12:00:00`)} onDateSelect={(date) => setHistoryDate(localDateKey(date))} className="settings-calendar__picker" weekdayFormat="compact" /></CalendarRailPopover>}</div>}>
                {isOrganizationPage && <section className="settings-calendar" aria-label="Navigation dans l’historique"><h2>Historique des taux</h2><p>Choisissez une date pour consulter le taux applicable à vos rapports.</p><Calendar selectedDate={new Date(`${historyDate}T12:00:00`)} onDateSelect={(date) => setHistoryDate(localDateKey(date))} className="settings-calendar__picker" weekdayFormat="compact" /></section>}
                <nav className="settings-rightbar__nav" aria-label="Sections des paramètres">
                    <span className="settings-rightbar__label">Pages</span>
                    <Link to="/settings" aria-current={!isOrganizationPage ? 'page' : undefined}>Mon compte</Link>
                    {(['patron', 'admin_immobilier', 'admin_ville'].includes(organizationRole ?? '') || (organizationRole === 'admin_ville' && fxSettings?.scope === 'city' && fxSettings.enabled)) && <Link to="/settings/organisation" aria-current={isOrganizationPage ? 'page' : undefined}>Organisation</Link>}
                    <Link to="/settings/manuel">Manuel d’utilisation</Link>
                    {!isOrganizationPage && <div className="settings-rightbar__subnav" aria-label="Sections du compte">
                        <span className="settings-rightbar__label">Préférences</span>
                        <a href="#settings-display" className={activeSection === 'settings-display' ? 'is-active' : undefined} aria-current={activeSection === 'settings-display' ? 'location' : undefined}>Affichage</a>
                        <a href="#settings-shortcut" className={activeSection === 'settings-shortcut' ? 'is-active' : undefined} aria-current={activeSection === 'settings-shortcut' ? 'location' : undefined}>Accès rapide</a>
                        <a href="#settings-notifications" className={activeSection === 'settings-notifications' ? 'is-active' : undefined} aria-current={activeSection === 'settings-notifications' ? 'location' : undefined}>Notifications</a>
                        <a href="#settings-security" className={activeSection === 'settings-security' ? 'is-active' : undefined} aria-current={activeSection === 'settings-security' ? 'location' : undefined}>Sécurité</a>
                        <Link to="/settings/manuel">Manuel d’utilisation</Link>
                        {isSuperAdmin && <a href="#settings-platform" className={activeSection === 'settings-platform' ? 'is-active' : undefined} aria-current={activeSection === 'settings-platform' ? 'location' : undefined}>Plateforme</a>}
                    </div>}
                    {isOrganizationPage && <div className="settings-rightbar__subnav" aria-label="Sections de l’organisation">
                        <a href="#settings-rental-rules" className={activeSection === 'settings-rental-rules' ? 'is-active' : undefined} aria-current={activeSection === 'settings-rental-rules' ? 'location' : undefined}>Règles de loyer</a>
                        {['patron', 'admin_immobilier'].includes(organizationRole ?? '') && <a href="#settings-organization-capacity">Offre et capacités</a>}
                        <span className="settings-rightbar__label">Réglages partagés</span>
                        {organizationRole === 'admin_immobilier' && <a href="#settings-organization-logo" className={activeSection === 'settings-organization-logo' ? 'is-active' : undefined} aria-current={activeSection === 'settings-organization-logo' ? 'location' : undefined}>Logo de l’organisation</a>}
                        <a href="#settings-exchange-rate" className={activeSection === 'settings-exchange-rate' ? 'is-active' : undefined} aria-current={activeSection === 'settings-exchange-rate' ? 'location' : undefined}>Taux de change</a>
                    </div>}
                </nav>
            </RightSidebar>
            <Modal isOpen={installHelpOpen} onClose={() => setInstallHelpOpen(false)} title="Ajouter Soft-IMMO à cet appareil" size="small" className="settings-pwa-help-modal" footer={<Button onClick={() => setInstallHelpOpen(false)}>J’ai compris</Button>}>
                {!window.isSecureContext && <div className="settings-pwa-help-modal__notice" role="alert">
                    <strong>Installation de l’application indisponible sur cette adresse</strong>
                    <p>Cette page est ouverte en HTTP depuis une adresse réseau. Chrome exige une connexion sécurisée (HTTPS) pour installer une application. L’installation sera disponible lorsque Soft-IMMO sera publiée en HTTPS.</p>
                </div>}
                <p className="settings-pwa-help-modal__intro">Vous pouvez tout de même créer un raccourci avec le menu de votre navigateur. Les intitulés varient selon sa version.</p>
                <ul className="settings-pwa-help-modal__steps">
                    <li><strong>Android / Chrome</strong><span>Ouvrez le menu ⋮, puis choisissez « Installer l’application » ou « Ajouter à l’écran d’accueil ». Si seule l’option « Créer un raccourci » apparaît, elle ouvrira le site dans Chrome.</span></li>
                    <li><strong>iPhone ou iPad / Safari</strong><span>Appuyez sur Partager, puis « Sur l’écran d’accueil ».</span></li>
                    <li><strong>Ordinateur / Chrome ou Edge</strong><span>Utilisez l’icône d’installation près de la barre d’adresse ou le menu du navigateur. Sur Safari pour Mac : Fichier, puis « Ajouter au Dock ».</span></li>
                </ul>
                <p className="settings-pwa-help-modal__note">Le raccourci reste lié à l’appareil et au navigateur. Vous pourrez le retirer depuis leurs réglages.</p>
            </Modal>
        </main>
    );
}
