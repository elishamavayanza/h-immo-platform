import React, { forwardRef } from 'react';
import { useSearchInput, UseSearchInputProps } from '../../../hook-components/Forms/SearchInput';
import { useI18n } from '@/react/i18n/I18nContext';

const SearchIcon = () => (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2">
        <circle cx="11" cy="11" r="8" />
        <line x1="21" y1="21" x2="16.65" y2="16.65" />
    </svg>
);

const ClearIcon = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2">
        <line x1="18" y1="6" x2="6" y2="18" />
        <line x1="6" y1="6" x2="18" y2="18" />
    </svg>
);

export interface SearchInputProps extends UseSearchInputProps {
    inputProps?: React.InputHTMLAttributes<HTMLInputElement>;
    showClearButton?: boolean;
    icon?: React.ReactNode;
}

export const SearchInput = forwardRef<HTMLInputElement, SearchInputProps>(
    (
        {
            value,
            defaultValue,
            variant = 'default',
            fieldSize = 'medium',
            fullWidth = false,
            disabled = false,
            placeholder,
            onSearch,
            onClear,
            className,
            inputProps,
            showClearButton = true,
            icon = <SearchIcon />,
        },
        ref
    ) => {
        const { t } = useI18n();
        const { classes, value: currentValue, handleChange, handleClear } = useSearchInput({
            value,
            defaultValue,
            variant,
            fieldSize,
            fullWidth,
            disabled,
            placeholder,
            onSearch,
            onClear,
            className,
        });

        return (
            <div className="search-input__wrapper">
                <span className="search-input__icon" aria-hidden="true">
                    {icon}
                </span>
                <input
                    ref={ref}
                    type="search"
                    className={classes}
                    value={currentValue}
                    onChange={handleChange}
                    disabled={disabled}
                    placeholder={placeholder}
                    {...inputProps}
                />
                {showClearButton && currentValue && (
                    <button
                        type="button"
                        className="search-input__clear"
                        onClick={handleClear}
                        aria-label={t('Effacer la recherche')}
                    >
                        <ClearIcon />
                    </button>
                )}
            </div>
        );
    }
);

SearchInput.displayName = 'SearchInput';
