import { Card } from '../../../components/UI/Card';

interface LeaseExpiryItem {
    leaseUuid: string;
    leaseReference: string;
    tenantName: string;
    unitLabel: string;
    cityName: string;
    endDate: string;
    daysRemaining: number;
}

/** Liste d’alertes de fin de bail, déjà bornée au périmètre par l’API. */
export function UpcomingLeaseExpirations({ items }: { items: LeaseExpiryItem[] }) {
    return (
        <Card className="organization-panel" padding="medium" header={<div><h2>Contrats à échéance</h2><p>Baux actifs se terminant dans les 30 prochains jours</p></div>}>
            {items.length > 0 ? (
                <ul className="organization-activity" aria-label="Baux arrivant à échéance : liste déroulante" tabIndex={0}>
                    {items.map((item) => (
                        <li key={item.leaseUuid} className="organization-activity__item organization-activity__item--warning">
                            <span className="organization-activity__dot" />
                            <div>
                                <strong>{item.tenantName}</strong>
                                <small>{item.leaseReference} · {item.unitLabel} · {item.cityName}</small>
                            </div>
                            <time dateTime={item.endDate}>{item.daysRemaining === 0 ? 'Aujourd’hui' : `${item.daysRemaining} j`} · {item.endDate.split('-').reverse().join('/')}</time>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="organization-panel__empty">Aucun bail actif n’arrive à échéance dans les 30 prochains jours.</p>
            )}
        </Card>
    );
}
