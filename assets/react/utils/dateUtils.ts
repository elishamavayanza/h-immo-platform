/** Formate une date civile pour un champ HTML date sans conversion UTC. */
export function localDateInputValue(date: Date = new Date()): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

/** Présente une date civile stockée sous la forme YYYY-MM-DD sans décalage de fuseau. */
export function formatCivilDate(value: string): string {
    const datePart = value.slice(0, 10);
    const [year, month, day] = datePart.split('-').map(Number);
    if (!year || !month || !day) return value;
    return new Date(year, month - 1, day).toLocaleDateString('fr-FR');
}
