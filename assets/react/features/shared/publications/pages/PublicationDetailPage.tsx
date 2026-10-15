import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { publicationsService } from '../services/publicationsService';
import type { Publication } from '../types/publication.types';
import { PublicationContent } from '../components/PublicationContent';
import '../components/publications.scss';

const LABEL: Record<string, string> = { feature: 'Nouvelle fonctionnalité', improvement: 'Amélioration', correction: 'Correction', maintenance: 'Maintenance', important: 'Annonce importante' };
export function PublicationDetailPage() {
    const { uuid = '' } = useParams();
    const [publication, setPublication] = useState<Publication | null>(null);
    const [error, setError] = useState(false);
    useEffect(() => { let active = true; void publicationsService.open(uuid).then(item => { if (active) setPublication(item); }).catch(() => { if (active) setError(true); }); return () => { active = false; }; }, [uuid]);
    if (error) return <section className="publication-detail"><h1>Publication indisponible</h1><p>Elle a peut-être expiré ou a été retirée.</p><Link to="/app/dashboard">Retour au tableau de bord</Link></section>;
    if (!publication) return <section className="publication-detail">Chargement de la publication…</section>;
    return <article className="publication-detail"><Link to="/app/dashboard">← Retour à l’application</Link><span>{LABEL[publication.category]}</span><h1>{publication.title}</h1><time dateTime={publication.publishedAt ?? publication.createdAt}>{new Date(publication.publishedAt ?? publication.createdAt).toLocaleString('fr-FR')}</time><PublicationContent publication={publication} /></article>;
}
