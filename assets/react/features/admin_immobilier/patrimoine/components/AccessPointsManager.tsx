import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Button } from '../../../../components/UI/Button';
import { FormField } from '../../../../components/Forms/FormField';
import { Input } from '../../../../components/Forms/Input';
import { Select } from '../../../../components/Forms/Select';
import { Textarea } from '../../../../components/Forms/Textarea';
import { Modal } from '../../../../components/UI/Modal';
import { ModalActions } from '../../../shared/components/ModalActions';
import { fieldErrorMap } from '../../../shared/utils/actionErrors';
import type { UnitItem } from '../../shared/types/reference.types';
import { createAccessPoint, fetchAccessPoints, setAccessPointEnabled, updateAccessPoint, type AccessPointItem } from '../services/patrimoineService';
import '../../../../../styles/pages/admin_immobilier/patrimoine/_access-points.scss';

const TYPE_OPTIONS = [
    ['main_entry', 'Entrée principale'], ['private_entry', 'Entrée privée'], ['shared_entry', 'Entrée commune'],
    ['rear', 'Porte arrière'], ['service', 'Porte de service'], ['staircase', 'Escalier commun'],
    ['parking', 'Accès parking'], ['technical', 'Accès technique'], ['other', 'Autre'],
].map(([value, label]) => ({ value, label }));

interface FormState { reference: string; label: string; type: string; accessMode: 'private' | 'shared' | 'restricted'; locationDescription: string; unitIds: string[] }
const EMPTY: FormState = { reference: '', label: '', type: 'shared_entry', accessMode: 'shared', locationDescription: '', unitIds: [] };

export function AccessPointsManager({ open, buildingId, parcelId, buildingName, units, canManage, onClose }: {
    open: boolean; buildingId: string | null; parcelId: string; buildingName: string; units: UnitItem[]; canManage: boolean; onClose: () => void;
}) {
    const [items, setItems] = useState<AccessPointItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [form, setForm] = useState<FormState>(EMPTY);
    const [edit, setEdit] = useState<AccessPointItem | null>(null);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const reload = useCallback(async () => {
        if (!open || !parcelId) return;
        setLoading(true); setError('');
        try { setItems(await fetchAccessPoints({ ...(buildingId ? { buildingUuid: buildingId } : {}), parcelUuid: parcelId })); }
        catch (cause) { setError(cause instanceof Error ? cause.message : 'Les accès n’ont pas pu être chargés.'); }
        finally { setLoading(false); }
    }, [open, buildingId, parcelId]);
    useEffect(() => { void reload(); }, [reload]);

    const reset = () => { setEdit(null); setForm(EMPTY); setErrors({}); };
    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault(); if (saving) return;
        setSaving(true); setErrors({});
        const targetBuildingId = edit ? edit.buildingId : buildingId;
        const payload = { ...(targetBuildingId ? { buildingUuid: targetBuildingId } : {}), parcelUuid: parcelId, reference: form.reference.trim(), label: form.label.trim(), type: form.type, accessMode: form.accessMode, locationDescription: form.locationDescription.trim() || null, unitUuids: form.unitIds };
        try {
            if (edit) await updateAccessPoint(edit.id, payload);
            else await createAccessPoint(payload);
            reset(); await reload();
        } catch (cause) { setErrors(fieldErrorMap(cause)); }
        finally { setSaving(false); }
    };

    const editItem = (item: AccessPointItem) => {
        setEdit(item); setForm({ reference: item.reference, label: item.label, type: item.type, accessMode: item.accessMode, locationDescription: item.locationDescription ?? '', unitIds: item.unitIds }); setErrors({});
    };

    return <Modal isOpen={open} onClose={onClose} title={`Accès · ${buildingName}`} size="large" footer={canManage ? <ModalActions formId="access-point-form" onCancel={reset} submitLabel={edit ? 'Enregistrer l’accès' : 'Ajouter l’accès'} loadingLabel="Enregistrement…" isLoading={saving} /> : undefined}>
        <div className="access-manager">
            <section className="access-manager__list" aria-label="Accès enregistrés">
                <h3>Accès enregistrés</h3>
                {loading ? <p>Chargement…</p> : error ? <div role="alert">{error} <Button variant="outline" onClick={() => void reload()}>Réessayer</Button></div> : items.length === 0 ? <p>Aucun accès configuré. Les entrées restent facultatives.</p> : items.map((item) => <article className="access-manager__item" key={item.id}>
                    <div><strong>{item.label}</strong><small>{item.reference} · {TYPE_OPTIONS.find((option) => option.value === item.type)?.label ?? item.type} · {item.accessMode === 'shared' ? 'Partagé' : item.accessMode === 'private' ? 'Privé' : 'Accès réservé'}</small><small>{item.unitIds.length ? item.unitIds.map((id) => units.find((unit) => unit.id === id)?.reference ?? 'Espace').join(', ') : item.buildingId ? 'Accès du bâtiment' : 'Entrée de la parcelle'}</small></div>
                    <div className="access-manager__actions">{canManage && (item.buildingId === buildingId) && <Button variant="outline" onClick={() => editItem(item)}>Modifier</Button>}{canManage && <Button variant="outline" onClick={async () => { await setAccessPointEnabled(item.id, !item.enabled); await reload(); }}>{item.enabled ? 'Désactiver' : 'Réactiver'}</Button>}{!item.enabled && <span className="access-manager__disabled">Désactivé</span>}</div>
                </article>)}
            </section>
            {canManage && <form id="access-point-form" className="access-manager__form" onSubmit={submit}>
                <h3>{edit ? 'Modifier un accès' : 'Nouvel accès'}</h3>
                <div className="access-manager__fields">
                    <FormField label="Référence" htmlFor="access-reference" required error={errors.reference}><Input id="access-reference" value={form.reference} onChange={(event) => setForm((current) => ({ ...current, reference: event.target.value }))} maxLength={50} required fullWidth /></FormField>
                    <FormField label="Libellé" htmlFor="access-label" required error={errors.label}><Input id="access-label" value={form.label} onChange={(event) => setForm((current) => ({ ...current, label: event.target.value }))} placeholder="Entrée principale" maxLength={120} required fullWidth /></FormField>
                    <FormField label="Type d’accès" htmlFor="access-type" required error={errors.type}><Select id="access-type" value={form.type} onChange={(event) => setForm((current) => ({ ...current, type: event.target.value }))} options={TYPE_OPTIONS} /></FormField>
                    <FormField label="Usage" htmlFor="access-mode" required error={errors.accessMode}><Select id="access-mode" value={form.accessMode} onChange={(event) => setForm((current) => ({ ...current, accessMode: event.target.value as FormState['accessMode'] }))} options={[{ value: 'shared', label: 'Partagé' }, { value: 'private', label: 'Privé' }, { value: 'restricted', label: 'Réservé' }]} /></FormField>
                </div>
                <FormField label="Emplacement (facultatif)" htmlFor="access-location" error={errors.locationDescription}><Textarea id="access-location" value={form.locationDescription} onChange={(event) => setForm((current) => ({ ...current, locationDescription: event.target.value }))} rows={2} fullWidth maxLength={255} /></FormField>
                {buildingId && <fieldset className="access-manager__units"><legend>Espaces desservis</legend><p>Ne sélectionnez aucun espace pour une entrée générale du bâtiment.</p>{units.filter((unit) => unit.buildingId === buildingId).map((unit) => <label key={unit.id}><input type="checkbox" checked={form.unitIds.includes(unit.id)} onChange={(event) => setForm((current) => ({ ...current, unitIds: event.target.checked ? [...current.unitIds, unit.id] : current.unitIds.filter((id) => id !== unit.id) }))} /> {unit.reference} · {unit.floorLabel || `Niveau ${unit.floor}`}</label>)}</fieldset>}
            </form>}
        </div>
    </Modal>;
}
