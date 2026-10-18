export interface PwaInstallationSnapshot {
    installed: boolean;
    canPrompt: boolean;
}

interface BeforeInstallPromptEvent extends Event {
    prompt(): Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>;
}

type Listener = () => void;

const listeners = new Set<Listener>();
let deferredPrompt: BeforeInstallPromptEvent | null = null;
let initialized = false;

const isStandalone = (): boolean => {
    if (typeof window === 'undefined') return false;
    const iosNavigator = navigator as Navigator & { standalone?: boolean };
    return window.matchMedia('(display-mode: standalone)').matches || iosNavigator.standalone === true;
};

let snapshot: PwaInstallationSnapshot = { installed: isStandalone(), canPrompt: false };

function publish(): void {
    snapshot = { installed: isStandalone(), canPrompt: deferredPrompt !== null };
    listeners.forEach((listener) => listener());
}

/** Installe le listener tôt pour conserver l’événement d’installation avant l’ouverture des paramètres. */
export function initializePwaInstallation(): void {
    if (initialized || typeof window === 'undefined') return;
    initialized = true;

    window.addEventListener('beforeinstallprompt', (event: Event) => {
        // L’application propose l’installation dans les paramètres, après le choix explicite de l’utilisateur.
        event.preventDefault();
        deferredPrompt = event as BeforeInstallPromptEvent;
        publish();
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        publish();
    });

    window.matchMedia('(display-mode: standalone)').addEventListener('change', publish);
    publish();
}

export function subscribeToPwaInstallation(listener: Listener): () => void {
    listeners.add(listener);
    return () => listeners.delete(listener);
}

export function getPwaInstallationSnapshot(): PwaInstallationSnapshot {
    return snapshot;
}

export async function requestPwaInstallation(): Promise<'accepted' | 'dismissed' | 'unavailable'> {
    const prompt = deferredPrompt;
    if (!prompt) return 'unavailable';

    deferredPrompt = null;
    publish();
    await prompt.prompt();
    const { outcome } = await prompt.userChoice;
    publish();
    return outcome;
}
