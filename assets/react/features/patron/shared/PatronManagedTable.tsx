import { useEffect, useState, type FormEvent } from 'react';
import { DataTable } from '../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../hook-components/Data/DataTable';
import { FormField } from '../../../components/Forms/FormField';
import { Input } from '../../../components/Forms/Input';
import { Button } from '../../../components/UI/Button';
import { EmptyState } from '../../../components/Data/EmptyState';
import { ConfirmDialog } from '../../../components/UI/ConfirmDialog';
import { Modal } from '../../../components/UI/Modal';
import { PopoverMenu } from '../../../components/UI/PopoverMenu';
import type { PopoverMenuItem } from '../../../hook-components/UI/PopoverMenu';

export interface EditableField<T> { key: keyof T; label: string; required?: boolean; type?: 'text' | 'number'; }
export interface PatronTableAction<T> { id: string; label: (row: T) => string; apply: (row: T) => Partial<T>; visible?: (row: T) => boolean; }
interface PatronManagedTableProps<T extends { id: string }> {
    rows: T[];
    columns: DataTableColumn<T>[];
    fields: EditableField<T>[];
    createRecord: (values: Record<string, string>) => T;
    title: string;
    createLabel: string;
    initialSortKey: string;
    toggleStatus?: (row: T) => Partial<T>;
    statusLabel?: (row: T) => string;
    statusAction?: PatronTableAction<T>;
    extraActions?: PatronTableAction<T>[];
    allowCreate?: boolean;
    allowEdit?: boolean;
    allowDelete?: boolean;
}

/** Contrôles locaux de maquette ; à remplacer par les mutations API lors de l’intégration. */
export function PatronManagedTable<T extends { id: string }>({ rows, columns, fields, createRecord, title, createLabel, initialSortKey, toggleStatus, statusLabel, statusAction, extraActions = [], allowCreate = true, allowEdit = true, allowDelete = true }: PatronManagedTableProps<T>) {
    const [records, setRecords] = useState(rows);
    const [editing, setEditing] = useState<T | null>(null);
    const [form, setForm] = useState<Record<string, string>>({});
    const [pendingDelete, setPendingDelete] = useState<T | null>(null);
    useEffect(() => setRecords(rows), [rows]);

    const openCreate = () => { setEditing(null); setForm(Object.fromEntries(fields.map(({ key }) => [String(key), '']))); };
    const openEdit = (row: T) => { setEditing(row); setForm(Object.fromEntries(fields.map(({ key }) => [String(key), String(row[key] ?? '')]))); };
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (editing) {
            const changes = Object.fromEntries(fields.map(({ key, type }) => [String(key), type === 'number' ? Number(form[String(key)]) || 0 : form[String(key)] ?? '']));
            setRecords((current) => current.map((record) => record.id === editing.id ? { ...record, ...changes } as T : record));
        }
        else setRecords((current) => [createRecord(form), ...current]);
        setEditing(null);
        setForm({});
    };
    const actionColumns: DataTableColumn<T>[] = [...columns, { key: 'rowActions', title: 'Actions', render: (row) => {
        const items: PopoverMenuItem[] = [
            ...(allowEdit ? [{ id: 'edit', label: 'Modifier', icon: <span aria-hidden="true">✎</span>, onClick: () => openEdit(row) }] : []),
            ...(toggleStatus ? [{ id: 'toggle', label: statusLabel?.(row) ?? 'Changer le statut', icon: <span aria-hidden="true">⏻</span>, onClick: () => setRecords((current) => current.map((item) => item.id === row.id ? { ...item, ...toggleStatus(row) } : item)) }] : []),
            ...(statusAction && (statusAction.visible?.(row) ?? true) ? [{ id: statusAction.id, label: statusAction.label(row), icon: <span aria-hidden="true">⏻</span>, onClick: () => setRecords((current) => current.map((item) => item.id === row.id ? { ...item, ...statusAction.apply(row) } : item)) }] : []),
            ...extraActions.filter((action) => action.visible?.(row) ?? true).map((action) => ({ id: action.id, label: action.label(row), onClick: () => setRecords((current) => current.map((item) => item.id === row.id ? { ...item, ...action.apply(row) } : item)) })),
            ...(allowDelete ? [{ id: 'delete-separator', label: '', separator: true }, { id: 'delete', label: 'Supprimer', icon: <span aria-hidden="true">⌫</span>, danger: true, onClick: () => setPendingDelete(row) }] : []),
        ];
        const rowLabel = String(row[columns[0]?.key as keyof T] ?? row.id);
        return <PopoverMenu placement="bottom" offset={6} items={items} trigger={<span className="organization-row-actions" aria-label={`Actions pour ${rowLabel}`}><span aria-hidden="true">•••</span></span>} />;
    } }];

    return <>
        {allowCreate && records.length > 0 && <div className="organization-table-toolbar__actions"><Button onClick={openCreate}>＋ {createLabel}</Button></div>}
        {records.length > 0 ? <DataTable columns={actionColumns} data={records} pageSize={8} initialSortKey={initialSortKey} /> : <EmptyState title="Aucun résultat" description="Aucun élément ne correspond à cette recherche ou à ces filtres. Ajustez les critères, ou ajoutez un élément si vous gérez cette liste." action={allowCreate ? <Button onClick={openCreate}>＋ {createLabel}</Button> : undefined} />}
        <Modal isOpen={allowCreate || allowEdit ? editing !== null || Object.keys(form).length > 0 : false} onClose={() => { setEditing(null); setForm({}); }} title={editing ? `Modifier : ${title}` : createLabel} size="medium" footer={<><Button variant="outline" onClick={() => { setEditing(null); setForm({}); }}>Annuler</Button><Button type="submit" form="patron-record-form">Enregistrer</Button></>}>
            <form id="patron-record-form" className="organization-management-form" onSubmit={submit}>
                <p className="organization-management-form__hint">Modification locale de la maquette, sans appel à l’API.</p>
                {fields.map(({ key, label, required, type = 'text' }) => <FormField key={String(key)} label={label} htmlFor={`patron-${String(key)}`} required={required}><Input id={`patron-${String(key)}`} type={type} value={form[String(key)] ?? ''} onChange={(event) => setForm((current) => ({ ...current, [String(key)]: event.target.value }))} required={required} fullWidth /></FormField>)}
            </form>
        </Modal>
        {allowDelete && <ConfirmDialog isOpen={pendingDelete !== null} onClose={() => setPendingDelete(null)} onCancel={() => setPendingDelete(null)} onConfirm={() => { if (pendingDelete) setRecords((current) => current.filter((row) => row.id !== pendingDelete.id)); setPendingDelete(null); }} title="Supprimer cet élément ?" message="Cette action est simulée dans la maquette. Les règles de conservation métier seront appliquées lors du raccordement à l’API." confirmLabel="Supprimer" />}
    </>;
}
