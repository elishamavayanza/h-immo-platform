import React from 'react';
import { usePagination, UsePaginationProps } from '../../../../assets/react/hook-components/Data/Pagination';

export interface PaginationProps extends UsePaginationProps {}

export function Pagination({ totalItems, pageSize, initialPage, currentPage, siblingCount, onPageChange, className }: PaginationProps) {
    const { currentPage: activePage, totalPages, pages, goToPage, goToNext, goToPrev, classes } = usePagination({
        totalItems,
        pageSize,
        initialPage,
        currentPage,
        siblingCount,
        onPageChange,
        className,
    });

    return (
        <nav className={classes} aria-label="Pagination">
            <button className="pagination__button" onClick={goToPrev} disabled={activePage === 1}>
                {('Précédent')}
            </button>
            {pages.map((page, index) => {
                if (page === 'DOTS') {
                    return <span key={`dots-${index}`} className="pagination__dots">...</span>;
                }
                return (
                    <button
                        key={page}
                        className={`pagination__button ${page === activePage ? 'pagination__button--active' : ''}`}
                        onClick={() => goToPage(page as number)}
                        aria-current={page === activePage ? 'page' : undefined}
                    >
                        {page}
                    </button>
                );
            })}
            <button className="pagination__button" onClick={goToNext} disabled={activePage === totalPages}>
                {('Suivant')}
            </button>
        </nav>
    );
}
