import React from 'react';
import { useStepper, UseStepperProps, StepperStep } from '../../../hook-components/Navigation/Stepper';
import { useI18n } from '@/react/i18n/I18nContext';

export interface StepperProps extends UseStepperProps {
    children?: (activeStepId: string) => React.ReactNode; // contenu affiché sous le stepper
}

export function Stepper({
                            steps,
                            activeStepId,
                            initialStepId,
                            variant = 'horizontal',
                            size = 'medium',
                            showDescription = false,
                            onStepChange,
                            className,
                            children,
                        }: StepperProps) {
    const { t } = useI18n();
    const { classes, activeStepId: currentStepId, activeIndex, goToStep } = useStepper({
        steps,
        activeStepId,
        initialStepId,
        variant,
        size,
        showDescription,
        onStepChange,
        className,
    });

    return (
        <div className={classes}>
            <ol className="stepper__list">
                {steps.map((step, index) => {
                    const isActive = step.id === currentStepId;
                    const isCompleted = index < activeIndex;
                    const isDisabled = step.disabled;

                    return (
                        <li
                            key={step.id}
                            className={`stepper__step ${
                                isActive ? 'stepper__step--active' : ''
                            } ${isCompleted ? 'stepper__step--completed' : ''} ${
                                isDisabled ? 'stepper__step--disabled' : ''
                            }`}
                        >
                            <button
                                type="button"
                                className="stepper__step-button"
                                onClick={() => goToStep(step.id)}
                                disabled={isDisabled}
                                aria-current={isActive ? 'step' : undefined}
                            >
                <span className="stepper__indicator">
                  {step.icon || <span className="stepper__number">{index + 1}</span>}
                </span>
                                <span className="stepper__content">
                  <span className="stepper__label">{typeof step.label === 'string' ? t(step.label) : step.label}</span>
                                    {showDescription && step.description && (
                                        <span className="stepper__description">{typeof step.description === 'string' ? t(step.description) : step.description}</span>
                                    )}
                </span>
                            </button>
                            {index < steps.length - 1 && <span className="stepper__connector" aria-hidden="true" />}
                        </li>
                    );
                })}
            </ol>
            {children && <div className="stepper__body">{children(currentStepId)}</div>}
        </div>
    );
}
