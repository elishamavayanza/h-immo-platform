import { useEffect, useRef, useState, type ChangeEvent, type FormEvent } from 'react';
import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { organizationsService } from '../../organizations/services/organizationsService';
import type { Publication, PublicationCategory, PublicationDraft } from '../../../shared/publications/types/publication.types';
import { publicationsService } from '../../../shared/publications/services/publicationsService';
import { PublicationContent } from '../../../shared/publications/components/PublicationContent';
import { PlatformPageHeader } from '../../components/PlatformPageHeader';
import { Select } from '../../../../components/Forms/Select';
import { ImageEditor } from '../../../../components/UI/ImageEditor/ImageEditor';
import { RightSidebar } from '../../../../components/Navigation/RightSidebar';
import { ActivityCalendar, activityDateKey, type ActivityCalendarEvent } from '../../../shared/components/ActivityCalendar';
import { CalendarRailPopover } from '../../../shared/components/CalendarRailPopover';
import '../../../shared/publications/components/publications.scss';

const CATEGORY: Record<PublicationCategory, string> = { feature: 'Nouvelle fonctionnalité', improvement: 'Amélioration', correction: 'Correction', maintenance: 'Maintenance', important: 'Annonce importante' };
const EMPTY: PublicationDraft = { title: '', content: '', category: 'feature', audience: 'all', audienceRoles: [], organizationUuid: null, scheduledAt: null, endsAt: null };
const toIso = (value: string) => value ? new Date(value).toISOString() : null;
interface ImageToCrop { file: File; src: string; }

export function PublicationsPage() {
    const { push } = useToast();
    const [items, setItems] = useState<Publication[]>([]);
    const [organizations, setOrganizations] = useState<Array<{ id: string; name: string }>>([]);
    const [draft, setDraft] = useState<PublicationDraft>(EMPTY);
    const [editing, setEditing] = useState<string | null>(null);
    const [savedDraft, setSavedDraft] = useState<Publication | null>(null);
    const [files, setFiles] = useState<File[]>([]);
    const [captions, setCaptions] = useState<string[]>([]);
    const [imageToCrop, setImageToCrop] = useState<ImageToCrop | null>(null);
    const [cropQueue, setCropQueue] = useState<File[]>([]);
    const [selectedHistoryDate, setSelectedHistoryDate] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const imageInputRef = useRef<HTMLInputElement>(null);
    const reload = async () => { setLoading(true); try { setItems(await publicationsService.management()); } catch { push('error', 'Impossible de charger les publications.'); } finally { setLoading(false); } };
    useEffect(() => { void reload(); void organizationsService.list().then(rows => setOrganizations(rows.map(row => ({ id: row.id, name: row.name })))).catch(() => undefined); }, []);
    const beginEdit = (item: Publication) => {
        setEditing(item.uuid); setSavedDraft(item);
        setDraft({ title: item.title, content: item.contentBlocks.map(block => block.type === 'bullet' ? `- ${block.text}` : block.type === 'ordered' ? `1. ${block.text}` : block.text).join('\n'), category: item.category, audience: item.audience, audienceRoles: item.audienceRoles.filter(role => role !== 'super_admin'), organizationUuid: item.organization?.uuid ?? null, scheduledAt: item.scheduledAt?.slice(0, 16) ?? null, endsAt: item.endsAt?.slice(0, 16) ?? null });
    };
    const save = async (event: FormEvent) => {
        event.preventDefault(); if (busy) return; setBusy(true);
        try {
            let item = await publicationsService.saveDraft({ ...draft, scheduledAt: toIso(draft.scheduledAt ?? ''), endsAt: toIso(draft.endsAt ?? '') }, editing ?? undefined);
            if (files.length) item = await publicationsService.uploadImages(item.uuid, files, captions);
            setSavedDraft(item); setEditing(item.uuid); setFiles([]); setCaptions([]); push('success', `Le brouillon « ${item.title} » a été enregistré. Il n’a pas encore été diffusé.`); await reload();
        } catch (error) { push('error', error instanceof Error ? error.message : 'Échec de l’enregistrement du brouillon.'); }
        finally { setBusy(false); }
    };
    const publish = async (uuid: string, title: string) => { if (busy) return; setBusy(true); try { await publicationsService.publish(uuid); push('success', `La publication « ${title} » a été diffusée aux destinataires sélectionnés.`); setSavedDraft(null); setEditing(null); setDraft(EMPTY); await reload(); } catch (error) { push('error', error instanceof Error ? `Impossible de diffuser la publication « ${title} » : ${error.message}` : `Impossible de diffuser la publication « ${title} ».`); } finally { setBusy(false); } };
    const statusAction = async (item: Publication, action: 'archive' | 'withdraw') => { try { await publicationsService[action](item.uuid); push('success', action === 'archive' ? `La publication « ${item.title} » a été archivée.` : `La publication « ${item.title} » a été retirée de la diffusion.`); await reload(); } catch (error) { push('error', error instanceof Error ? `Impossible de modifier la publication « ${item.title} » : ${error.message}` : `Impossible de modifier la publication « ${item.title} ».`); } };
    const toggleRole = (role: string) => setDraft(current => ({ ...current, audienceRoles: current.audienceRoles.includes(role) ? current.audienceRoles.filter(value => value !== role) : [...current.audienceRoles, role] }));
    const startCropping = (file: File, queue: File[] = []) => {
        setCropQueue(queue);
        setImageToCrop({ file, src: URL.createObjectURL(file) });
    };
    const finishCropping = (croppedFile?: File) => {
        if (imageToCrop) URL.revokeObjectURL(imageToCrop.src);
        if (croppedFile && imageToCrop) {
            const stem = imageToCrop.file.name.replace(/\.[^.]+$/, '') || 'publication';
            const namedFile = new File([croppedFile], `${stem}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
            setFiles(current => [...current, namedFile]);
            setCaptions(current => [...current, '']);
        }
        const [next, ...remaining] = cropQueue;
        if (next) setImageToCrop({ file: next, src: URL.createObjectURL(next) });
        else setImageToCrop(null);
        setCropQueue(remaining);
    };
    const handleImageSelection = (event: ChangeEvent<HTMLInputElement>) => {
        const selected = Array.from(event.target.files ?? []);
        event.target.value = '';
        if (!selected.length) return;
        const capacity = Math.max(0, 8 - (savedDraft?.images.length ?? 0) - files.length);
        if (capacity === 0) {
            push('error', 'Cette publication contient déjà le maximum de 8 images.');
            return;
        }
        const candidates = selected.slice(0, capacity);
        if (selected.length > capacity) push('error', `Vous pouvez ajouter au maximum ${capacity} image(s) à cette publication.`);
        const invalid = candidates.find(file => !file.type.startsWith('image/') || file.size > 10 * 1024 * 1024);
        if (invalid) {
            push('error', !invalid.type.startsWith('image/') ? 'Le fichier doit être une image.' : 'Chaque image ne doit pas dépasser 10 Mo.');
            return;
        }
        if (imageToCrop) URL.revokeObjectURL(imageToCrop.src);
        startCropping(candidates[0], candidates.slice(1));
    };
    const historyEvents: ActivityCalendarEvent[] = items.map(item => ({
        date: item.publishedAt ?? item.scheduledAt ?? item.createdAt,
        title: item.title,
        detail: `${item.status} · ${item.recipientCount ?? 0} destinataire(s)`,
        tone: item.status === 'published' ? 'success' : item.status === 'withdrawn' ? 'warning' : item.status === 'archived' ? 'error' : 'info',
    }));
    const visibleHistoryItems = selectedHistoryDate
        ? items.filter(item => activityDateKey(item.publishedAt ?? item.scheduledAt ?? item.createdAt) === selectedHistoryDate)
        : items;
    const historyCalendar = <ActivityCalendar
        events={historyEvents}
        label="Calendrier de l’historique des publications"
        selectedDate={selectedHistoryDate}
        onSelectDate={setSelectedHistoryDate}
        onClearDate={() => setSelectedHistoryDate(null)}
        onMonthChange={() => setSelectedHistoryDate(null)}
        description="Les dates marquées correspondent aux publications créées, programmées ou diffusées. Sélectionnez une date pour filtrer l’historique."
        emptyMessage="Aucune publication associée à cette date."
    />;

    return <>
        <main className="publication-admin">
        <PlatformPageHeader icon="briefcase" title="Publications" description="Préparez les annonces, contrôlez leur aperçu puis choisissez séparément quand les diffuser." />
        <section className="publication-admin__stats" aria-label="Statistiques des publications">
            <article><span>Publications</span><strong>{items.length}</strong></article><article><span>Destinataires</span><strong>{items.reduce((sum, item) => sum + (item.recipientCount ?? 0), 0)}</strong></article><article><span>Consultations</span><strong>{items.reduce((sum, item) => sum + (item.viewCount ?? 0), 0)}</strong></article><article><span>Expirées</span><strong>{items.reduce((sum, item) => sum + (item.expiredCount ?? 0), 0)}</strong></article>
        </section>
        <div className="publication-admin__grid">
            <form className="publication-admin__editor" onSubmit={save}>
                <h2>{editing ? 'Modifier le brouillon' : 'Nouvelle publication'}</h2>
                <label>Titre<input required maxLength={180} value={draft.title} onChange={e => setDraft({ ...draft, title: e.target.value })} /></label>
                <label>Contenu <small>Une ligne par paragraphe ; utilisez « - » ou « 1. » pour les listes.</small><textarea required rows={8} maxLength={20000} value={draft.content} onChange={e => setDraft({ ...draft, content: e.target.value })} /></label>
                <div className="publication-admin__fields"><label>Catégorie<Select aria-label="Catégorie" value={draft.category} onChange={e => setDraft({ ...draft, category: e.target.value as PublicationCategory })} options={Object.entries(CATEGORY).map(([value, label]) => ({ value, label }))} fullWidth /></label>
                    <label>Public cible<Select aria-label="Public cible" value={draft.audience} onChange={e => setDraft({ ...draft, audience: e.target.value as PublicationDraft['audience'] })} options={[{ value: 'all', label: 'Tous les utilisateurs métier actifs' }, { value: 'roles', label: 'Rôles sélectionnés' }, { value: 'organization', label: 'Une organisation' }]} fullWidth /></label></div>
                {draft.audience === 'roles' && <fieldset className="publication-admin__roles"><legend>Rôles destinataires</legend>{[['patron','Patron'],['admin_immobilier','Administrateur immobilier'],['admin_ville','Administrateur de ville']].map(([role, label]) => <label key={role}><input type="checkbox" checked={draft.audienceRoles.includes(role)} onChange={() => toggleRole(role)} />{label}</label>)}</fieldset>}
                {draft.audience === 'organization' && <label>Organisation<Select aria-label="Organisation" required value={draft.organizationUuid ?? ''} onChange={e => setDraft({ ...draft, organizationUuid: e.target.value || null })} options={organizations.map(org => ({ value: org.id, label: org.name }))} placeholder="Sélectionner…" fullWidth /></label>}
                <div className="publication-admin__fields"><label>Publier à partir du<input type="datetime-local" value={draft.scheduledAt ?? ''} onChange={e => setDraft({ ...draft, scheduledAt: e.target.value || null })} /></label><label>Fin de diffusion (facultatif)<input type="datetime-local" value={draft.endsAt ?? ''} onChange={e => setDraft({ ...draft, endsAt: e.target.value || null })} /></label></div>
                <div className="publication-admin__image-upload"><div><strong>Images (maximum 8 au total)</strong><small>Chaque image s’ouvre dans l’outil de recadrage avant son ajout. Glissez-la, ajustez le zoom ou faites-la pivoter.</small></div><div className="publication-admin__image-upload-action"><input ref={imageInputRef} className="publication-admin__file-input" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple disabled={busy || Boolean(imageToCrop)} onChange={handleImageSelection} /><button type="button" className="publication-admin__upload-button is-secondary" disabled={busy || Boolean(imageToCrop)} onClick={() => imageInputRef.current?.click()}><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m0 0L7 9m5-5 5 5M5 14v5h14v-5" /></svg>Ajouter des images</button><span>JPEG, PNG, WebP ou GIF · 10 Mo max. par image</span></div></div>
                {imageToCrop && <div className="publication-admin__cropper" aria-label="Recadrer l’image de publication">
                    <h3>Recadrer — {imageToCrop.file.name}{cropQueue.length > 0 ? ` (${files.length + 1} sur ${files.length + cropQueue.length + 1})` : ''}</h3>
                    <p>Déplacez la photo dans le cadre, réglez le zoom et la rotation, puis appliquez le cadrage.</p>
                    <ImageEditor src={imageToCrop.src} shape="square" outputSize={600} onCancel={() => finishCropping()} onApply={(_dataUrl, file) => finishCropping(file)} />
                </div>}
                {files.length > 0 && <p className="publication-admin__image-count">{files.length} image(s) recadrée(s), prête(s) à être ajoutée(s) au brouillon.</p>}
                {files.map((file, index) => <label key={`${file.name}-${index}`}>Légende — {file.name}<input value={captions[index] ?? ''} maxLength={240} onChange={e => setCaptions(current => current.map((caption, i) => i === index ? e.target.value : caption))} /></label>)}
                <div className="publication-admin__actions"><button type="submit" disabled={busy || Boolean(imageToCrop)}>{busy ? 'Enregistrement…' : 'Enregistrer le brouillon'}</button><button type="button" className="is-secondary" onClick={() => { setDraft(EMPTY); setEditing(null); setSavedDraft(null); setFiles([]); setCaptions([]); setCropQueue([]); if (imageToCrop) URL.revokeObjectURL(imageToCrop.src); setImageToCrop(null); }}>Nouveau</button>{savedDraft?.status === 'draft' && <button type="button" disabled={busy || Boolean(imageToCrop)} onClick={() => void publish(savedDraft.uuid, savedDraft.title)}>Publier maintenant</button>}</div>
            </form>
            <aside className="publication-admin__preview"><h2>Aperçu</h2>{savedDraft ? <article className="publication-admin__preview-card"><span>{CATEGORY[draft.category]}</span><h3>{draft.title || 'Titre de la publication'}</h3><PublicationContent publication={{ ...savedDraft, title: draft.title || 'Titre de la publication', category: draft.category, contentBlocks: draft.content.split(/\r?\n/).filter(Boolean).map(line => ({ type: line.startsWith('- ') ? 'bullet' : line.match(/^\d+[.)]\s/) ? 'ordered' : 'paragraph', text: line.replace(/^[-*]\s+|^\d+[.)]\s+/, '') } as const)), images: savedDraft.images }} /></article> : <p>Enregistrez le brouillon pour obtenir un aperçu fidèle, avec ses images.</p>}</aside>
        </div>
        <section className="publication-admin__history"><header><div><h2>Historique des publications</h2><p>Brouillons, annonces programmées, publications diffusées et archives.</p>{selectedHistoryDate && <div className="publication-admin__history-filter"><span>Filtré au {selectedHistoryDate}</span><button type="button" className="is-secondary" onClick={() => setSelectedHistoryDate(null)}>Effacer le filtre</button></div>}</div><button type="button" className="is-secondary" onClick={() => void reload()}>Actualiser</button></header>
            {loading ? <p>Chargement…</p> : items.length === 0 ? <p>Aucune publication pour le moment.</p> : visibleHistoryItems.length === 0 ? <p>Aucune publication trouvée à cette date.</p> : <div className="publication-admin__list">{visibleHistoryItems.map(item => <article key={item.uuid}><div className="publication-admin__item-main"><span className={`publication-admin__status status-${item.status}`}>{item.status}</span><span>{CATEGORY[item.category]}</span><h3>{item.title}</h3><p>{item.audience === 'organization' ? item.organization?.name : item.audience === 'roles' ? item.audienceRoles.join(', ') : 'Tous les utilisateurs'} · {item.recipientCount ?? 0} destinataires · {item.viewCount ?? 0} vues{item.expiredCount ? ` · ${item.expiredCount} expirées` : ''}</p></div><div className="publication-admin__item-actions">{item.status === 'draft' && <><button type="button" className="is-secondary" onClick={() => beginEdit(item)}>Modifier</button><button type="button" onClick={() => void publish(item.uuid, item.title)}>Publier</button></>}{item.status === 'published' && <><button type="button" className="is-secondary" onClick={() => void statusAction(item, 'withdraw')}>Retirer</button><button type="button" className="is-secondary" onClick={() => void statusAction(item, 'archive')}>Archiver</button></>}</div></article>)}</div>}
        </section>
        </main>
        <RightSidebar title="Historique des publications" size="medium" variant="dark" collapsible collapsedWidth={48} collapsedContent={<div className="financial-calendar-rail"><CalendarRailPopover title="Historique des publications" label="Ouvrir le calendrier des publications">{historyCalendar}</CalendarRailPopover></div>}>
            {historyCalendar}
        </RightSidebar>
    </>;
}
