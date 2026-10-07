import { PopoverMenu } from '../../../components/UI/PopoverMenu';
import type { PopoverMenuItem } from '../../../hook-components/UI/PopoverMenu';

const EditIcon = <span aria-hidden="true">✎</span>;
const ToggleIcon = <span aria-hidden="true">⏻</span>;
const DeleteIcon = <span aria-hidden="true">⌫</span>;
const MoreIcon = <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="19" cy="12" r="1.5" /></svg>;

interface RowActionsProps {
    label: string;
    isActive: boolean;
    onEdit: () => void;
    onToggleActive: () => void;
    onDelete: () => void;
}

export function RowActions({ label, isActive, onEdit, onToggleActive, onDelete }: RowActionsProps) {
    const items: PopoverMenuItem[] = [
        { id: 'edit', label: 'Modifier', icon: EditIcon, onClick: onEdit },
        { id: 'toggle', label: isActive ? 'Suspendre' : 'Réactiver', icon: ToggleIcon, onClick: onToggleActive },
        { id: 'delete-separator', label: '', separator: true },
        { id: 'delete', label: 'Supprimer', icon: DeleteIcon, danger: true, onClick: onDelete },
    ];
    return <PopoverMenu
        placement="bottom"
        offset={6}
        items={items}
        className="sa-row-actions__menu"
        trigger={<span className="sa-row-actions__trigger" aria-label={`Actions pour ${label}`}>{MoreIcon}</span>}
    />;
}
