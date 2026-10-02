/**
 * Convertit une date au format 'YYYY-MM-DD' en objet Date (local).
 */
export function parseDateToLocal(dateString: string): Date | null {
    if (!dateString) return null;
    const [year, month, day] = dateString.split('-').map(Number);
    return new Date(year, month - 1, day);
}

/**
 * Convertit une Date en chaîne 'YYYY-MM-DD' (format API).
 */
export function formatDateToApi(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Convertit une chaîne 'HH:mm' en minutes depuis minuit.
 */
export function timeToMinutes(timeString: string): number {
    const [hours, minutes] = timeString.split(':').map(Number);
    return hours * 60 + minutes;
}

/**
 * Convertit un nombre de minutes depuis minuit en chaîne 'HH:mm'.
 */
export function minutesToTime(minutes: number): string {
    const h = Math.floor(minutes / 60).toString().padStart(2, '0');
    const m = (minutes % 60).toString().padStart(2, '0');
    return `${h}:${m}`;
}

/**
 * Formate une date et une heure en chaîne ISO (ex: '2026-08-30T14:30:00').
 */
export function combineDateAndTime(dateString: string, timeString: string): string {
    return `${dateString}T${timeString}:00`;
}

/**
 * Convertit une date ISO en date/heure séparées.
 */
export function splitIsoDateTime(iso: string): { date: string; time: string } {
    const [date, time] = iso.split('T');
    return { date, time: time?.slice(0, 5) ?? '' };
}

/**
 * Retourne la date du jour au format 'YYYY-MM-DD'.
 */
export function todayApi(): string {
    return formatDateToApi(new Date());
}

/**
 * Retourne l'heure actuelle au format 'HH:mm'.
 */
export function currentTimeApi(): string {
    const now = new Date();
    return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
}
