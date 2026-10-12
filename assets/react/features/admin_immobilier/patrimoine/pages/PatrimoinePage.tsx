import { useState } from 'react';

import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Badge } from '../../../../components/UI/Badge';
import { Modal } from '../../../../components/UI/Modal';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { RightSidebar } from '../../../../components/Navigation/RightSidebar';
import { useLocation, useNavigate } from 'react-router-dom';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { formatInteger, formatMoney, formatPercent } from '../../../../../utils/format.utils';
import { usePatrimoine } from '../hooks/usePatrimoine';
import { useUnitContext } from '../hooks/useUnitContext';
import { canDo } from '../../../shared/permissions';
import { tenantLabel } from '../../shared/services/referenceService';
import { PhotoViewer } from '../../../shared/media/components/PhotoViewer';
import { PatrimoineTable } from '../components/PatrimoineTable';
import { VillesTab } from '../components/VillesTab';
import { ParcellesTab } from '../components/ParcellesTab';
import { BatimentsTab } from '../components/BatimentsTab';
import { UnitesTab } from '../components/UnitesTab';
import { PatrimoineNav, buildPatrimoinePath, resolvePatrimoineSelection, type PatrimoineLevel } from '../components/PatrimoineNav';
import { PatrimoineSummaryCard } from '../components/PatrimoineSummaryCard';
import { PatrimoineChildList, type PatrimoineChildItem } from '../components/PatrimoineChildList';
import { Tabs } from '../../../../components/Navigation/Tabs';
import {
    buildingIndex,
    buildingOccupancy,
    buildingsOfCity,
    buildingsOfParcel,
    buildingTypeLabel,
    cityOccupancy,
    formatPeriod,
    formatShortDate,
    leaseStatusLabel,
    nextUnpaidRent,
    parcelCoordinates,
    parcelIndex,
    parcelOccupancy,
    parcelsOfCity,
    relatedModuleUrl,
    rentStatusLabel,
    summarizeRents,
    unitCount,
    unitsOfBuilding,
    unitTypeLabel,
} from '../components/patrimoineView';
import type { ParcelItem } from '../../shared/types/reference.types';
import '../../../../../styles/pages/admin_immobilier/patrimoine/_patrimoine.scss';

/** Surface affichée en m² à partir d'une chaîne décimale (jamais un montant). */
function squareMeters(value: string): string {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? `${new Intl.NumberFormat('fr-CD', { maximumFractionDigits: 2 }).format(parsed)} m²` : `${value} m²`;
}

/**
 * Patrimoine : vue d'ensemble (lecture) + gestion des quatre niveaux.
 *
 * La page orchestre : elle charge via `usePatrimoine`, affiche le bandeau
 * d'indicateurs, puis, pour chaque niveau sélectionné, une carte de synthèse et
 * les éléments enfants sous forme de cartes navigables, avant le tableau de
 * gestion. Le niveau actif et le contexte de drill-down sont lus dans l'URL ;
 * la navigation verticale (panneau droit) et le fil d'Ariane ne font que des
 * liens vers ces URL, ce qui garde chaque vue partageable et le bouton Retour
 * fonctionnel.
 *
 * Tous les indicateurs sont dérivés des listes déjà chargées par le hook et du
 * rapport d'occupation : aucune vue ne déclenche d'appel supplémentaire, aucun
 * montant n'est recalculé. Les actions sont gatées par `canDo` / `readOnly` ;
 * l'API demeure l'autorité.
 *
 * `readOnly` (espace PATRON) : les écrans de niveau n'exposent aucune écriture
 * et le lien « Employés » (absent de l'espace Patron) est écarté.
 */
export function PatrimoinePage({ readOnly = false }: { readOnly?: boolean } = {}) {
    const { organizationRole } = useOrganization();
    const location = useLocation();
    const navigate = useNavigate();
    const isCityAdmin = organizationRole === 'admin_ville';
    const selection = resolvePatrimoineSelection(location.pathname, isCityAdmin);
    const { level: activeLevel, cityId: selectedCityId, parcelId: selectedParcelId, buildingId: selectedBuildingId, unitId: selectedUnitId } = selection;

    const [photoParcel, setPhotoParcel] = useState<ParcelItem | null>(null);

    const goToLevel = (level: PatrimoineLevel, cityId: string | null, parcelId: string | null, buildingId: string | null) => {
        navigate(buildPatrimoinePath(level, { cityId, parcelId, buildingId }));
    };
    const {
        data,
        rows,
        cities,
        parcels,
        buildings,
        units,
        isLoading,
        error,
        reload,
        search,
        setSearch,
        city,
        setCity,
        kind,
        setKind,
        createCity,
        createCityAdmin,
        updateCity,
        setCityStatus,
        deleteCity,
        createParcel,
        updateParcel,
        deleteParcel,
        createBuilding,
        updateBuilding,
        deleteBuilding,
        createUnit,
        updateUnit,
        deleteUnit,
        publishUnit,
    } = usePatrimoine();

    const note = data?.note;
    const cityBy = new Map(cities.map((entry) => [entry.id, entry]));
    const parcelBy = parcelIndex(data);
    const buildingBy = buildingIndex(data);
    const selectedCity = selectedCityId ? cityBy.get(selectedCityId) : undefined;
    const selectedParcel = selectedParcelId ? parcelBy.get(selectedParcelId) : undefined;
    const selectedBuilding = selectedBuildingId ? buildingBy.get(selectedBuildingId) : undefined;
    const selectedUnit = selectedUnitId ? units.find((unit) => unit.id === selectedUnitId) : undefined;
    const unitContext = useUnitContext(selectedUnit ? selectedUnitId : null);
    const contextParcel = selectedParcel ?? parcelBy.get(selectedBuilding?.parcelId ?? '');
    const contextCity = contextParcel ? cityBy.get(contextParcel.cityId) : selectedCity;
    const openParcel = (id: string | null) => {
        const parcel = parcels.find((item) => item.id === id);
        goToLevel(id ? 'batiments' : 'parcelles', parcel?.cityId ?? selectedCityId, id, null);
    };
    const openBuilding = (id: string | null) => {
        const building = buildings.find((item) => item.id === id);
        const parcel = parcels.find((item) => item.id === building?.parcelId);
        goToLevel('unites', parcel?.cityId ?? selectedCityId, parcel?.id ?? selectedParcelId, id);
    };

    /**
     * Liens vers les modules qui portent les données non chargées par le
     * Patrimoine (locataires, baux, loyers, paiements, dépenses, personnel).
     *
     * Aucune donnée n'en est extraite ni additionnée : ce sont de simples liens,
     * filtrés par `parcelUuid` / `buildingUuid`, seuls paramètres reconnus par
     * ces pages. Les actions d'écriture restent dans leurs écrans dédiés.
     */
    const moduleLinks = (parcelId: string | null, buildingId: string | null): PatrimoineChildItem[] => {
        const links: PatrimoineChildItem[] = [];
        if (canDo(organizationRole, 'view_tenant')) {
            links.push({ id: 'tenants', title: 'Locataires et baux', subtitle: 'Locataires, contrats et statut des baux', to: relatedModuleUrl('/app/location/locataires', parcelId, buildingId) });
        }
        if (canDo(organizationRole, 'view_rent')) {
            links.push({ id: 'rents', title: 'Loyers et paiements', subtitle: 'Échéances et encaissements', to: relatedModuleUrl('/app/location/loyers', parcelId, buildingId) });
        }
        if (canDo(organizationRole, 'view_expense')) {
            links.push({ id: 'expenses', title: 'Dépenses', subtitle: 'Dépenses rattachées à ce bien', to: relatedModuleUrl('/app/depenses', parcelId, buildingId) });
        }
        if (!readOnly && organizationRole !== 'patron' && canDo(organizationRole, 'create_worker_assignment')) {
            links.push({
                id: 'workers',
                title: isCityAdmin ? 'Affecter un employé' : 'Employés et affectations',
                subtitle: isCityAdmin ? 'Choisir un employé à affecter à ce bien' : 'Personnel affecté à ce bien',
                // `/app/operations` (ADMIN_VILLE) ne lit pas `parcelUuid`/`buildingUuid` :
                // on n'ajoute pas de paramètre ignoré. `/app/personnel` les reconnaît.
                to: isCityAdmin ? '/app/operations' : relatedModuleUrl('/app/personnel', parcelId, buildingId),
            });
        }
        return links;
    };

    const relatedSection = (items: PatrimoineChildItem[], description: string) => items.length > 0 ? (
        <PatrimoineChildList
            title="Accès liés"
            description={description}
            items={items}
            emptyTitle="Aucun accès"
            emptyDescription="Aucun module lié n’est disponible pour votre rôle."
            ariaLabel="Accès liés"
        />
    ) : null;

    const overviewModules = (
        <PatrimoineChildList
            title="Accès aux modules"
            description="Cette vue ne charge que villes, parcelles, bâtiments et unités : les autres familles se consultent dans leurs pages dédiées."
            items={[
                ...(canDo(organizationRole, 'view_tenant') ? [{ id: 'tenants', title: 'Locataires et baux', subtitle: 'Locataires, contrats et statut des baux', to: '/app/location/locataires' }] : []),
                ...(canDo(organizationRole, 'view_rent') ? [{ id: 'rents', title: 'Loyers et paiements', subtitle: 'Échéances et encaissements', to: '/app/location/loyers' }] : []),
                ...(canDo(organizationRole, 'view_expense') ? [{ id: 'expenses', title: 'Dépenses', subtitle: 'Dépenses de l’organisation', to: '/app/depenses' }] : []),
            ] as PatrimoineChildItem[]}
            emptyTitle="Aucun module"
            emptyDescription="Aucun module lié n’est disponible pour votre rôle."
            ariaLabel="Accès aux modules"
        />
    );

    const dataReady = !isLoading && !error && data !== null;

    let overviewBody = <PatrimoineTable rows={rows} />;
    if (isLoading) {
        overviewBody = <EmptyState icon={<Spinner size="large" />} title="Chargement du patrimoine…" description="Récupération du catalogue et du rapport d’occupation." />;
    } else if (error) {
        overviewBody = <EmptyState title="Patrimoine indisponible" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />;
    } else if (rows.length === 0) {
        overviewBody = <EmptyState
            title="Aucun bien à afficher"
            description={data && data.rows.length > 0 ? 'Aucun bien ne correspond à la recherche ou aux filtres sélectionnés.' : 'Votre organisation n’a encore aucune parcelle ou immeuble enregistré.'}
        />;
    }

    /** Vue contextuelle de la ville sélectionnée : synthèse + parcelles. */
    const cityView = selectedCity && (
        <>
            <PatrimoineSummaryCard
                eyebrow="VILLE"
                title={selectedCity.name}
                badges={<Badge variant={selectedCity.status === 'active' ? 'success' : 'secondary'}>{selectedCity.status === 'active' ? 'Active' : 'Inactive'}</Badge>}
                subtitle="Structure du patrimoine rattaché à cette ville."
                fields={(() => {
                    const cityParcels = parcelsOfCity(data, selectedCity.id);
                    const cityBuildings = buildingsOfCity(data, selectedCity.id);
                    const occupancy = cityOccupancy(data, selectedCity.id);
                    const cityUnits = occupancy ? occupancy.totalUnits : cityBuildings.reduce((total, building) => total + unitsOfBuilding(data, building.id).length, 0);
                    return [
                        { label: 'Code', value: selectedCity.code },
                        { label: 'Province', value: selectedCity.province || '—' },
                        { label: 'Pays', value: selectedCity.country || '—' },
                        { label: 'Parcelles', value: formatInteger(cityParcels.length) },
                        { label: 'Bâtiments', value: formatInteger(cityBuildings.length) },
                        { label: 'Unités', value: formatInteger(cityUnits) },
                        { label: 'Occupées', value: occupancy ? formatInteger(occupancy.occupiedUnits) : '—' },
                        { label: 'Disponibles', value: occupancy ? formatInteger(occupancy.availableUnits) : '—' },
                        { label: 'Occupation', value: occupancy ? formatPercent(occupancy.occupancyRate) : '—' },
                    ];
                })()}
            />
            <PatrimoineChildList
                title="Parcelles de la ville"
                description="Ouvrez une parcelle pour accéder à ses bâtiments."
                items={parcelsOfCity(data, selectedCity.id).map((parcel) => {
                    const occupancy = parcelOccupancy(data, parcel.id);
                    return {
                        id: parcel.id,
                        title: parcel.name,
                        subtitle: parcel.reference,
                        meta: [
                            { label: 'Adresse', value: [parcel.quarter, parcel.address].filter(Boolean).join(' · ') || '—' },
                            { label: 'Superficie', value: squareMeters(parcel.area) },
                            { label: 'Occupation', value: occupancy ? formatPercent(occupancy.occupancyRate) : '—' },
                        ],
                        to: buildPatrimoinePath('batiments', { cityId: selectedCity.id, parcelId: parcel.id, buildingId: null }),
                    } satisfies PatrimoineChildItem;
                })}
                emptyTitle="Aucune parcelle"
                emptyDescription="Ajoutez une parcelle à cette ville pour commencer à la structurer."
                ariaLabel={`Parcelles de ${selectedCity.name}`}
            />
        </>
    );

    /** Vue contextuelle de la parcelle sélectionnée : synthèse + bâtiments. */
    const parcelView = selectedParcel && (
        <>
            <PatrimoineSummaryCard
                eyebrow="PARCELLE"
                title={selectedParcel.name}
                subtitle={contextCity ? `Ville de ${contextCity.name}` : undefined}
                fields={(() => {
                    const parcelBuildings = buildingsOfParcel(data, selectedParcel.id);
                    const occupancy = parcelOccupancy(data, selectedParcel.id);
                    const parcelUnits = occupancy ? occupancy.totalUnits : parcelBuildings.reduce((total, building) => total + unitsOfBuilding(data, building.id).length, 0);
                    const coordinates = parcelCoordinates(selectedParcel);
                    return [
                        { label: 'Référence', value: selectedParcel.reference },
                        { label: 'Ville', value: contextCity?.name ?? '—' },
                        { label: 'Adresse', value: selectedParcel.address || '—' },
                        { label: 'Quartier', value: selectedParcel.quarter || '—' },
                        { label: 'Superficie', value: squareMeters(selectedParcel.area) },
                        { label: 'N° de titre', value: selectedParcel.titleNumber || '—' },
                        { label: 'Coordonnées', value: coordinates ? `${coordinates.latitude}, ${coordinates.longitude}` : 'GPS absent' },
                        { label: 'Bâtiments', value: formatInteger(parcelBuildings.length) },
                        { label: 'Unités', value: formatInteger(parcelUnits) },
                        { label: 'Occupées', value: occupancy ? formatInteger(occupancy.occupiedUnits) : '—' },
                        { label: 'Disponibles', value: occupancy ? formatInteger(occupancy.availableUnits) : '—' },
                        { label: 'Occupation', value: occupancy ? formatPercent(occupancy.occupancyRate) : '—' },
                    ];
                })()}
                actions={<>
                    {canDo(organizationRole, 'view_parcel') && <Button variant="outline" onClick={() => setPhotoParcel(selectedParcel)}>Voir les photos</Button>}
                </>}
            >
                {parcelOccupancy(data, selectedParcel.id) === null && (
                    <p className="patrimoine-summary__hint">
                        Aucune donnée d’occupation pour cette parcelle : les compteurs d’unités proviennent du catalogue chargé et peuvent être partiels.
                    </p>
                )}
            </PatrimoineSummaryCard>
            <PatrimoineChildList
                title="Bâtiments de la parcelle"
                description="Ouvrez un bâtiment pour consulter et gérer ses unités."
                items={buildingsOfParcel(data, selectedParcel.id).map((building) => {
                    const occupancy = buildingOccupancy(data, building.id);
                    return {
                        id: building.id,
                        title: building.name,
                        subtitle: building.reference,
                        meta: [
                            { label: 'Type', value: buildingTypeLabel(building.type) },
                            { label: 'Niveaux', value: building.numberOfFloors !== null ? String(building.numberOfFloors) : '—' },
                            { label: 'Occupation', value: occupancy ? formatPercent(occupancy.occupancyRate) : '—' },
                        ],
                        to: buildPatrimoinePath('unites', { cityId: contextCity?.id ?? selectedCityId, parcelId: selectedParcel.id, buildingId: building.id }),
                    } satisfies PatrimoineChildItem;
                })}
                emptyTitle="Aucun bâtiment"
                emptyDescription="Ajoutez un bâtiment à cette parcelle pour héberger des unités."
                ariaLabel={`Bâtiments de ${selectedParcel.name}`}
            />
            {relatedSection(moduleLinks(selectedParcel.id, null), 'Ces modules sont ouverts filtrés sur cette parcelle.')}
        </>
    );

    /** Vue contextuelle du bâtiment sélectionné : synthèse + unités. */
    const buildingView = selectedBuilding && (
        <>
            <PatrimoineSummaryCard
                eyebrow="BÂTIMENT"
                title={selectedBuilding.name}
                subtitle={contextParcel ? `Parcelle ${contextParcel.name}${contextCity ? ` · ${contextCity.name}` : ''}` : undefined}
                fields={(() => {
                    const buildingUnits = unitsOfBuilding(data, selectedBuilding.id);
                    const occupancy = buildingOccupancy(data, selectedBuilding.id);
                    return [
                        { label: 'Référence', value: selectedBuilding.reference },
                        { label: 'Type', value: buildingTypeLabel(selectedBuilding.type) },
                        { label: 'Niveaux', value: selectedBuilding.numberOfFloors !== null ? String(selectedBuilding.numberOfFloors) : '—' },
                        { label: 'Parcelle', value: contextParcel?.name ?? '—' },
                        { label: 'Ville', value: contextCity?.name ?? '—' },
                        { label: 'Unités', value: formatInteger(unitCount(occupancy, buildingUnits)) },
                        { label: 'Occupées', value: occupancy ? formatInteger(occupancy.occupiedUnits) : '—' },
                        { label: 'Disponibles', value: occupancy ? formatInteger(occupancy.availableUnits) : '—' },
                        { label: 'Publiées', value: formatInteger(buildingUnits.filter((unit) => unit.isPublished).length) },
                        { label: 'Occupation', value: occupancy ? formatPercent(occupancy.occupancyRate) : '—' },
                    ];
                })()}
            >
                <p className="patrimoine-summary__hint">
                    Le loyer affiché par unité est son loyer d’annonce ; les loyers contractuels, les baux et les locataires se consultent via la section « Accès liés ».{buildingOccupancy(data, selectedBuilding.id) === null ? ' Aucune donnée d’occupation pour ce bâtiment : les compteurs proviennent du catalogue chargé et peuvent être partiels.' : ''}
                </p>
            </PatrimoineSummaryCard>
            <PatrimoineChildList
                title="Unités du bâtiment"
                description="Informations principales ; la gestion se fait dans la liste détaillée ci-dessous."
                items={unitsOfBuilding(data, selectedBuilding.id).map((unit) => ({
                    id: unit.id,
                    title: unit.reference,
                    subtitle: `${unitTypeLabel(unit.type)} · niveau ${unit.floor}`,
                    meta: [
                        { label: 'Surface', value: squareMeters(unit.surface) },
                        { label: 'Chambres', value: unit.bedrooms === null ? '—' : formatInteger(unit.bedrooms) },
                        { label: 'Pièces', value: unit.rooms === null ? '—' : formatInteger(unit.rooms) },
                        { label: 'Salles de bain', value: unit.bathrooms === null ? '—' : formatInteger(unit.bathrooms) },
                        { label: 'Loyer d’annonce', value: formatMoney(unit.monthlyRent, unit.currency) },
                        { label: 'Vitrine', value: <Badge variant={unit.isPublished ? 'success' : 'secondary'}>{unit.isPublished ? 'Publiée' : 'Non publiée'}</Badge> },
                    ],
                    to: buildPatrimoinePath('unites', { cityId: contextCity?.id ?? selectedCityId, parcelId: contextParcel?.id ?? selectedParcelId, buildingId: selectedBuilding.id, unitId: unit.id }),
                }))}
                emptyTitle="Aucune unité"
                emptyDescription="Ajoutez une unité à ce bâtiment pour commencer à la louer."
                ariaLabel={`Unités de ${selectedBuilding.name}`}
            />
            {relatedSection(moduleLinks(contextParcel?.id ?? null, selectedBuilding.id), 'Ces modules sont ouverts filtrés sur ce bâtiment.')}
        </>
    );

    /**
     * Champs du bail actif, dérivés uniquement des lectures filtrées serveur
     * (`useUnitContext`). Les échéances sont décomptées à partir des lignes
     * reçues : aucun montant encaissé n'est recalculé ni estimé.
     */
    const leaseFields = (() => {
        const lease = unitContext.activeLease;
        if (!lease) return null;
        const rentSummary = summarizeRents(unitContext.rents);
        const nextRent = nextUnpaidRent(unitContext.rents);
        return [
            { label: 'Locataire', value: unitContext.tenant ? tenantLabel(unitContext.tenant) : (unitContext.tenantForbidden ? 'Accès non autorisé' : '—') },
            { label: 'Référence du bail', value: lease.reference },
            { label: 'Statut du bail', value: <Badge variant={lease.status === 'active' ? 'success' : 'secondary'}>{leaseStatusLabel(lease.status)}</Badge> },
            { label: 'Début', value: formatShortDate(lease.startDate) },
            { label: 'Fin', value: lease.endDate ? formatShortDate(lease.endDate) : 'Indéterminée' },
            { label: 'Loyer contractuel', value: formatMoney(lease.monthlyRent, lease.currency) },
            { label: 'Caution', value: lease.depositAmount ? formatMoney(lease.depositAmount, lease.currency) : '—' },
            {
                label: 'Prochaine échéance à régler',
                value: unitContext.rentsForbidden
                    ? 'Accès non autorisé'
                    : nextRent
                        ? `${formatPeriod(nextRent.period)} · ${formatMoney(nextRent.amount, nextRent.currency)} · ${rentStatusLabel(nextRent.status)}`
                        : unitContext.rents.length > 0 ? 'À jour' : '—',
            },
            {
                label: 'Échéances',
                value: unitContext.rentsForbidden
                    ? 'Accès non autorisé'
                    : `${formatInteger(rentSummary.paid)} / ${formatInteger(rentSummary.total)} payées${rentSummary.overdue > 0 ? ` · ${formatInteger(rentSummary.overdue)} en retard` : ''}`,
            },
        ];
    })();

    const unitLeaseNote = unitContext.leaseForbidden ? (
        <p className="patrimoine-summary__hint">Consultation des baux non autorisée pour votre rôle : les informations de location de cette unité ne sont pas affichées.</p>
    ) : unitContext.isLoading ? (
        <p className="patrimoine-summary__hint">Chargement des informations de location…</p>
    ) : unitContext.error ? (
        <p className="patrimoine-summary__hint">Informations de location indisponibles : {unitContext.error}</p>
    ) : !unitContext.activeLease ? (
        <p className="patrimoine-summary__hint">Aucun bail actif pour cette unité. Les contrats et locataires se consultent via « Accès liés ».</p>
    ) : null;

    /** Vue contextuelle de l'unité sélectionnée : identité + location. */
    const unitView = selectedUnit && (
        <>
            <PatrimoineSummaryCard
                eyebrow="UNITÉ"
                title={selectedUnit.reference}
                subtitle={[selectedBuilding?.name, contextParcel?.name, contextCity?.name].filter(Boolean).join(' · ') || undefined}
                badges={<Badge variant={selectedUnit.isPublished ? 'success' : 'secondary'}>{selectedUnit.isPublished ? 'Annoncée sur la vitrine' : 'Non annoncée'}</Badge>}
                fields={[
                    { label: 'Type', value: unitTypeLabel(selectedUnit.type) },
                    { label: 'Niveau', value: formatInteger(selectedUnit.floor) },
                    { label: 'Surface', value: squareMeters(selectedUnit.surface) },
                    { label: 'Chambres', value: selectedUnit.bedrooms === null ? '—' : formatInteger(selectedUnit.bedrooms) },
                    { label: 'Pièces', value: selectedUnit.rooms === null ? '—' : formatInteger(selectedUnit.rooms) },
                    { label: 'Salles de bain', value: selectedUnit.bathrooms === null ? '—' : formatInteger(selectedUnit.bathrooms) },
                    { label: 'Loyer d’annonce', value: formatMoney(selectedUnit.monthlyRent, selectedUnit.currency) },
                    { label: 'Bâtiment', value: selectedBuilding?.name ?? '—' },
                    { label: 'Parcelle', value: contextParcel?.name ?? '—' },
                    { label: 'Ville', value: contextCity?.name ?? '—' },
                ]}
            />
            <PatrimoineSummaryCard
                eyebrow="LOCATION"
                title="Bail et encaissements"
                subtitle="Le loyer d’annonce est propre à l’unité ; le loyer contractuel, le locataire et les échéances proviennent du bail actif."
                fields={leaseFields ?? undefined}
            >
                {unitLeaseNote}
                {unitContext.rentsTruncated && <p className="patrimoine-summary__hint">Seules les {formatInteger(unitContext.rents.length)} échéances les plus récentes sont prises en compte (liste API bornée).</p>}
            </PatrimoineSummaryCard>
            {relatedSection(moduleLinks(contextParcel?.id ?? null, selectedBuilding?.id ?? null), 'Ces modules sont ouverts filtrés sur ce bâtiment.')}
        </>
    );

    let contextView = null;
    if (dataReady && activeLevel === 'unites' && selectedUnit) contextView = unitView;
    else if (dataReady && activeLevel === 'unites' && selectedBuilding) contextView = buildingView;
    else if (dataReady && activeLevel === 'batiments' && selectedParcel) contextView = parcelView;
    else if (dataReady && activeLevel === 'parcelles' && selectedCity) contextView = cityView;

    const renderTab = (level: PatrimoineLevel) => {
        if (level === 'villes') {
            return <VillesTab cities={cities} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createCity} onCreateAdmin={createCityAdmin} onUpdate={updateCity} onSetStatus={setCityStatus} onDelete={deleteCity} onOpen={(id) => goToLevel('parcelles', id, null, null)} readOnly={readOnly} />;
        }
        if (level === 'parcelles') {
            return <ParcellesTab parcels={parcels} cities={cities} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createParcel} onUpdate={updateParcel} onDelete={deleteParcel} selectedCityId={selectedCityId} onCityFilter={(id) => goToLevel('parcelles', id, null, null)} onOpen={(id) => openParcel(id)} readOnly={readOnly} />;
        }
        if (level === 'batiments') {
            return <BatimentsTab buildings={buildings} parcels={parcels} cities={cities} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createBuilding} onUpdate={updateBuilding} onDelete={deleteBuilding} selectedParcelId={selectedParcelId} onParcelFilter={openParcel} onOpen={(id) => openBuilding(id)} readOnly={readOnly} />;
        }
        return <UnitesTab units={units} buildings={buildings} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createUnit} onUpdate={updateUnit} onDelete={deleteUnit} onPublish={publishUnit} selectedBuildingId={selectedBuildingId} onBuildingFilter={openBuilding} readOnly={readOnly} />;
    };

    const overviewCities = isLoading ? (
        <EmptyState icon={<Spinner size="large" />} title="Chargement des villes…" description="Récupération du patrimoine de l’organisation." />
    ) : error ? (
        <EmptyState title="Villes indisponibles" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />
    ) : (
        <PatrimoineChildList
            title="Villes du portefeuille"
            description="Choisissez une ville pour parcourir son patrimoine."
            items={cities.map((entry) => {
                const occupancy = cityOccupancy(data, entry.id);
                return {
                    id: entry.id,
                    title: entry.name,
                    subtitle: [entry.code, entry.province || entry.country].filter(Boolean).join(' · '),
                    meta: [
                        { label: 'Parcelles', value: formatInteger(parcelsOfCity(data, entry.id).length) },
                        { label: 'Bâtiments', value: formatInteger(buildingsOfCity(data, entry.id).length) },
                        { label: 'Occupation', value: occupancy ? formatPercent(occupancy.occupancyRate) : '—' },
                    ],
                    to: buildPatrimoinePath('parcelles', { cityId: entry.id, parcelId: null, buildingId: null }),
                };
            })}
            emptyTitle="Aucune ville"
            emptyDescription="Ajoutez une première ville pour structurer votre patrimoine."
            ariaLabel="Villes du portefeuille"
        />
    );

    const overviewProperties = (
        <Card className="organization-table-card" padding="medium">
            <div className="organization-table-toolbar">
                <div><h2>Biens du portefeuille</h2><p>{note ?? 'Recherchez et filtrez les parcelles et bâtiments de votre organisation.'}</p></div>
                <div className="organization-filters">
                    <SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un bien…" fullWidth />
                    <Select aria-label="Filtrer par ville" value={city} onChange={(event) => setCity(event.target.value)} options={[{ value: 'all', label: 'Toutes les villes' }, ...(data?.availableCities ?? []).map((value) => ({ value, label: value }))]} />
                    <Select aria-label="Filtrer par type" value={kind} onChange={(event) => setKind(event.target.value)} options={[{ value: 'all', label: 'Tous les types' }, { value: 'Parcelle', label: 'Parcelle' }, { value: 'Bâtiment', label: 'Bâtiment' }]} />
                </div>
            </div>
            {overviewBody}
        </Card>
    );

    let levelContent;
    const contextId = activeLevel === 'parcelles' ? selectedCityId : activeLevel === 'batiments' ? selectedParcelId : activeLevel === 'unites' ? (selectedUnitId ?? selectedBuildingId) : null;
    const hasContext = contextId !== null;
    const managementLabel = activeLevel === 'parcelles' ? 'Gérer les parcelles'
        : activeLevel === 'batiments' ? 'Gérer les bâtiments'
            : activeLevel === 'unites' ? 'Gérer les unités' : 'Gérer le patrimoine';

    if (activeLevel === 'overview') {
        levelContent = (
            <>
                {overviewModules}
                <Tabs
                    key="portfolio-overview"
                    variant="underline"
                    tabs={[{ id: 'cities', label: 'Villes' }, { id: 'properties', label: 'Tous les biens' }]}
                    defaultActiveTabId="cities"
                    renderContent={(tabId) => tabId === 'cities' ? overviewCities : overviewProperties}
                />
            </>
        );
    } else if (hasContext) {
        // Tant que la donnée n'est pas résolue, on garde les onglets stables et
        // on place l'état (chargement / erreur / introuvable) dans l'onglet
        // Aperçu, plutôt que de basculer d'un coup vers le tableau de gestion.
        const contextBody = contextView
            ?? (isLoading
                ? <EmptyState icon={<Spinner size="large" />} title="Chargement du bien…" description="Récupération du patrimoine de l’organisation." />
                : error
                    ? <EmptyState title="Bien indisponible" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />
                    : <EmptyState title="Bien introuvable" description="Ce bien n’existe pas ou n’appartient pas au périmètre de votre organisation." />);
        levelContent = (
            <Tabs
                key={`${activeLevel}:${selectedCityId ?? ''}:${selectedParcelId ?? ''}:${selectedBuildingId ?? ''}:${selectedUnitId ?? ''}`}
                variant="underline"
                tabs={[{ id: 'overview', label: 'Aperçu' }, { id: 'manage', label: managementLabel }]}
                defaultActiveTabId="overview"
                renderContent={(tabId) => tabId === 'overview' ? contextBody : renderTab(activeLevel)}
            />
        );
    } else {
        levelContent = renderTab(activeLevel);
    }

    return (
        <div className="patrimoine-layout">
            <main className="patrimoine-layout__main organization-page organization-operational-page">
                <header className="organization-page__header"><div><span className="organization-page__eyebrow">GESTION IMMOBILIÈRE</span><h1>{isCityAdmin ? 'Biens de ma ville' : 'Patrimoine'}</h1><p>{isCityAdmin ? 'Consultez et gérez les parcelles, bâtiments et unités de vos villes attribuées.' : 'Parcourez les biens de votre portefeuille et gérez chaque niveau : villes, parcelles, bâtiments et unités.'}</p></div></header>
                {!hasContext && <OrganizationSummary items={isCityAdmin ? [{ label: 'Parcelles', value: <>{formatInteger(parcels.length)}</> }, { label: 'Unités gérées', value: <>{formatInteger(data?.totalUnits ?? 0)}</> }, { label: 'Occupation', value: <>{formatPercent(data?.globalOccupancyRate ?? 0)}</> }] : [{ label: 'Villes', value: <>{formatInteger(cities.length)}</> }, { label: 'Unités gérées', value: <>{formatInteger(data?.totalUnits ?? 0)}</> }, { label: 'Occupation globale', value: <>{formatPercent(data?.globalOccupancyRate ?? 0)}</> }]} />}

                {(selectedCity || selectedParcel || selectedBuilding) && <nav className="patrimoine-breadcrumb" aria-label="Fil d’Ariane du patrimoine"><button type="button" onClick={() => goToLevel(isCityAdmin ? 'parcelles' : 'villes', null, null, null)}>{isCityAdmin ? 'Toutes les parcelles' : 'Toutes les villes'}</button>{selectedCity && <><span aria-hidden="true">/</span>{isCityAdmin ? <span>{selectedCity.name}</span> : <button type="button" onClick={() => goToLevel('parcelles', selectedCity.id, null, null)}>{selectedCity.name}</button>}</>}{selectedParcel && <><span aria-hidden="true">/</span><button type="button" onClick={() => goToLevel('batiments', selectedCity?.id ?? selectedParcel.cityId, selectedParcel.id, null)}>{selectedParcel.name}</button></>}{selectedBuilding && <><span aria-hidden="true">/</span><button type="button" onClick={() => goToLevel('unites', selectedCity?.id ?? null, selectedParcel?.id ?? null, selectedBuilding.id)}>{selectedBuilding.name}</button></>}{selectedUnit && <><span aria-hidden="true">/</span><span>{selectedUnit.reference}</span></>}</nav>}

                <div className="patrimoine-level" key={`${activeLevel}:${selectedCityId ?? ''}:${selectedParcelId ?? ''}:${selectedBuildingId ?? ''}:${selectedUnitId ?? ''}`}>
                    {levelContent}
                </div>
            </main>

            <RightSidebar
                title="Explorer le patrimoine"
                size="large"
                collapsible
                collapsedWidth={48}
                className="patrimoine-layout__nav"
                collapsedContent={<PatrimoineNav isCityAdmin={isCityAdmin} selection={selection} cities={cities} parcels={parcels} buildings={buildings} units={units} collapsed />}
            >
                <PatrimoineNav isCityAdmin={isCityAdmin} selection={selection} cities={cities} parcels={parcels} buildings={buildings} units={units} />
            </RightSidebar>

            <Modal
                isOpen={photoParcel !== null}
                onClose={() => setPhotoParcel(null)}
                title={photoParcel ? `Photos — ${photoParcel.name}` : 'Photos de la parcelle'}
                size="large"
                footer={<Button variant="outline" onClick={() => setPhotoParcel(null)}>Fermer</Button>}
            >
                {photoParcel && <PhotoViewer entityUuid={photoParcel.id} entityType="parcel" entityName={photoParcel.name} />}
            </Modal>
        </div>
    );
}
