import type { ReactNode } from 'react';

import { Card } from '../../../../components/UI/Card';

export interface PatrimoineSummaryField {
    label: string;
    value: ReactNode;
}

interface PatrimoineSummaryCardProps {
    eyebrow?: string;
    title: ReactNode;
    subtitle?: ReactNode;
    badges?: ReactNode;
    fields?: PatrimoineSummaryField[];
    actions?: ReactNode;
    children?: ReactNode;
}

/**
 * Carte de synthèse d'un bien sélectionné (ville, parcelle, bâtiment).
 *
 * Purement présentationnelle : elle affiche l'identité du bien, ses
 * indicateurs et les actions autorisées transmises par l'appelant. Elle ne
 * décide d'aucune permission et ne charge aucune donnée — les actions restent
 * gatées par `canDo` / `readOnly` en amont, l'API demeurant l'autorité.
 */
export function PatrimoineSummaryCard({ eyebrow, title, subtitle, badges, fields, actions, children }: PatrimoineSummaryCardProps) {
    return (
        <Card className="patrimoine-summary" padding="medium">
            <div className="patrimoine-summary__head">
                <div className="patrimoine-summary__identity">
                    {eyebrow && <span className="patrimoine-summary__eyebrow">{eyebrow}</span>}
                    <div className="patrimoine-summary__title-row">
                        <h2>{title}</h2>
                        {badges && <span className="patrimoine-summary__badges">{badges}</span>}
                    </div>
                    {subtitle && <p className="patrimoine-summary__subtitle">{subtitle}</p>}
                </div>
                {actions && <div className="patrimoine-summary__actions">{actions}</div>}
            </div>

            {fields && fields.length > 0 && (
                <dl className="patrimoine-summary__fields">
                    {fields.map((field) => (
                        <div className="patrimoine-summary__field" key={field.label}>
                            <dt>{field.label}</dt>
                            <dd>{field.value}</dd>
                        </div>
                    ))}
                </dl>
            )}

            {children}
        </Card>
    );
}
