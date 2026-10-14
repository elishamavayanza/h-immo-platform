const recentActions: string[] = [];
let initialized = false;

function scrub(value: string, limit: number): string {
    return value
        .replace(/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/gi, '[email masqué]')
        .replace(/\b\+?\d[\d ()-]{7,}\d\b/g, '[numéro masqué]')
        .replace(/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/g, '[jeton masqué]')
        .replace(/\s+/g, ' ')
        .slice(0, limit);
}

function recordAction(action: string): void {
    const timestamp = new Date().toISOString().slice(11, 19);
    recentActions.push(`${timestamp} · ${window.location.pathname.slice(0, 200)} · ${scrub(action, 120)}`);
    if (recentActions.length > 12) recentActions.shift();
}

function controlLabel(element: Element | null): string {
    if (!element) return 'interaction';
    const label = element.getAttribute('aria-label')
        || element.getAttribute('title')
        || element.textContent?.replace(/\s+/g, ' ').trim()
        || element.tagName.toLowerCase();
    return scrub(label, 100) || element.tagName.toLowerCase();
}

/** Capture les actions de navigation et de formulaire, jamais les valeurs saisies. */
export function initializeUserActionTrail(): void {
    if (initialized || typeof document === 'undefined') return;
    initialized = true;

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target.closest('button, a, [role="button"]') : null;
        if (!target) return;
        const href = target instanceof HTMLAnchorElement ? target.getAttribute('href') : null;
        recordAction(href ? `navigation · ${controlLabel(target)}` : `clic ${target.tagName.toLowerCase()} · ${controlLabel(target)}`);
    }, true);

    document.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement || target instanceof HTMLSelectElement || target instanceof HTMLTextAreaElement)) return;
        if (target instanceof HTMLInputElement && ['password', 'email', 'tel', 'search'].includes(target.type)) return;
        const label = target.labels?.[0]?.textContent?.replace(/\s+/g, ' ').trim() || target.getAttribute('aria-label') || target.name || 'champ';
        recordAction(`changement de filtre ou champ · ${scrub(label, 100)} (valeur non enregistrée)`);
    }, true);

    document.addEventListener('submit', (event) => {
        if (!(event.target instanceof HTMLFormElement)) return;
        const submitter = event.submitter instanceof Element ? event.submitter : null;
        recordAction(`soumission de formulaire · ${controlLabel(submitter)}`);
    }, true);
}

export function getRecentUserActions(): string {
    return recentActions.join('\n').slice(0, 1800);
}
