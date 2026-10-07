import type { ReactNode } from 'react';
import { InlineSummary } from './InlineSummary';

export interface OrganizationSummaryItem {
    label: string;
    value: ReactNode;
}

/** Résumé compact d’une page opérationnelle : les indicateurs restent groupés sans fragmenter la page en cartes. */
export function OrganizationSummary({ items }: { items: OrganizationSummaryItem[] }) {
    return <InlineSummary items={items} />;
}
