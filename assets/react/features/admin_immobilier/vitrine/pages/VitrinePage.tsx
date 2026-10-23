import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ListingWorkflowPanel } from '../components/ListingWorkflowPanel';

/** Vitrine de gestion. ADMIN_VILLE soumet les annonces ; ADMIN_IMMOBILIER les contrôle et les publie. */
export function VitrinePage({ readOnly = false }: { readOnly?: boolean } = {}) {
    const { organizationRole } = useOrganization();
    return <main className="organization-page organization-operational-page">
        <header className="organization-page__header"><div><span className="organization-page__eyebrow">PRÉSENCE EN LIGNE</span><h1>Vitrine</h1><p>{organizationRole === 'admin_ville' ? 'Préparez les annonces des unités de la ville active et transmettez-les au contrôle immobilier.' : 'Contrôlez les annonces soumises par les villes et gérez leur publication.'}</p></div></header>
        <ListingWorkflowPanel role={organizationRole} readOnly={readOnly} />
    </main>;
}
