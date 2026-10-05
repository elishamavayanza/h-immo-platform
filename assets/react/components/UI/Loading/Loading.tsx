import React from 'react';
import { Spinner } from '../Spinner';
import { UseSpinnerProps } from '../../../hook-components/UI/Spinner';

export interface LoadingProps extends UseSpinnerProps {
    text?: string;
}

export function Loading({ text, ...spinnerProps }: LoadingProps) {
    const displayText = text ?? ('Chargement...');
    return (
        <div className="loading">
            <Spinner {...spinnerProps} />
            {displayText && <span className="loading__text">{displayText}</span>}
        </div>
    );
}
