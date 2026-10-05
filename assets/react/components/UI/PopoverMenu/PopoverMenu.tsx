import React from 'react';
import { createPortal } from 'react-dom';
import { PopoverMenuItem, usePopoverMenu, UsePopoverMenuProps } from '../../../hook-components/UI/PopoverMenu';

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

    // `top`/`left` sont déjà des pixels finaux, bornés dans le viewport
    // par `useFloatingPosition` : plus aucun `transform` de positionnement
    // (c'est l'animation CSS qui utilise `transform`, sans conflit).
    // `visibility: hidden` tant que la mesure n'est pas faite, pour ne
    // pas peindre une frame le menu en (0, 0).
    // Le `z-index` vient de la classe CSS (`$z-popover`) et non d'un
    // littéral inline, pour rester cohérent avec les autres panneaux.
    const style: React.CSSProperties = {
        position: 'fixed',
        top: coords?.top ?? 0,
        left: coords?.left ?? 0,
        visibility: coords === null ? 'hidden' : 'visible',
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

            {/* Portal sur `document.body` : le menu est `position: fixed`
                mais reste un descendant du sidebar, qui a `overflow:
                hidden` et un contexte d'empilement propre. Rendu dans le
                DOM racine, il ne peut plus être rogné par un ancêtre. */}
            {isOpen &&
                createPortal(
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
                                    <span className="popover-menu__item-label">{typeof item.label === 'string' ? (item.label) : item.label}</span>
                                </button>
                            );
                        })}
                    </div>,
                    document.body
                )}
        </div>
    );
}
