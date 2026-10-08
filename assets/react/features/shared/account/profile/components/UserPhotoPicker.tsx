import { useRef, useState, type ChangeEvent } from 'react';

import { ImageEditor } from '../../../../../components/UI/ImageEditor/ImageEditor';
import { Button } from '../../../../../components/UI/Button';
import { mediaHref } from '../../../media/services/mediaService';

type UserPhotoChange =
    | { kind: 'new'; dataUrl: string; file: File }
    | { kind: 'removed' }
    | null;

interface UserPhotoPickerProps {
    value?: string | null;
    change: UserPhotoChange;
    onChange: (change: UserPhotoChange) => void;
    disabled?: boolean;
}

/**
 * Sélection de la photo de profil utilisateur.
 *
 * L'utilisateur choisit une image puis valide OBLIGATOIREMENT un cadrage :
 * `ImageEditor` permet le zoom, la rotation (±90°) et le recadrage carré
 * avant l'envoi. Tant que la validation n'a pas eu lieu, rien n'est transmis
 * au parent.
 */
export function UserPhotoPicker({ value, change, onChange, disabled }: UserPhotoPickerProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [editing, setEditing] = useState<string | null>(null);

    const preview = change?.kind === 'new' ? change.dataUrl : mediaHref(value);
    const hasPicked = change?.kind === 'new';
    const removed = change?.kind === 'removed';

    const handleFile = (event: ChangeEvent<HTMLInputElement>) => {
        if (disabled) return;
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;

        if (!file.type.startsWith('image/')) return;
        if (file.size > 10 * 1024 * 1024) return;

        const reader = new FileReader();
        reader.onload = () => {
            if (typeof reader.result === 'string') {
                setEditing(reader.result);
            }
        };
        reader.readAsDataURL(file);
    };

    const handleApply = (dataUrl: string, file?: File) => {
        if (!file) return;
        onChange({ kind: 'new', dataUrl, file });
        setEditing(null);
    };

    const remove = () => {
        if (hasPicked) {
            onChange(null);
        } else if (value) {
            onChange({ kind: 'removed' });
        }
        setEditing(null);
    };

    if (editing) {
        return (
            <div className="sa-photo-picker">
                <p className="sa-management-form__hint">Glissez la photo pour ajuster le cadrage, zoomez ou pivotez, puis validez.</p>
                <ImageEditor
                    src={editing}
                    shape="square"
                    outputSize={256}
                    onCancel={() => setEditing(null)}
                    onApply={handleApply}
                />
            </div>
        );
    }

    const pickLabel = hasPicked ? 'Changer la photo' : removed ? 'Choisir une photo' : value ? 'Remplacer la photo' : 'Ajouter une photo';
    const dismissLabel = hasPicked ? 'Annuler le choix' : 'Retirer la photo';

    return (
        <div className="sa-photo-picker">
            <div className={`sa-photo-picker__preview${preview ? ' sa-photo-picker__preview--filled' : ''}`}>
                {preview
                    ? <img src={preview} alt="Aperçu de votre photo de profil" />
                    : <span aria-hidden="true">Pas encore de photo</span>}
            </div>
            <div className="sa-photo-picker__actions">
                <input
                    ref={inputRef}
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/gif"
                    hidden
                    onChange={handleFile}
                    disabled={disabled}
                />
                <Button type="button" variant="outline" onClick={() => inputRef.current?.click()} disabled={disabled}>
                    {pickLabel}
                </Button>
                {(hasPicked || (value && !removed)) && !disabled && (
                    <Button type="button" variant="ghost" onClick={remove}>
                        {dismissLabel}
                    </Button>
                )}
            </div>
        </div>
    );
}
