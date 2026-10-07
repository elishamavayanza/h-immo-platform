import { Card } from '../../../../components/UI/Card';
import { Skeleton } from '../../../../components/UI/Skeleton';

export function DashboardSkeleton() {
    return (
        <div className="sa-dashboard sa-dashboard--loading" aria-busy="true">
            <Skeleton variant="rect" className="sa-skel--header" />
            <div className="sa-kpi-grid">
                {Array.from({ length: 4 }, (_, i) => (
                    <Card key={i} className="sa-dashboard-card sa-skel-card" padding="none">
                        <Skeleton variant="rect" className="sa-skel--kpi" />
                    </Card>
                ))}
            </div>
            <div className="sa-grid sa-grid--2-1">
                <Card className="sa-dashboard-card sa-skel-card" padding="none">
                    <Skeleton variant="rect" className="sa-skel--chart" />
                </Card>
                <Card className="sa-dashboard-card sa-skel-card" padding="none">
                    <Skeleton variant="rect" className="sa-skel--panel" />
                </Card>
            </div>
        </div>
    );
}
