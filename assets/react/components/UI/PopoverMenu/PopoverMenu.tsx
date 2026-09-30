import React from 'react';
import { PopoverMenuItem, usePopoverMenu, UsePopoverMenuProps } from '../../../hook-components/UI/PopoverMenu';
import { useI18n } from '@/react/i18n/I18nContext';

export interface PopoverMenuProps extends UsePopoverMenuProps {
    trigger: React.ReactNode;
    renderItem?: (item: PopoverMenuItem, index: number) => React.ReactNode;
}

export function PopoverMenu({
                                items,
                                placement = 'bottom',
                                offset = 8,
                                closeOnClickItem = true,
                                closeOnOutsideClick = true,
                                closeOnEscape = true,
                                className = '',
                                trigger,
                                renderItem,
                            }: PopoverMenuProps) {
    const {
        isOpen,
        triggerRef,
        menuRef,
        toggle,
        handleItemClick,
        classes,
        coords,
    } = usePopoverMenu({ items, placement, offset, closeOnClickItem, closeOnOutsideClick, closeOnEscape, className });

    const { t } = useI18n();

    // Définir la transformation selon le placement
    const transform = () => {
        switch (placement) {
            case 'bottom':
                return 'translate(-50%, 0)';
            case 'top':
                return 'translate(-50%, -100%)';
            case 'left':
                return 'translate(-100%, -50%)';
            case 'right':
                return 'translate(0, -50%)';
            default:
                return 'translate(-50%, 0)';
        }
    };

    const style: React.CSSProperties = {
        position: 'fixed',
        top: coords.top,
        left: coords.left,
        transform: transform(),
        zIndex: 3000,
    };

    return (
        <div className="popover-menu-container" ref={triggerRef}>
            <div
                className={classes.trigger}
                onClick={toggle}
                role="button"
                aria-haspopup="true"
                aria-expanded={isOpen}
                tabIndex={0}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggle();
                    }
                }}
            >
                {trigger}
            </div>

            {isOpen && (
                <div
                    ref={menuRef}
                    className={classes.menu}
                    style={style}
                    role="menu"
                    aria-orientation="vertical"
                >
                    {items.map((item, index) => {
                        if (item.separator) {
                            return <div key={`sep-${index}`} className="popover-menu__separator" />;
                        }
                        return (
                            <button
                                key={item.id}
                                className={`popover-menu__item ${item.danger ? 'popover-menu__item--danger' : ''} ${item.disabled ? 'popover-menu__item--disabled' : ''}`}
                                onClick={() => handleItemClick(item)}
                                disabled={item.disabled}
                                role="menuitem"
                                tabIndex={-1}
                            >
                                {item.icon && <span className="popover-menu__item-icon">{item.icon}</span>}
                                <span className="popover-menu__item-label">{typeof item.label === 'string' ? t(item.label) : item.label}</span>
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
