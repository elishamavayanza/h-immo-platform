import { DashboardIcon } from '../../icons/DashboardIcon';
import type { DashboardActivityKind, PlatformActivityEntry } from '../types';

const KIND_ICON: Record<DashboardActivityKind, 'plus' | 'sparkle' | 'warning'> = {
    create: 'plus',
    update: 'sparkle',
    alert: 'warning',
};

export interface ActivityFeedProps {
    entries: PlatformActivityEntry[];
}

export function ActivityFeed({ entries }: ActivityFeedProps) {
    return (
        entries.length === 0 ? (
            <p className="sa-feed__empty">Aucune action d’administration récente.</p>
        ) : (
            <ol className="sa-feed">
                {entries.map((entry) => (
                    <li key={entry.id} className={`sa-feed__item sa-feed__item--${entry.kind}`}>
                        <span className="sa-feed__dot"><DashboardIcon name={KIND_ICON[entry.kind]} size={14} /></span>
                        <div className="sa-feed__body">
                            <p className="sa-feed__line">{entry.action}</p>
                            <span className="sa-feed__time">{entry.timestamp}</span>
                        </div>
                    </li>
                ))}
            </ol>
        )
    );
}
