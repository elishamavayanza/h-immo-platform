import { useState, useMemo } from 'react';

export type SearchInputVariant = 'default' | 'error' | 'success';
export type SearchInputSize = 'small' | 'medium' | 'large';

export interface UseSearchInputProps {
    value?: string;
    defaultValue?: string;
    variant?: SearchInputVariant;
    fieldSize?: SearchInputSize;
    fullWidth?: boolean;
    disabled?: boolean;
    placeholder?: string;
    onSearch?: (value: string) => void;
    onClear?: () => void;
    className?: string;
}

export function useSearchInput({
                                   value: controlledValue,
                                   defaultValue = '',
                                   variant = 'default',
                                   fieldSize = 'medium',
                                   fullWidth = false,
                                   disabled = false,
                                   placeholder = 'Rechercher...',
                                   onSearch,
                                   onClear,
                                   className = '',
                               }: UseSearchInputProps) {
    const [uncontrolledValue, setUncontrolledValue] = useState(defaultValue);
    const isControlled = controlledValue !== undefined;
    const value = isControlled ? controlledValue : uncontrolledValue;

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const newValue = e.target.value;
        if (!isControlled) setUncontrolledValue(newValue);
        onSearch?.(newValue);
    };

    const handleClear = () => {
        if (!isControlled) setUncontrolledValue('');
        onSearch?.('');
        onClear?.();
    };

    const classes = useMemo(() => {
        const base = 'search-input';
        const variantClass = `search-input--${variant}`;
        const sizeClass = `search-input--${fieldSize}`;
        const fullWidthClass = fullWidth ? 'search-input--full-width' : '';
        const disabledClass = disabled ? 'search-input--disabled' : '';
        return [base, variantClass, sizeClass, fullWidthClass, disabledClass, className]
            .filter(Boolean)
            .join(' ');
    }, [variant, fieldSize, fullWidth, disabled, className]);

    return {
        classes,
        value,
        handleChange,
        handleClear,
        placeholder,
    };
}
