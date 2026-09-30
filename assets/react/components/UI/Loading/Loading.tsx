import React from 'react';
import { Spinner } from '../Spinner';
import { UseSpinnerProps } from '../../../hook-components/UI/Spinner';
import { useI18n } from '@/react/i18n/I18nContext';

export interface LoadingProps extends UseSpinnerProps {
    text?: string;
}

export function Loading({ text, ...spinnerProps }: LoadingProps) {
    const { t } = useI18n();
    const displayText = text ?? t('Chargement...');
    return (
        <div className="loading">
            <Spinner {...spinnerProps} />
            {displayText && <span className="loading__text">{displayText}</span>}
        </div>
    );
}
