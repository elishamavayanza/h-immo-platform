import { useCallback, useSyncExternalStore } from 'react';
import {
    getPwaInstallationSnapshot,
    requestPwaInstallation,
    subscribeToPwaInstallation,
} from '../../../../../services/pwaInstallation';

export function usePwaInstallation() {
    const installation = useSyncExternalStore(
        subscribeToPwaInstallation,
        getPwaInstallationSnapshot,
        getPwaInstallationSnapshot,
    );
    const install = useCallback(() => requestPwaInstallation(), []);

    return { ...installation, install };
}
