import { Card } from '../../../../components/UI/Card';
import type { PatronDashboardData } from '../types/patronDashboard.types';

export function PatronOverview({ data }: { data: PatronDashboardData }) {
    return <>
        <section className="organization-kpis">{data.metrics.map((metric) => <Card key={metric.id} className={`organization-kpi organization-kpi--${metric.tone}`} padding="medium"><span>{metric.label}</span><strong>{metric.value}</strong><small>{metric.detail}</small></Card>)}</section>
        <section className="organization-dashboard-grid">
            <Card className="organization-panel" padding="medium" header={<div><h2>Encaissements du mois</h2><p>Suivi global des loyers perçus</p></div>}>
                <div className="organization-collection"><strong>{data.rentCollected}</strong><span>88 % de l’objectif mensuel</span><div className="organization-progress"><span style={{ width: `${data.occupancy}%` }} /></div><small>{data.occupancy}% d’occupation du patrimoine</small></div>
            </Card>
            <Card className="organization-panel" padding="medium" header={<div><h2>Activité récente</h2><p>Derniers mouvements de votre portefeuille</p></div>}>
                <ul className="organization-activity">{data.activities.map((item) => <li key={item.id} className={`organization-activity__item organization-activity__item--${item.status}`}><span className="organization-activity__dot" /><div><strong>{item.title}</strong><small>{item.detail}</small></div><time>{item.date}</time></li>)}</ul>
            </Card>
        </section>
    </>;
}
