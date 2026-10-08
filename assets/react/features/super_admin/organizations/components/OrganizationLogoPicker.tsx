import { useRef, useState, type ChangeEvent } from 'react';

import { ImageEditor } from '../../../../components/UI/ImageEditor/ImageEditor';
import { Button } from '../../../../components/UI/Button';
import { logoHref } from '../services/organizationsService';

/** Décision de logo portée par le parent (wizard / modal d'édition). */
export type OrganizationLogoChange =
    /** Nouveau logo retaillé à envoyer (upload). */
    | { kind: 'new'; dataUrl: string; file: File }
    /** Retirer le logo existant (delete). */
    | { kind: 'removed' }
    /** Aucun changement. */
    | null;

interface OrganizationLogoPickerProps {
    /** Logo actuellement enregistré (chemin/URL renvoyé par l'API), pour l'édition. */
    value?: string | null;
    /** Décision en cours gérée par le parent. */
    change: OrganizationLogoChange;
    onChange: (change: OrganizationLogoChange) => void;
}

/**
 * Sélection du logo d'une organisation.
 *
 * L'utilisateur choisit une image puis valide OBLIGATOIREMENT un cadrage :
 * `ImageEditor` permet le zoom, la rotation (±90°) et le recadrage carré
 * avant l'envoi. Tant que la validation n'a pas eu lieu, rien n'est transmis
 * au parent. La décision est portée par `OrganizationLogoChange` pour que
 * l'édition distingue « nouvel upload », « retrait » et « inchangé ».
 */
export function OrganizationLogoPicker({ value, change, onChange }: OrganizationLogoPickerProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [editing, setEditing] = useState<string | null>(null);

    const preview = change ? (change.kind === 'new' ? change.dataUrl : null) : value ? logoHref(value) : null;
    const hasPicked = change?.kind === 'new';
    const removed = change?.kind === 'removed';

    const handleFile = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;

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
            <div className="sa-logo-picker">
                <p className="sa-management-form__hint">Zoomez, pivotez et cadrez le logo, puis validez. Le cadrage est carré.</p>
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

    const pickLabel = hasPicked ? 'Changer le logo' : removed ? 'Choisir un logo' : value ? 'Remplacer le logo' : 'Choisir un logo';
    const dismissLabel = hasPicked ? 'Annuler le choix' : 'Retirer le logo';

    return (
        <div className="sa-logo-picker">
            <div className={`sa-logo-picker__preview${preview ? ' sa-logo-picker__preview--filled' : ''}`}>
                {preview
                    ? <img src={preview} alt="Aperçu du logo de l’organisation" />
                    : <span aria-hidden="true">Pas encore de logo</span>}
            </div>
            <div className="sa-logo-picker__actions">
                <input
                    ref={inputRef}
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/gif"
                    hidden
                    onChange={handleFile}
                />
                <Button type="button" variant="outline" onClick={() => inputRef.current?.click()}>
                    {pickLabel}
                </Button>
                {(hasPicked || (value && !removed)) && (
                    <Button type="button" variant="ghost" onClick={remove}>
                        {dismissLabel}
                    </Button>
                )}
            </div>
        </div>
    );
}