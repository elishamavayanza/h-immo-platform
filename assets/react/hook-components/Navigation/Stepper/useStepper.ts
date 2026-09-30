import { useState, useMemo } from 'react';

export type StepperVariant = 'horizontal' | 'vertical';
export type StepperSize = 'small' | 'medium' | 'large';

export interface StepperStep {
    id: string;
    label: React.ReactNode;
    description?: React.ReactNode;
    icon?: React.ReactNode;
    disabled?: boolean;
}

export interface UseStepperProps {
    steps: StepperStep[];
    activeStepId?: string;
    initialStepId?: string;
    variant?: StepperVariant;
    size?: StepperSize;
    showDescription?: boolean;
    onStepChange?: (stepId: string) => void;
    className?: string;
}

export function useStepper({
                               steps,
                               activeStepId: controlledActiveStepId,
                               initialStepId,
                               variant = 'horizontal',
                               size = 'medium',
                               showDescription = false,
                               onStepChange,
                               className = '',
                           }: UseStepperProps) {
    const [internalActiveStepId, setInternalActiveStepId] = useState(
        initialStepId || (steps.length > 0 ? steps[0].id : '')
    );

    const activeStepId = controlledActiveStepId ?? internalActiveStepId;

    const activeIndex = steps.findIndex((step) => step.id === activeStepId);

    const goToStep = (stepId: string) => {
        const step = steps.find((s) => s.id === stepId);
        if (step && !step.disabled) {
            if (controlledActiveStepId === undefined) {
                setInternalActiveStepId(stepId);
            }
            onStepChange?.(stepId);
        }
    };

    const goToNext = () => {
        if (activeIndex < steps.length - 1) {
            const nextStep = steps[activeIndex + 1];
            goToStep(nextStep.id);
        }
    };

    const goToPrevious = () => {
        if (activeIndex > 0) {
            const prevStep = steps[activeIndex - 1];
            goToStep(prevStep.id);
        }
    };

    const classes = useMemo(() => {
        const base = 'stepper';
        const variantClass = `stepper--${variant}`;
        const sizeClass = `stepper--${size}`;
        return [base, variantClass, sizeClass, className].filter(Boolean).join(' ');
    }, [variant, size, className]);

    return {
        classes,
        steps,
        activeStepId,
        activeIndex,
        goToStep,
        goToNext,
        goToPrevious,
    };
}
