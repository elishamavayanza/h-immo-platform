import { FormField } from '../../../../components/Forms/FormField';
import { Input } from '../../../../components/Forms/Input';

interface CapacityLimitFieldProps {
    id: string;
    label: string;
    value: number | null;
    onChange: (value: number | null) => void;
    disabled?: boolean;
}

/** Plafond métier flexible : une valeur positive ou explicitement sans limite. */
export function CapacityLimitField({ id, label, value, onChange, disabled = false }: CapacityLimitFieldProps) {
    const unlimited = value === null;

    return (
        <FormField label={label} htmlFor={id}>
            <Input
                id={id}
                type="number"
                min={1}
                max={10_000_000}
                step={1}
                value={unlimited ? '' : value}
                placeholder={unlimited ? 'Sans limite' : 'Ex. : 10'}
                disabled={unlimited || disabled}
                onChange={(event) => onChange(Math.max(1, Number(event.target.value) || 1))}
                fullWidth
            />
            <label className="sa-capacity-limit__unlimited">
                <input
                    type="checkbox"
                    checked={unlimited}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.checked ? null : 1)}
                />
                <span>Sans limite</span>
            </label>
        </FormField>
    );
}
