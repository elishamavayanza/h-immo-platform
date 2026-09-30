import { useState, useMemo } from 'react';

export interface UsePaginationProps {
    totalItems: number;
    pageSize?: number;
    initialPage?: number;
    /** Mode contrôlé : la page courante est imposée par le parent (pagination serveur). */
    currentPage?: number;
    siblingCount?: number;
    onPageChange?: (page: number) => void;
    className?: string;
}

export function usePagination({
                                   totalItems,
                                   pageSize = 10,
                                   initialPage = 1,
                                   currentPage: externalCurrentPage,
                                   siblingCount = 1,
                                   onPageChange,
                                   className = '',
                               }: UsePaginationProps) {
    const [internalPage, setInternalPage] = useState(initialPage);

    const currentPage = externalCurrentPage ?? internalPage;

    const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));

    const goToPage = (page: number) => {
        if (page < 1 || page > totalPages) return;
        setInternalPage(page);
        onPageChange?.(page);
    };

    const goToNext = () => goToPage(currentPage + 1);
    const goToPrev = () => goToPage(currentPage - 1);

    const pages = useMemo(() => {
        const totalPageNumbers = siblingCount * 2 + 5;
        if (totalPages <= totalPageNumbers) {
            return Array.from({ length: totalPages }, (_, i) => i + 1);
        }
        const leftSibling = Math.max(currentPage - siblingCount, 1);
        const rightSibling = Math.min(currentPage + siblingCount, totalPages);
        const shouldShowLeftDots = leftSibling > 2;
        const shouldShowRightDots = rightSibling < totalPages - 2;
        const firstPage = 1;
        const lastPage = totalPages;

        if (!shouldShowLeftDots && shouldShowRightDots) {
            const leftItemCount = 3 + 2 * siblingCount;
            const leftRange = Array.from({ length: leftItemCount }, (_, i) => i + 1);
            return [...leftRange, 'DOTS', lastPage];
        }

        if (shouldShowLeftDots && !shouldShowRightDots) {
            const rightItemCount = 3 + 2 * siblingCount;
            const rightRange = Array.from({ length: rightItemCount }, (_, i) => totalPages - rightItemCount + i + 1);
            return [firstPage, 'DOTS', ...rightRange];
        }

        if (shouldShowLeftDots && shouldShowRightDots) {
            const middleRange = Array.from({ length: rightSibling - leftSibling + 1 }, (_, i) => leftSibling + i);
            return [firstPage, 'DOTS', ...middleRange, 'DOTS', lastPage];
        }

        return [];
    }, [currentPage, totalPages, siblingCount]);

    const classes = useMemo(() => {
        return ['pagination', className].filter(Boolean).join(' ');
    }, [className]);

    return {
        currentPage,
        totalPages,
        pages,
        goToPage,
        goToNext,
        goToPrev,
        classes,
    };
}
