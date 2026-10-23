import { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { fetchCustomerInquiryAlerts, markCustomerInquiryAlertRead } from '../../customer_inquiries/services/customerInquiryService';
import type { CustomerInquiryAlert } from '../../customer_inquiries/services/customerInquiryService';
import { publicationsService } from '../services/publicationsService';
import type { Publication } from '../types/publication.types';
import './publications.scss';

const CATEGORY_LABEL: Record<string, string> = { feature: 'Nouvelle fonctionnalité', improvement: 'Amélioration', correction: 'Correction', maintenance: 'Maintenance', important: 'Annonce importante' };

export function PublicationNotifications({ disabled = false }: { disabled?: boolean }) {
    const [items, setItems] = useState<Publication[]>([]);
    const [open, setOpen] = useState(false);
    const [loadError, setLoadError] = useState(false);
    const [refreshVersion, setRefreshVersion] = useState(0);
    const [inquiryAlerts, setInquiryAlerts] = useState<CustomerInquiryAlert[]>([]);
    const [inquiryUnread, setInquiryUnread] = useState(0);
    const navigate = useNavigate();
    const location = useLocation();
    const { currentOrganization, organizationRole } = useOrganization();
    useEffect(() => {
        let active = true;
        if (disabled) {
            setItems([]);
            setOpen(false);
            return () => { active = false; };
        }
        const load = async () => {
            try { const rows = await publicationsService.active(); if (active) { setItems(rows); setLoadError(false); } }
            catch { if (active) setLoadError(true); }
            if (organizationRole === 'admin_ville' && currentOrganization?.uuid) {
                try {
                    const inbox = await fetchCustomerInquiryAlerts(currentOrganization.uuid);
                    if (active) { setInquiryAlerts(inbox.items); setInquiryUnread(inbox.unread); }
                } catch { /* Le centre de publications reste utilisable si la file ville est indisponible. */ }
            } else if (active) { setInquiryAlerts([]); setInquiryUnread(0); }
        };
        const refreshAfterRead = () => { void load(); };
        void load();
        window.addEventListener('soft-immo:publication-read', refreshAfterRead);
        const timer = window.setInterval(() => { void load(); }, organizationRole === 'admin_ville' ? 30_000 : 60_000);
        return () => { active = false; window.clearInterval(timer); window.removeEventListener('soft-immo:publication-read', refreshAfterRead); };
    }, [refreshVersion, disabled, location.pathname, currentOrganization?.uuid, organizationRole]);
    const unread = items.filter(item => !item.firstViewedAt).length + inquiryUnread;
    if (disabled || (items.length === 0 && inquiryAlerts.length === 0)) return null;

    return <div className="publication-notifications">
        <button className="publication-notifications__trigger" type="button" aria-label={`Notifications${unread ? `, ${unread} non lue(s)` : ''}`} aria-expanded={open} onClick={() => setOpen(value => !value)}>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
            {unread > 0 && <span className="publication-notifications__count">{unread > 99 ? '99+' : unread}</span>}
        </button>
        {open && <section className="publication-notifications__panel" aria-label="Centre de notifications">
            <header><strong>Notifications</strong><button type="button" onClick={() => setOpen(false)} aria-label="Fermer">×</button></header>
            {inquiryAlerts.map(alert => <button key={`inquiry-${alert.uuid}`} className={`publication-notifications__item${alert.readAt ? '' : ' is-unread'}`} type="button" onClick={() => {
                void (async () => {
                    try {
                        if (currentOrganization?.uuid) await markCustomerInquiryAlertRead(currentOrganization.uuid, alert.uuid);
                        setInquiryAlerts(previous => previous.map(item => item.uuid === alert.uuid ? { ...item, readAt: new Date().toISOString() } : item));
                        setInquiryUnread(previous => alert.readAt === null ? Math.max(0, previous - 1) : previous);
                    } catch { /* L’accès direct reste possible si le reçu de lecture est temporairement indisponible. */ }
                    setOpen(false);
                    navigate(`/app/demandes-clients?demande=${encodeURIComponent(alert.inquiryUuid)}`);
                })();
            }}>
                <span className="publication-notifications__category">Demande de votre ville</span><strong>{alert.listingTitle}</strong><span>{alert.city}</span><time dateTime={alert.createdAt}>{new Date(alert.createdAt).toLocaleString('fr-FR')}</time>
            </button>)}
            {loadError ? <div className="publication-notifications__empty" role="status"><p>Les publications sont momentanément indisponibles.</p><button className="publication-notifications__retry" type="button" onClick={() => setRefreshVersion(value => value + 1)}>Réessayer</button></div> : items.map(item => <button key={item.uuid} className={`publication-notifications__item${item.firstViewedAt ? '' : ' is-unread'}`} type="button" onClick={() => { setOpen(false); navigate(`/app/publications/${item.uuid}`); }}>
                <span className="publication-notifications__category">{CATEGORY_LABEL[item.category] ?? item.category}</span><strong>{item.title}</strong><time dateTime={item.publishedAt ?? item.createdAt}>{new Date(item.publishedAt ?? item.createdAt).toLocaleDateString('fr-FR')}</time><span>Lire la publication →</span>
            </button>)}
            {!loadError && items.length === 0 && inquiryAlerts.length === 0 && <p className="publication-notifications__empty">Aucune nouvelle notification.</p>}
        </section>}
    </div>;
}
