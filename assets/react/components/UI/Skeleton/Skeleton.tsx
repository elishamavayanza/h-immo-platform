import React from 'react';
import { useSkeleton, UseSkeletonProps } from '../../../hook-components/UI/Skeleton';

export interface SkeletonProps extends UseSkeletonProps {}

export function Skeleton({ variant, size, width, height, className }: SkeletonProps) {
    const { classes, style } = useSkeleton({ variant, size, width, height, className });
    return <div className={classes} style={style} aria-hidden="true" />;
}
