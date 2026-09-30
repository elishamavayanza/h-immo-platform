import { useState, useMemo } from 'react';

export interface UseCollapsibleProps {
    /** État initial d'ouverture */
    defaultOpen?: boolean;
    /** Contrôler l'ouverture depuis le parent (optionnel) */
    isOpen?: boolean;
    /** Callback lors du changement */
    onOpenChange?: (open: boolean) => void;
    className?: string;
}

export function useCollapsible({
                                   defaultOpen = false,
                                   isOpen: controlledIsOpen,
                                   onOpenChange,
                                   className = '',
                               }: UseCollapsibleProps) {
    const [internalOpen, setInternalOpen] = useState(defaultOpen);

    // Si la prop isOpen est fournie, c'est un composant contrôlé
    const open = controlledIsOpen !== undefined ? controlledIsOpen : internalOpen;

    const toggle = () => {
        const newOpen = !open;
        if (controlledIsOpen === undefined) setInternalOpen(newOpen);
        onOpenChange?.(newOpen);
    };

    const classes = useMemo(() => {
        const base = 'collapsible';
        const openClass = open ? 'collapsible--open' : 'collapsible--closed';
        return [base, openClass, className].filter(Boolean).join(' ');
    }, [open, className]);

    return {
        classes,
        open,
        toggle,
    };
}
