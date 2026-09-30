import React from 'react';
import { useBreadcrumb, UseBreadcrumbProps } from '../../../hook-components/Navigation/Breadcrumb';
import { useI18n } from '@/react/i18n/I18nContext';

export interface BreadcrumbProps extends UseBreadcrumbProps {}

export function Breadcrumb({ items, separator, size, className }: BreadcrumbProps) {
    const { t } = useI18n();
    const { classes, separator: sep } = useBreadcrumb({ items, separator, size, className });

    return (
        <nav className={classes} aria-label={t("Fil d'Ariane")}>
            <ol className="breadcrumb__list">
                {items.map((item, index) => {
                    const isLast = index === items.length - 1;
                    return (
                        <li key={item.id} className="breadcrumb__item">
                            {item.disabled ? (
                                <span className="breadcrumb__label breadcrumb__label--disabled" aria-disabled="true">
                  {item.icon && <span className="breadcrumb__icon">{item.icon}</span>}
                                    {item.label}
                </span>
                            ) : item.href ? (
                                <a href={item.href} className="breadcrumb__link">
                                    {item.icon && <span className="breadcrumb__icon">{item.icon}</span>}
                                    {item.label}
                                </a>
                            ) : (
                                <span className={`breadcrumb__label ${isLast ? 'breadcrumb__label--current' : ''}`} aria-current={isLast ? 'page' : undefined}>
                  {item.icon && <span className="breadcrumb__icon">{item.icon}</span>}
                                    {item.label}
                </span>
                            )}
                            {!isLast && (
                                <span className="breadcrumb__separator" aria-hidden="true">
                  {sep}
                </span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
