import { useState, useMemo } from 'react';

export interface NavbarItem {
    id: string;
    label: React.ReactNode;
    href?: string;
    icon?: React.ReactNode;
    active?: boolean;
}

export interface UseNavbarProps {
    items: NavbarItem[];
    position?: 'fixed' | 'sticky' | 'static';
    variant?: 'default' | 'transparent' | 'colored';
    mobileBreakpoint?: number;
    className?: string;
}

export function useNavbar({
                              items,
                              position = 'sticky',
                              variant = 'default',
                              mobileBreakpoint = 768,
                              className = '',
                          }: UseNavbarProps) {
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    const toggleMobileMenu = () => setIsMobileMenuOpen((prev) => !prev);
    const closeMobileMenu = () => setIsMobileMenuOpen(false);

    const classes = useMemo(() => {
        const base = 'navbar';
        const positionClass = `navbar--${position}`;
        const variantClass = `navbar--${variant}`;
        return [base, positionClass, variantClass, className].filter(Boolean).join(' ');
    }, [position, variant, className]);

    return {
        classes,
        isMobileMenuOpen,
        toggleMobileMenu,
        closeMobileMenu,
    };
}
