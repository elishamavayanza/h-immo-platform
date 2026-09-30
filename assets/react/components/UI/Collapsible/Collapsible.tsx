import React from 'react';
import { useCollapsible, UseCollapsibleProps } from '../../../hook-components/UI/Collapsible';
import { useI18n } from '@/react/i18n/I18nContext';

export interface CollapsibleProps extends UseCollapsibleProps {
    /** Élément déclencheur (toujours visible) */
    trigger: React.ReactNode;
    /** Contenu à afficher/masquer */
    children: React.ReactNode;
}

export function Collapsible({
                                defaultOpen,
                                isOpen,
                                onOpenChange,
                                className,
                                trigger,
                                children,
                            }: CollapsibleProps) {
    const { t } = useI18n();
    const { classes, open, toggle } = useCollapsible({
        defaultOpen,
        isOpen,
        onOpenChange,
        className,
    });

    return (
        <div className={classes}>
            <div
                className="collapsible__trigger"
                onClick={toggle}
                role="button"
                tabIndex={0}
                aria-expanded={open}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggle();
                    }
                }}
            >
                {typeof trigger === 'string' ? t(trigger) : trigger}
            </div>
            {open && <div className="collapsible__content">{children}</div>}
        </div>
    );
}
