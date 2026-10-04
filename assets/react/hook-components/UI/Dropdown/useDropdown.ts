import { useState, useRef, useEffect, useMemo } from 'react';

export interface DropdownOption {
    value: string;
    label: React.ReactNode;
    disabled?: boolean;
}

export interface UseDropdownProps {
    options: DropdownOption[];
    value?: string;
    onSelect?: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
}

export function useDropdown({
                                options,
                                value,
                                onSelect,
                                placeholder = 'Sélectionner...',
                                disabled = false,
                                className = '',
                            }: UseDropdownProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [selectedValue, setSelectedValue] = useState<string | undefined>(value);
    const dropdownRef = useRef<HTMLDivElement>(null);

    const selectedOption = options.find((opt) => opt.value === selectedValue);

    const toggleOpen = () => {
        if (!disabled) setIsOpen((prev) => !prev);
    };

    const close = () => setIsOpen(false);

    const handleSelect = (option: DropdownOption) => {
        if (option.disabled) return;
        setSelectedValue(option.value);
        onSelect?.(option.value);
        close();
    };

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                close();
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const classes = useMemo(() => {
        const base = 'dropdown';
        const openClass = isOpen ? 'dropdown--open' : '';
        const disabledClass = disabled ? 'dropdown--disabled' : '';
        return [base, openClass, disabledClass, className].filter(Boolean).join(' ');
    }, [isOpen, disabled, className]);

    return {
        classes,
        isOpen,
        toggleOpen,
        close,
        handleSelect,
        selectedOption,
        placeholder,
        dropdownRef,
    };
}
