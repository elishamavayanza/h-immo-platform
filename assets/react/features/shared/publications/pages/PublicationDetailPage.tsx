import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { defaultPathFor } from '../../../../app/layout/MainLayout/sidebar/sidebar.config';
import { publicationsService } from '../services/publicationsService';
import type { Publication } from '../types/publication.types';
import { PublicationContent } from '../components/PublicationContent';
import '../components/publications.scss';

const LABEL: Record<string, string> = { feature: 'Nouvelle fonctionnalité', improvement: 'Amélioration', correction: 'Correction', maintenance: 'Maintenance', important: 'Annonce importante' };
export function PublicationDetailPage() {
    const { uuid = '' } = useParams();
    const { platformRole, organizationRole } = useOrganization();
    const returnPath = defaultPathFor(platformRole, organizationRole);
    const [publication, setPublication] = useState<Publication | null>(null);
    const [error, setError] = useState(false);
    useEffect(() => { let active = true; void publicationsService.open(uuid).then(item => { if (active) { setPublication(item); window.dispatchEvent(new Event('soft-immo:publication-read')); } }).catch(() => { if (active) setError(true); }); return () => { active = false; }; }, [uuid]);
    if (error) return <main className="publication-detail-page"><section className="publication-detail publication-detail--state"><h1>Publication indisponible</h1><p>Elle a peut-être expiré ou a été retirée.</p><Link className="publication-detail__back" to={returnPath}>← Retour à l’application</Link></section></main>;
    if (!publication) return <main className="publication-detail-page"><section className="publication-detail publication-detail--state" role="status">Chargement de la publication…</section></main>;
    return <main className="publication-detail-page"><article className="publication-detail">
        <Link className="publication-detail__back" to={returnPath}><span aria-hidden="true">←</span> Retour à l’application</Link>
        <header className="publication-detail__header">
            <div className="publication-detail__meta"><span className="publication-detail__category">{LABEL[publication.category]}</span><time dateTime={publication.publishedAt ?? publication.createdAt}>{new Date(publication.publishedAt ?? publication.createdAt).toLocaleString('fr-FR')}</time></div>
            <h1>{publication.title}</h1>
        </header>
        <PublicationContent publication={publication} />
    </article></main>;
}
