import { Select } from '../../../components/Forms/Select';
import { useAssignedCities } from '../hooks/useAssignedCities';

interface CitySelectProps {
    /** UUID de la ville sélectionnée (`CityItem.id`). */
    value: string;
    onChange: (cityUuid: string) => void;
    id?: string;
    fullWidth?: boolean;
    disabled?: boolean;
}

/**
 * Sélecteur de ville limité aux villes affectées à l'utilisateur courant.
 *
 * Utilisé par les écrans qui doivent raisonner « par ville » (tableau de
 * bord, rapports, formulaires de patrimoine/dépenses) : pour un
 * ADMIN_VILLE, seules ses villes apparaissent ; pour les autres rôles le
 * backend renvoie toutes les villes de l'organisation active.
 */
export function CitySelect({
    value,
    onChange,
    id = 'city-select',
    fullWidth = true,
    disabled = false,
}: CitySelectProps) {
    const { options, isLoading } = useAssignedCities();

    return (
        <Select
            id={id}
            value={value}
            onChange={(event) => onChange(event.target.value)}
            options={options}
            placeholder={
                isLoading
                    ? 'Chargement des villes…'
                    : options.length === 0
                      ? 'Aucune ville affectée'
                      : 'Sélectionner une ville'
            }
            fullWidth={fullWidth}
            disabled={disabled || isLoading}
        />
    );
}