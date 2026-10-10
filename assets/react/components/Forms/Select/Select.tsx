import React, { forwardRef, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import {useSelect, UseSelectProps} from "../../../hook-components/Forms/Select";
import { Icon } from '../../UI/Icon/Icon';


export interface SelectOption {
    value: string | number;
    label: string;
    disabled?: boolean;
}

export interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement>, UseSelectProps {
    options: SelectOption[];
    placeholder?: string;
}

function getMenuPosition(trigger: HTMLButtonElement): React.CSSProperties {
    const bounds = trigger.getBoundingClientRect();
    const viewportPadding = 8;
    const spaceBelow = window.innerHeight - bounds.bottom - viewportPadding;
    const spaceAbove = bounds.top - viewportPadding;
    const openAbove = spaceBelow < Math.min(240, spaceAbove) && spaceAbove > spaceBelow;
    const maxHeight = Math.max(1, Math.min(240, openAbove ? spaceAbove : spaceBelow));
    const width = Math.min(bounds.width, window.innerWidth - viewportPadding * 2);
    const left = Math.max(viewportPadding, Math.min(bounds.left, window.innerWidth - width - viewportPadding));

    return {
        position: 'fixed',
        left,
        width,
        maxHeight,
        ...(openAbove ? { bottom: window.innerHeight - bounds.top + 4 } : { top: bounds.bottom + 4 }),
    };
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(
    (
        {
            variant = 'default',
            fieldSize = 'medium',
            fullWidth = false,
            disabled = false,
            className,
            options,
            placeholder,
            children,
            onChange,
            value,
            defaultValue,
            id,
            name,
            required,
            ...selectProps
        },
        ref
    ) => {
        const selectRef = useRef<HTMLSelectElement | null>(null);
        const triggerRef = useRef<HTMLButtonElement | null>(null);
        const menuRef = useRef<HTMLDivElement | null>(null);
        const [isOpen, setIsOpen] = useState(false);
        const [menuStyle, setMenuStyle] = useState<React.CSSProperties>({});
        const [activeIndex, setActiveIndex] = useState(0);
        const { classes, ariaProps } = useSelect({
            variant,
            fieldSize,
            fullWidth,
            disabled,
            className,
        });

        const selectedValue = String(value ?? defaultValue ?? '');
        const selectedOption = options.find((option) => String(option.value) === selectedValue);

        useEffect(() => {
            if (!isOpen) return;
            const updateMenuPosition = () => {
                const trigger = triggerRef.current;
                if (trigger) setMenuStyle(getMenuPosition(trigger));
            };
            updateMenuPosition();
            window.addEventListener('resize', updateMenuPosition);
            window.addEventListener('scroll', updateMenuPosition, true);
            const handleOutside = (event: PointerEvent) => {
                const target = event.target as Node;
                if (!triggerRef.current?.parentElement?.contains(target) && !menuRef.current?.contains(target)) setIsOpen(false);
            };
            document.addEventListener('pointerdown', handleOutside);
            return () => {
                window.removeEventListener('resize', updateMenuPosition);
                window.removeEventListener('scroll', updateMenuPosition, true);
                document.removeEventListener('pointerdown', handleOutside);
            };
        }, [isOpen]);

        const chooseOption = (option: SelectOption) => {
            const nativeSelect = selectRef.current;
            if (!nativeSelect) return;
            Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value')?.set?.call(nativeSelect, String(option.value));
            nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
            setIsOpen(false);
            triggerRef.current?.focus();
        };

        const handleTriggerKeyDown = (event: React.KeyboardEvent<HTMLButtonElement>) => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                setIsOpen(true);
                setActiveIndex(Math.max(0, options.findIndex((option) => String(option.value) === selectedValue)));
            } else if (event.key === 'Escape') {
                setIsOpen(false);
            }
        };

        return (
            <div className="select-field__wrapper">
                <select
                    ref={(element) => {
                        selectRef.current = element;
                        if (typeof ref === 'function') ref(element);
                        else if (ref) ref.current = element;
                    }}
                    className={classes}
                    disabled={disabled}
                    {...ariaProps}
                    id={id ? `${id}-native` : undefined}
                    name={name}
                    value={value}
                    defaultValue={defaultValue}
                    required={required}
                    onChange={onChange}
                    {...selectProps}
                >
                    {placeholder && <option value="">{(placeholder)}</option>}
                    {options.map((opt) => (
                        <option key={opt.value} value={opt.value} disabled={opt.disabled}>
                            {opt.label}
                        </option>
                    ))}
                    {children}
                </select>
                <button
                    ref={triggerRef}
                    id={id}
                    type="button"
                    className={`${classes} select-field__trigger`}
                    disabled={disabled}
                    aria-label={selectProps['aria-label']}
                    aria-haspopup="listbox"
                    aria-expanded={isOpen}
                    aria-required={required || undefined}
                    aria-invalid={ariaProps['aria-invalid']}
                    onClick={() => {
                        if (!isOpen && triggerRef.current) {
                            setMenuStyle(getMenuPosition(triggerRef.current));
                        }
                        setActiveIndex(Math.max(0, options.findIndex((option) => String(option.value) === selectedValue)));
                        setIsOpen((open) => !open);
                    }}
                    onKeyDown={handleTriggerKeyDown}
                >
                    <span>{selectedOption?.label ?? placeholder ?? ''}</span>
                    <Icon name="chevronDown" size={18} className="select-field__arrow" />
                </button>
                {isOpen && createPortal(
                    <div ref={menuRef} className="select-field__menu" role="listbox" style={menuStyle}>
                        {options.map((option, index) => (
                            <button
                                key={option.value}
                                type="button"
                                role="option"
                                aria-selected={String(option.value) === selectedValue}
                                disabled={option.disabled}
                                className={`select-field__option ${String(option.value) === selectedValue ? 'select-field__option--selected' : ''}`}
                                onMouseEnter={() => setActiveIndex(index)}
                                onClick={() => chooseOption(option)}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>,
                    document.body,
                )}
            </div>
        );
    }
);

Select.displayName = 'Select';
