import { Card } from '../../../../components/UI/Card';
import type { ExchangeRateRecord } from '../types/exchangeRate.types';

export function ExchangeRateSummary({ current }: { current: ExchangeRateRecord }) {
    return <div className="sa-management-stats sa-exchange-summary">
        <Card className="sa-management-stat" padding="medium"><span>Taux actuel</span><strong>{current.rate}</strong><small>1 USD en CDF</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Dernière mise à jour</span><strong className="sa-exchange-summary__date">{current.effectiveAt}</strong><small>Par {current.updatedBy}</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Devise de référence</span><strong className="sa-exchange-summary__date">USD → CDF</strong><small>Dollar américain vers franc congolais</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Source</span><strong className="sa-exchange-summary__date">{current.source}</strong><small>Historique des changements ci-dessous</small></Card>
    </div>;
}
