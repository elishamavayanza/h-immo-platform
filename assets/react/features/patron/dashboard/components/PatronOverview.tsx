import { Card } from '../../../../components/UI/Card';
import type { PatronDashboardData } from '../types/patronDashboard.types';

export function PatronOverview({ data }: { data: PatronDashboardData }) {
    return <>
        <section className="patron-kpis">{data.metrics.map((metric) => <Card key={metric.id} className={`patron-kpi patron-kpi--${metric.tone}`} padding="medium"><span>{metric.label}</span><strong>{metric.value}</strong><small>{metric.detail}</small></Card>)}</section>
        <section className="patron-dashboard-grid">
            <Card className="patron-panel" padding="medium" header={<div><h2>Encaissements du mois</h2><p>Suivi global des loyers perçus</p></div>}>
                <div className="patron-collection"><strong>{data.rentCollected}</strong><span>88 % de l’objectif mensuel</span><div className="patron-progress"><span style={{ width: `${data.occupancy}%` }} /></div><small>{data.occupancy}% d’occupation du patrimoine</small></div>
            </Card>
            <Card className="patron-panel" padding="medium" header={<div><h2>Activité récente</h2><p>Derniers mouvements de votre portefeuille</p></div>}>
                <ul className="patron-activity">{data.activities.map((item) => <li key={item.id} className={`patron-activity__item patron-activity__item--${item.status}`}><span className="patron-activity__dot" /><div><strong>{item.title}</strong><small>{item.detail}</small></div><time>{item.date}</time></li>)}</ul>
            </Card>
        </section>
    </>;
}
