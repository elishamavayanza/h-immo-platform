export interface TreasuryPage<T> {
    items: T[];
    total: number;
    limit?: number;
}

/** Charge toutes les pages, ou échoue explicitement sans présenter un total partiel. */
export async function loadCompletePages<T extends { id: string }>(
    firstPage: TreasuryPage<T>,
    fetchPage: (page: number) => Promise<TreasuryPage<T>>,
    label: string,
    defaultPageSize = 100,
): Promise<T[]> {
    const pageSize = firstPage.limit || defaultPageSize;
    const pageCount = Math.ceil(firstPage.total / pageSize);
    if (pageCount > 50) {
        throw new Error(`Cette période contient plus de 5 000 ${label}. Réduisez la période pour afficher un total complet.`);
    }

    const remainingPages = await Promise.all(
        Array.from({ length: Math.max(0, pageCount - 1) }, (_, index) => fetchPage(index + 2)),
    );
    if (remainingPages.some((page) => page.total !== firstPage.total || (page.limit && page.limit !== pageSize))) {
        throw new Error(`Les ${label} ont changé pendant leur chargement. Actualisez la période pour obtenir un total fiable.`);
    }

    const items = [firstPage, ...remainingPages].flatMap((page) => page.items);
    const uniqueItems = new Map(items.map((item) => [item.id, item]));
    if (uniqueItems.size !== firstPage.total) {
        throw new Error(`La liste des ${label} est incomplète. Réessayez ou réduisez la période.`);
    }

    return [...uniqueItems.values()];
}
