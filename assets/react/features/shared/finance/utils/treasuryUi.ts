export interface CashCityOption {
    id: string;
}

/** Empêche de garder une ville sélectionnée qui n'appartient plus au contexte actif. */
export function resolveCashCityUuid(
    role: string | null | undefined,
    currentUuid: string,
    allowedCities: readonly CashCityOption[],
    activeCityUuid?: string | null,
): string {
    if (role === 'admin_ville') return allowedCities.some((city) => city.id === activeCityUuid) ? activeCityUuid ?? '' : '';
    return allowedCities.some((city) => city.id === currentUuid) ? currentUuid : allowedCities[0]?.id ?? '';
}

export function requiresCashOpeningConfirmation(openingBalance: string | null | undefined): boolean {
    return openingBalance !== null && openingBalance !== undefined;
}
