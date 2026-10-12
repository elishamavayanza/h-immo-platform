/** Retourne uniquement les valeurs qui diffèrent du formulaire initial. */
export function changedFields<T extends object>(
    current: T,
    initial: Partial<T>,
): Partial<T> {
    return Object.fromEntries(
        Object.entries(current).filter(([key, value]) => !sameValue(value, (initial as Record<string, unknown>)[key])),
    ) as Partial<T>;
}

export function hasChangedFields<T extends object>(
    current: T,
    initial: Partial<T>,
): boolean {
    return Object.keys(changedFields(current, initial)).length > 0;
}

function sameValue(left: unknown, right: unknown): boolean {
    if (Object.is(left, right)) return true;
    if (left instanceof Date && right instanceof Date) return left.getTime() === right.getTime();
    if (Array.isArray(left) && Array.isArray(right)) {
        return left.length === right.length && left.every((value, index) => sameValue(value, right[index]));
    }
    return false;
}
