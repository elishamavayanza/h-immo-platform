import { useEffect, useState, type FormEvent } from 'react';

import { useNavigate } from 'react-router-dom';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { Input } from '../../../../components/Forms/Input';
import { Textarea } from '../../../../components/Forms/Textarea';
import { Switch } from '../../../../components/Forms/Switch';
import { FormField } from '../../../../components/Forms/FormField';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Tabs } from '../../../../components/Navigation/Tabs';
import { OrganizationLogoPicker, type OrganizationLogoChange } from '../../../super_admin/organizations/components/OrganizationLogoPicker';
import { useVitrine } from '../../../admin_immobilier/vitrine/hooks/useVitrine';
import { VitrineTable } from '../../../admin_immobilier/vitrine/components/VitrineTable';
import { fieldErrorMap } from '../../../shared/utils/actionErrors';
import { usePublicSettings } from '../hooks/usePublicSettings';
import '../../../../../styles/pages/patron/vitrine/_vitrine.scss';

/**
 * Vitrine du PATRON.
 *
 * Deux zones :
 *  1. « Réglages publics » — le Patron pilote lui-même le slug, la
 *     description et la visibilité de sa page publique (+ le logo). C'est
 *     l'une de ses zones d'écriture propres ; le backend rejoue
 *     `checkOrganizationAccess(MANAGE_ORGANIZATION)`.
 *  2. Les annonces de l'organisation, réutilisées en lecture seule : la
 *     publication d'une unité reste une opération d'ADMIN_IMMOBILIER.
 */
export function VitrinePage() {
    const { organizationRole } = useOrganization();
    const { settings, isLoading, error, reload, save, saveLogo, removeLogo } = usePublicSettings();
    const vitrine = useVitrine();
    const allRows = vitrine.data?.rows ?? [];
    const navigate = useNavigate();

    const [slug, setSlug] = useState('');
    const [description, setDescription] = useState('');
    const [isListed, setIsListed] = useState(true);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const [logoChange, setLogoChange] = useState<OrganizationLogoChange>(null);
    const [savingLogo, setSavingLogo] = useState(false);

    useEffect(() => {
        if (!settings) return;
        setSlug(settings.slug ?? '');
        setDescription(settings.publicDescription ?? '');
        setIsListed(settings.isPubliclyListed);
    }, [settings]);

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        setFormErrors({});
        try {
            await save({
                // Le slug ne peut être ni effacé ni vidé côté backend : on ne
                // l'envoie que s'il valait déjà quelque chose ou est renseigné.
                ...(slug.trim() ? { slug: slug.trim() } : {}),
                publicDescription: description.trim(),
                isPubliclyListed: isListed,
            });
        } catch (cause) {
            setFormErrors(fieldErrorMap(cause));
        } finally {
            setSubmitting(false);
        }
    };

    const persistLogo = async () => {
        if (!logoChange || savingLogo) return;
        setSavingLogo(true);
        try {
            if (logoChange.kind === 'new') {
                await saveLogo(logoChange.file);
            } else {
                await removeLogo();
            }
            setLogoChange(null);
        } finally {
            setSavingLogo(false);
        }
    };

    let listingBody = (
        <VitrineTable
            rows={vitrine.rows}
            role={organizationRole}
            pendingId={vitrine.pendingId}
            onTogglePublished={() => {}}
            onEdit={() => {}}
            onAddPhoto={() => {}}
            readOnly
        />
    );
    if (vitrine.isLoading) {
        listingBody = <EmptyState icon={<Spinner size="large" />} title="Chargement des annonces…" description="Récupération des biens de l’organisation." />;
    } else if (vitrine.error) {
        listingBody = <EmptyState title="Annonces indisponibles" description={vitrine.error} action={<Button onClick={() => void vitrine.reload()}>Réessayer</Button>} />;
    } else if (vitrine.rows.length === 0) {
        listingBody = <EmptyState title="Aucune annonce à afficher" description="Aucune unité enregistrée pour cette organisation." />;
    }

    return (
        <main className="organization-page organization-operational-page">
            <header className="organization-page__header">
                <div>
                    <span className="organization-page__eyebrow">ESPACE PATRON</span>
                    <h1>Vitrine</h1>
                    <p>Pilotez la présentation publique de votre organisation et suivez vos annonces.</p>
                </div>
                <Button variant="outline" onClick={() => navigate('/app/organisation')}>Gérer l'organisation</Button>
            </header>

            {isLoading ? (
                <div className="main-layout__page-loading" aria-busy="true"><Spinner size="large" className="spinner--page" /><span>Chargement des réglages…</span></div>
            ) : error ? (
                <EmptyState title="Réglages indisponibles" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />
            ) : (
                <Tabs
                    className="patron-vitrine-tabs"
                    variant="underline"
                    tabs={[
                        { id: 'settings', label: 'Réglages de la vitrine' },
                        { id: 'listings', label: `Annonces (${allRows.length})` },
                    ]}
                    renderContent={(activeTabId) => activeTabId === 'settings' ? (
                        <div className="patron-vitrine-settings">
                    <Card className="organization-table-card" padding="medium">
                        <div className="organization-table-toolbar">
                            <div>
                                <h2>Réglages publics</h2>
                                <p>Ces informations apparaissent sur votre page publique.</p>
                            </div>
                        </div>
                        <form className="organization-management-form" onSubmit={submit}>
                            <FormField label="Nom de la page publique (slug)" htmlFor="vitrine-slug" error={formErrors.slug}>
                                <Input
                                    id="vitrine-slug"
                                    value={slug}
                                    onChange={(event) => setSlug(event.target.value)}
                                    placeholder="ex : immo-plus"
                                    maxLength={60}
                                    fullWidth
                                />
                            </FormField>
                            <p className="organization-management-form__hint">
                                Minuscules, chiffres et tirets uniquement. Un slug déjà utilisé par une autre entreprise est refusé.
                            </p>
                            <FormField label="Description publique" htmlFor="vitrine-description" error={formErrors.publicDescription}>
                                <Textarea
                                    id="vitrine-description"
                                    value={description}
                                    onChange={(event) => setDescription(event.target.value)}
                                    placeholder="Ex. : Des logements bien situés, entretenus et adaptés à vos besoins."
                                    rows={4}
                                    maxLength={2000}
                                    fullWidth
                                />
                            </FormField>
                            <FormField label="Visibilité" htmlFor="vitrine-listed">
                                <Switch
                                    id="vitrine-listed"
                                    checked={isListed}
                                    onChange={(event) => setIsListed(event.target.checked)}
                                    label="Afficher la vitrine sur le site public"
                                />
                            </FormField>
                            <div className="organization-table-toolbar__actions">
                                <Button type="submit" disabled={submitting} aria-busy={submitting}>
                                    {submitting ? 'Enregistrement…' : 'Enregistrer les réglages'}
                                </Button>
                            </div>
                        </form>
                    </Card>

                    <Card className="organization-table-card" padding="medium">
                        <div className="organization-table-toolbar">
                            <div>
                                <h2>Logo</h2>
                                <p>Le logo s’affiche sur votre vitrine et vos documents.</p>
                            </div>
                        </div>
                        <OrganizationLogoPicker value={settings?.logo ?? null} change={logoChange} onChange={setLogoChange} />
                        <div className="organization-table-toolbar__actions">
                            <Button
                                type="button"
                                onClick={() => void persistLogo()}
                                disabled={!logoChange || savingLogo}
                                aria-busy={savingLogo}
                            >
                                {savingLogo ? 'Enregistrement…' : 'Enregistrer le logo'}
                            </Button>
                        </div>
                    </Card>
                        </div>
                    ) : (
                        <>
                            <OrganizationSummary
                                items={[
                                    { label: 'Annonces chargées', value: <>{allRows.length}</> },
                                    { label: 'Publiées', value: <>{allRows.filter((row) => row.isPublished).length}</> },
                                    { label: 'Brouillons', value: <>{allRows.filter((row) => !row.isPublished).length}</> },
                                ]}
                            />
                            <Card className="organization-table-card" padding="medium">
                                <div className="organization-table-toolbar">
                                    <div>
                                        <h2>Annonces immobilières</h2>
                                        <p>{vitrine.data?.note ?? 'La publication des annonces est gérée par votre administrateur immobilier.'}</p>
                                    </div>
                                    <div className="organization-filters organization-filters--tenant">
                                        <SearchInput value={vitrine.search} onSearch={vitrine.setSearch} placeholder="Rechercher une annonce…" fullWidth />
                                        <Select
                                            aria-label="Filtrer par statut"
                                            value={vitrine.status}
                                            onChange={(event) => vitrine.setStatus(event.target.value)}
                                            options={[
                                                { value: 'all', label: 'Tous les statuts' },
                                                { value: 'published', label: 'Publiées' },
                                                { value: 'unpublished', label: 'Brouillons' },
                                            ]}
                                        />
                                    </div>
                                </div>
                                {listingBody}
                            </Card>
                        </>
                    )}
                />
            )}
        </main>
    );
}
