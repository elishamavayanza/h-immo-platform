import { DashboardIcon } from './DashboardIcon';
import type { ActivityEntry, ActivityKind, DashboardIconName } from '../types';

const KIND_ICON: Record<ActivityKind, DashboardIconName> = {
    create: 'plus',
    update: 'sparkle',
    delete: 'trash',
    login: 'login',
    alert: 'warning',
};

export interface ActivityFeedProps {
    entries: ActivityEntry[];
}

export function ActivityFeed({ entries }: ActivityFeedProps) {
    return (
        <ol className="sa-feed">
            {entries.map((e) => (
                <li key={e.id} className={`sa-feed__item sa-feed__item--${e.kind}`}>
                    <span className="sa-feed__dot">
                        <DashboardIcon name={KIND_ICON[e.kind]} size={14} />
                    </span>
                    <div className="sa-feed__body">
                        <p className="sa-feed__line">
                            <strong>{e.actor}</strong> {e.action}
                            {e.target ? <> <em>{e.target}</em></> : null}
                        </p>
                        <span className="sa-feed__time">{e.timestamp}</span>
                    </div>
                </li>
            ))}
        </ol>
    );
}
