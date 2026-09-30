import React from 'react';
import { useList, UseListProps } from '../../../hook-components/Data/List';
import { useI18n } from '@/react/i18n/I18nContext';

export interface ListItem {
    id?: string | number;
    icon?: React.ReactNode;
    iconPosition?: 'left' | 'right';
    content: React.ReactNode;
    onClick?: () => void;
    className?: string;
}

export interface ListProps extends UseListProps {
    items: ListItem[];
    emptyContent?: React.ReactNode;
    renderItem?: (item: ListItem, index: number) => React.ReactNode;
}

export function List({
                         items,
                         variant = 'default',
                         size = 'medium',
                         order = 'none',
                         fullWidth = true,
                         className,
                         emptyContent,
                         renderItem,
                     }: ListProps) {
    const { t } = useI18n();
    const { classes } = useList({
        variant,
        size,
        order,
        fullWidth,
        className,
    });

    if (items.length === 0) {
        return (
            <div className={`${classes}__empty`}>
                {emptyContent || t('Aucun élément')}
            </div>
        );
    }

    const Tag = order === 'ordered' ? 'ol' : order === 'none' ? 'ul' : 'ul';

    return (
        <Tag className={classes}>
            {items.map((item, index) => {
                const itemContent = renderItem ? renderItem(item, index) : (
                    <>
                        {item.icon && item.iconPosition !== 'right' && (
                            <span className="list__icon list__icon--left" aria-hidden="true">
                {item.icon}
              </span>
                        )}
                        <span className="list__content">{item.content}</span>
                        {item.icon && item.iconPosition === 'right' && (
                            <span className="list__icon list__icon--right" aria-hidden="true">
                {item.icon}
              </span>
                        )}
                    </>
                );

                return (
                    <li
                        key={item.id ?? index}
                        className={`list__item ${item.className || ''}`}
                        onClick={item.onClick}
                        role={item.onClick ? 'button' : undefined}
                        tabIndex={item.onClick ? 0 : undefined}
                        onKeyDown={
                            item.onClick
                                ? (e) => {
                                    if (e.key === 'Enter' || e.key === ' ') {
                                        e.preventDefault();
                                        item.onClick?.();
                                    }
                                }
                                : undefined
                        }
                    >
                        {itemContent}
                    </li>
                );
            })}
        </Tag>
    );
}
