import { Button } from '../../../components/UI/Button';

interface ModalActionsProps {
    /** `id` du `<form>` que le bouton de validation soumet (`type="submit" form=…`). */
    formId: string;
    /** Ferme la modale sans soumettre. */
    onCancel: () => void;
    /** Libellé au repos du bouton de validation. */
    submitLabel: string;
    /** Libellé pendant l'appel (défaut : `submitLabel`). */
    loadingLabel?: string;
    /** État d'envoi : désactive Annuler et bloque le double-clic. */
    isLoading?: boolean;
    /** `danger` pour une action destructive (annulation, suppression, suspension). */
    variant?: 'primary' | 'danger';
    /** Désactive la soumission tant que le formulaire est incomplet. */
    disabled?: boolean;
    cancelLabel?: string;
}

/**
 * Pied de modale standard : Annuler + Valider. Le bouton de validation est
 * rattaché au formulaire par `form={formId}` (donc hors du `<form>`, comme
 * dans l'espace PATRON). `isLoading` sert à la fois de retour visuel et de
 * garde anti double-clic : le hook appelant doit en plus sortir tôt si un
 * envoi est déjà en cours.
 */
export function ModalActions({
                                 formId,
                                 onCancel,
                                 submitLabel,
                                 loadingLabel,
                                 isLoading = false,
                                 variant = 'primary',
                                 disabled = false,
                                 cancelLabel = 'Annuler',
                             }: ModalActionsProps) {
    return (
        <>
            <Button variant="outline" onClick={onCancel} disabled={isLoading}>{cancelLabel}</Button>
            <Button type="submit" form={formId} variant={variant} isLoading={isLoading} disabled={disabled}>
                {isLoading ? (loadingLabel ?? submitLabel) : submitLabel}
            </Button>
        </>
    );
}
