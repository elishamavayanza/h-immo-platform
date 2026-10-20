import { useCallback, useEffect, useState } from 'react';
import { ApiError } from '../../../../../services/api/api.types';
import { Button } from '../../../../components/UI/Button';
import { Modal } from '../../../../components/UI/Modal/Modal';
import { ConfirmDialog } from '../../../../components/UI/ConfirmDialog/ConfirmDialog';
import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { deletePrivateDocument, downloadPrivateDocument, listPrivateDocuments, uploadPrivateDocument, type PrivateDocumentItem } from '../services/privateDocumentsService';

interface Props {
    open: boolean;
    onClose: () => void;
    organizationUuid: string;
    relatedType: 'lease' | 'payment' | 'expense';
    relatedUuid: string;
    cityUuid?: string;
    canUpload: boolean;
    canDelete: boolean;
}

export function PrivateDocumentsModal({ open, onClose, organizationUuid, relatedType, relatedUuid, cityUuid, canUpload, canDelete }: Props) {
    const [items, setItems] = useState<PrivateDocumentItem[]>([]);
    const [file, setFile] = useState<File | null>(null);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [pendingDelete, setPendingDelete] = useState<PrivateDocumentItem | null>(null);
    const { push } = useToast();

    const reload = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            setItems(await listPrivateDocuments(organizationUuid, relatedType, relatedUuid, cityUuid));
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les pièces jointes.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid, relatedType, relatedUuid, cityUuid]);

    useEffect(() => { if (open) void reload(); }, [open, reload]);

    const upload = async () => {
        if (!file) return;
        setSaving(true);
        try {
            await uploadPrivateDocument(organizationUuid, relatedType, relatedUuid, file);
            setFile(null);
            const relatedLabel = relatedType === 'lease' ? 'au bail' : relatedType === 'payment' ? 'au paiement' : 'à la dépense';
            push('success', `Le document « ${file.name} » a été joint ${relatedLabel}.`);
            await reload();
        } catch (cause) {
            push('error', cause instanceof ApiError ? cause.message : 'Échec de l’ajout du document.');
        } finally {
            setSaving(false);
        }
    };

    const remove = async () => {
        if (!pendingDelete) return;
        try {
            await deletePrivateDocument(pendingDelete.uuid);
            push('success', `Le document « ${pendingDelete.originalName} » a été supprimé des pièces justificatives.`);
            setPendingDelete(null);
            await reload();
        } catch (cause) {
            push('error', cause instanceof ApiError ? cause.message : 'Échec de la suppression.');
        }
    };

    return <Modal isOpen={open} onClose={onClose} title="Pièces justificatives" size="medium">
        <ConfirmDialog isOpen={pendingDelete !== null} onClose={() => setPendingDelete(null)} onConfirm={() => void remove()} title="Supprimer cette pièce ?" message="Cette suppression est définitive et sera inscrite au journal d’audit." confirmLabel="Supprimer" />
        <div className="private-documents-modal">
            <p>Les fichiers sont conservés en espace privé et accessibles selon les droits de votre organisation.</p>
            {canUpload && <form onSubmit={(event) => { event.preventDefault(); void upload(); }}>
                <label>Ajouter un document (PDF, JPEG, PNG ou WebP, 15 Mo maximum)
                    <input type="file" accept="application/pdf,image/jpeg,image/png,image/webp" onChange={(event) => setFile(event.target.files?.[0] ?? null)} />
                </label>
                <Button type="submit" disabled={!file || saving}>{saving ? 'Envoi…' : 'Ajouter'}</Button>
            </form>}
            {error && <div role="alert"><p>{error}</p><Button type="button" variant="secondary" onClick={() => void reload()}>Réessayer</Button></div>}
            {loading ? <p role="status">Chargement des documents…</p> : items.length === 0 ? <p>Aucune pièce jointe pour cet élément.</p> : <ul>
                {items.map((item) => <li key={item.uuid}>
                    <span><strong>{item.originalName}</strong><small> · {(item.fileSize / 1024).toFixed(0)} Ko · {item.uploadedBy}</small></span>
                    <Button type="button" variant="secondary" onClick={() => void downloadPrivateDocument(item.uuid, item.originalName)}>Télécharger</Button>
                    {canDelete && <Button type="button" variant="secondary" onClick={() => setPendingDelete(item)}>Supprimer</Button>}
                </li>)}
            </ul>}
        </div>
    </Modal>;
}
