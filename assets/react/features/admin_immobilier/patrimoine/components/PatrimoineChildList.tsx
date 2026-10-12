import type { ReactNode } from 'react';
import { Link } from 'react-router-dom';

import { EmptyState } from '../../../../components/Data/EmptyState';

export interface PatrimoineChildItem {
    id: string;
    title: ReactNode;
    subtitle?: ReactNode;
    meta?: { label: string; value: ReactNode }[];
    /** URL de l'enfant ; sans `to`, la ligne reste informative. */
    to?: string;
}

interface PatrimoineChildListProps {
    title: string;
    description?: ReactNode;
    items: PatrimoineChildItem[];
    emptyTitle: string;
    emptyDescription: string;
    ariaLabel?: string;
}

/** Liste compacte des éléments enfants, conçue pour compléter une synthèse sans multiplier les cartes. */
export function PatrimoineChildList({ title, description, items, emptyTitle, emptyDescription, ariaLabel }: PatrimoineChildListProps) {
    return (
        <section className="patrimoine-children" aria-label={ariaLabel ?? title}>
            <div className="patrimoine-children__heading">
                <h2>{title}</h2>
                {description && <p>{description}</p>}
            </div>

            {items.length === 0 ? (
                <EmptyState title={emptyTitle} description={emptyDescription} />
            ) : (
                <ul className="patrimoine-children__list">
                    {items.map((item) => {
                        const content = (
                            <>
                                <span className="patrimoine-child__identity">
                                    <strong>{item.title}</strong>
                                    {item.subtitle && <small>{item.subtitle}</small>}
                                </span>
                                {item.meta?.length ? (
                                    <dl className="patrimoine-child__meta">
                                        {item.meta.map((entry) => (
                                            <div key={entry.label}><dt>{entry.label}</dt><dd>{entry.value}</dd></div>
                                        ))}
                                    </dl>
                                ) : null}
                                {item.to && <span className="patrimoine-child__arrow" aria-hidden="true">›</span>}
                            </>
                        );

                        return (
                            <li key={item.id}>
                                {item.to
                                    ? <Link className="patrimoine-child" to={item.to} aria-label={`Ouvrir ${String(item.title)}`}>{content}</Link>
                                    : <div className="patrimoine-child patrimoine-child--static">{content}</div>}
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}
