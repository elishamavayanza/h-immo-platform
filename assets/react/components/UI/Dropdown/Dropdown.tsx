import React from 'react';
import { useDropdown, UseDropdownProps } from '../../../hook-components/UI/Dropdown';
import { useI18n } from '@/react/i18n/I18nContext';

const ChevronIcon = () => (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2">
        <polyline points="6 9 12 15 18 9" />
    </svg>
);

export interface DropdownProps extends UseDropdownProps {}

export function Dropdown({ options, value, onSelect, placeholder, disabled, className }: DropdownProps) {
    const { t } = useI18n();
    const {
        classes,
        isOpen,
        toggleOpen,
        handleSelect,
        selectedOption,
        placeholder: placeholderText,
        dropdownRef,
    } = useDropdown({ options, value, onSelect, placeholder, disabled, className });

    return (
        <div className={classes} ref={dropdownRef}>
            <button
                type="button"
                className="dropdown__trigger"
                onClick={toggleOpen}
                disabled={disabled}
                aria-haspopup="listbox"
                aria-expanded={isOpen}
            >
                <span className="dropdown__value">{selectedOption ? selectedOption.label : t(placeholderText)}</span>
                <ChevronIcon />
            </button>

            {isOpen && (
                <ul className="dropdown__menu" role="listbox">
                    {options.map((option) => (
                        <li
                            key={option.value}
                            className={`dropdown__item ${option.disabled ? 'dropdown__item--disabled' : ''}`}
                            onClick={() => handleSelect(option)}
                            role="option"
                            aria-selected={option.value === selectedOption?.value}
                        >
                            {option.label}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
