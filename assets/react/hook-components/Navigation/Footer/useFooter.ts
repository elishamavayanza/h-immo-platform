import { useMemo } from 'react';

export interface FooterSection {
    id: string;
    title: React.ReactNode;
    links: { id: string; label: React.ReactNode; href?: string; icon?: React.ReactNode }[];
}

export interface UseFooterProps {
    brand?: React.ReactNode;
    sections?: FooterSection[];
    bottomContent?: React.ReactNode;
    logo?: React.ReactNode;
    variant?: 'default' | 'dark' | 'colored';
    className?: string;
}

export function useFooter({
                              brand,
                              sections = [],
                              bottomContent,
                              logo,
                              variant = 'default',
                              className = '',
                          }: UseFooterProps) {
    const classes = useMemo(() => {
        const base = 'footer';
        const variantClass = `footer--${variant}`;
        return [base, variantClass, className].filter(Boolean).join(' ');
    }, [variant, className]);

    return {
        classes,
        brand,
        sections,
        bottomContent,
        logo,
    };
}
