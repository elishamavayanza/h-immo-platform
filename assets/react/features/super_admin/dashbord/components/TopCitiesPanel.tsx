import type { CityShare } from '../types';

export interface TopCitiesPanelProps {
    cities: CityShare[];
}

export function TopCitiesPanel({ cities }: TopCitiesPanelProps) {
    return (
        <ul className="sa-cities">
            {cities.map((c) => (
                <li key={c.name} className="sa-cities__row">
                    <span className="sa-cities__name">{c.name}</span>
                    <span className="sa-cities__bar">
                        <span style={{ width: `${c.share * 100}%` }} />
                    </span>
                    <span className="sa-cities__count">{c.count}</span>
                </li>
            ))}
        </ul>
    );
}
