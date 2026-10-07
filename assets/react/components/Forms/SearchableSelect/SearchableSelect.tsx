import React, { useState, useRef, useEffect } from 'react';
import '../../../../styles/components/Form/SearchableSelect.scss';

export interface SearchableSelectOption {
    value: string | number;
    label: string;
}

export interface SearchableSelectProps {
    options: SearchableSelectOption[];
    value: string | number | '';
    onChange: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
    required?: boolean;
    className?: string;
}

export function SearchableSelect({
                                     options,
                                     value,
                                     onChange,
                                     placeholder,
                                     disabled = false,
                                     required = false,
                                     className,
                                 }: SearchableSelectProps) {
    const effectivePlaceholder = placeholder ?? ('Rechercher...');
    const [isOpen, setIsOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [displayValue, setDisplayValue] = useState('');
    const [opensAbove, setOpensAbove] = useState(false);
    const [dropdownMaxHeight, setDropdownMaxHeight] = useState<number>();
    const wrapperRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!isOpen) return;

        const updatePlacement = () => {
            const bounds = wrapperRef.current?.getBoundingClientRect();
            if (!bounds) return;

            const spaceBelow = window.innerHeight - bounds.bottom;
            const spaceAbove = bounds.top;
            const preferredHeight = Math.min(
                window.innerWidth <= 640 ? 220 : 260,
                window.innerHeight * 0.4,
            );
            const openAbove = spaceBelow < preferredHeight && spaceAbove > spaceBelow;

            setOpensAbove(openAbove);
            setDropdownMaxHeight(Math.max(1, Math.min(preferredHeight, (openAbove ? spaceAbove : spaceBelow) - 12)));
        };

        updatePlacement();
        window.addEventListener('resize', updatePlacement);
        window.addEventListener('scroll', updatePlacement, true);
        return () => {
            window.removeEventListener('resize', updatePlacement);
            window.removeEventListener('scroll', updatePlacement, true);
        };
    }, [isOpen]);

    // Synchroniser la valeur affichée avec la prop `value`
    useEffect(() => {
        const selectedOption = options.find(
            (opt) => String(opt.value) === String(value)
        );
        if (selectedOption) {
            setDisplayValue(selectedOption.label);
            setSearch(''); // réinitialise la recherche
        } else if (value === '') {
            setDisplayValue('');
        }
    }, [value, options]);

    // Fermer le dropdown si clic à l'extérieur
    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (
                wrapperRef.current &&
                !wrapperRef.current.contains(e.target as Node)
            ) {
                setIsOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Filtrer les options selon la recherche
    const filteredOptions = options.filter((opt) =>
        opt.label.toLowerCase().includes(search.toLowerCase())
    );

    const handleSelect = (val: string) => {
        onChange(val);
        const selectedOption = options.find((opt) => String(opt.value) === val);
        setDisplayValue(selectedOption?.label ?? '');
        setSearch('');
        setIsOpen(false);
    };

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setSearch(e.target.value);
        setDisplayValue(e.target.value); // affiche la frappe
        setIsOpen(true);
    };

    return (
        <div className={`searchable-select ${className ?? ''}`} ref={wrapperRef}>
            <input
                type="text"
                className="searchable-select__input"
                placeholder={effectivePlaceholder}
                value={isOpen ? search : displayValue}
                onClick={() => setIsOpen((prev) => !prev)}
                onChange={handleInputChange}
                disabled={disabled}
                required={required}
            />
            {isOpen && (
                <div
                    className={`searchable-select__dropdown ${opensAbove ? 'searchable-select__dropdown--above' : ''}`}
                    style={dropdownMaxHeight ? { maxHeight: `${dropdownMaxHeight}px` } : undefined}
                >
                    {filteredOptions.length > 0 ? (
                        filteredOptions.map((opt) => (
                            <div
                                key={opt.value}
                                className={`searchable-select__option ${
                                    String(opt.value) === String(value)
                                        ? 'searchable-select__option--selected'
                                        : ''
                                }`}
                                onClick={() => handleSelect(String(opt.value))}
                            >
                                {opt.label}
                            </div>
                        ))
                    ) : (
                        <div className="searchable-select__empty">{('Aucun résultat')}</div>
                    )}
                </div>
            )}
        </div>
    );
}
