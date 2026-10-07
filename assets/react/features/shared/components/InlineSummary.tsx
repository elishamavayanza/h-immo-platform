import type { ReactNode } from 'react';
import '../../../../styles/components/_inline-summary.scss';

export interface InlineSummaryItem {
    label: string;
    value: ReactNode;
    detail?: ReactNode;
}

/** Regroupe les indicateurs secondaires dans une ligne compacte, sans multiplier les cartes. */
export function InlineSummary({ items }: { items: InlineSummaryItem[] }) {
    return <section className="inline-summary" aria-label="Résumé de la page">
        {items.map((item) => <div className="inline-summary__item" key={item.label}>
            <span>{item.label}</span>
            <strong>{item.value}</strong>
            {item.detail && <small>{item.detail}</small>}
        </div>)}
    </section>;
}
