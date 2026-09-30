import { useOnlineStatus } from '@/react/hooks/useNetwork';
import { useI18n } from '@/react/i18n/I18nContext';

/**
 * Bannière globale affichée lorsque l'appareil est hors-ligne.
 * Les requêtes API sont alors mises en échec immédiat côté client
 * (voir services/api/client.ts) ; la bannière informe l'utilisateur.
 */
export function OfflineBanner() {
    const { t } = useI18n();
    const isOffline = useOnlineStatus();

    if (!isOffline) {
        return null;
    }

    return (
        <div className="offline-banner" role="status" aria-live="polite">
            <span className="offline-banner__dot" aria-hidden="true" />
            <span>{t("Vous êtes hors-ligne. Les modifications ne seront pas synchronisées tant que la connexion n'est pas rétablie.")}</span>
        </div>
    );
}