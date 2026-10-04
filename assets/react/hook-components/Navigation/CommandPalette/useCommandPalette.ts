import { useState, useMemo, useEffect, useRef } from 'react';

export interface CommandItem {
    id: string;
    label: React.ReactNode;
    icon?: React.ReactNode;
    group?: string;
    action: () => void;
    disabled?: boolean;
}

export interface UseCommandPaletteProps {
    isOpen: boolean;
    onClose: () => void;
    commands: CommandItem[];
    placeholder?: string;
    className?: string;
}

export function useCommandPalette({
                                      isOpen,
                                      onClose,
                                      commands,
                                      placeholder = 'Tapez une commande...',
                                      className = '',
                                  }: UseCommandPaletteProps) {
    const [query, setQuery] = useState('');
    const [selectedIndex, setSelectedIndex] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);

    const filteredCommands = useMemo(() => {
        if (!query.trim()) return commands;
        const lowerQuery = query.toLowerCase();
        return commands.filter((cmd) => {
            const labelText = typeof cmd.label === 'string' ? cmd.label : '';
            return labelText.toLowerCase().includes(lowerQuery);
        });
    }, [commands, query]);

    useEffect(() => {
        if (isOpen) {
            setQuery('');
            setSelectedIndex(0);
            setTimeout(() => inputRef.current?.focus(), 0);
        }
    }, [isOpen]);

    useEffect(() => {
        setSelectedIndex(0);
    }, [query, filteredCommands.length]);

    const goDown = () => {
        setSelectedIndex((prev) => (prev + 1) % filteredCommands.length);
    };

    const goUp = () => {
        setSelectedIndex((prev) => (prev - 1 + filteredCommands.length) % filteredCommands.length);
    };

    const executeSelected = () => {
        const selected = filteredCommands[selectedIndex];
        if (selected && !selected.disabled) {
            selected.action();
            onClose();
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                goDown();
                break;
            case 'ArrowUp':
                e.preventDefault();
                goUp();
                break;
            case 'Enter':
                e.preventDefault();
                executeSelected();
                break;
            case 'Escape':
                e.preventDefault();
                onClose();
                break;
            default:
                break;
        }
    };

    const classes = useMemo(() => {
        const base = 'command-palette';
        const openClass = isOpen ? 'command-palette--open' : '';
        return [base, openClass, className].filter(Boolean).join(' ');
    }, [isOpen, className]);

    return {
        classes,
        isOpen,
        query,
        setQuery,
        filteredCommands,
        selectedIndex,
        handleKeyDown,
        inputRef,
        placeholder,
        onClose,
    };
}
