import { Card } from '../../../../components/UI/Card';
import type { ExchangeRateRecord } from '../types/exchangeRate.types';

export function ExchangeRateSummary({ current }: { current: ExchangeRateRecord }) {
    return <Card className="sa-exchange-summary" padding="medium">
        <div className="sa-exchange-summary__rate">
            <span>Taux de référence actuel</span>
            <strong>{current.rate} CDF</strong>
            <small>pour 1 USD</small>
        </div>
        <dl className="sa-exchange-summary__details">
            <div><dt>En vigueur depuis</dt><dd>{current.effectiveAt}</dd></div>
            <div><dt>Source</dt><dd>{current.source}</dd></div>
            <div><dt>Mis à jour par</dt><dd>{current.updatedBy}</dd></div>
        </dl>
    </Card>;
}
