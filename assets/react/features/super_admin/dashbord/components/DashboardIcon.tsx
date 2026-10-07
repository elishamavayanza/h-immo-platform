import type { DashboardIconName } from '../types';

const PATHS: Record<DashboardIconName, string> = {
    building: 'M3 21h18M5 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16M9 8h2m-2 4h2m-2 4h2m5-7h3v11',
    users: 'M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8m6-7a4 4 0 0 1 0 8m2 3h1a4 4 0 0 1 4 4v2',
    briefcase: 'M3 8h18v12H3zM8 8V5h8v3M3 13h18',
    revenue: 'M3 17l6-6 4 4 8-9M14 6h7v7',
    pulse: 'M3 12h4l3-8 4 16 3-8h4',
    shield: 'M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11zM9 12l2 2 4-4',
    server: 'M4 4h16v6H4zM4 14h16v6H4zM8 7h.01M8 17h.01M12 7h4m-4 10h4',
    'arrow-up': 'M12 19V5m-7 7 7-7 7 7',
    'arrow-down': 'M12 5v14m7-7-7 7-7-7',
    minus: 'M5 12h14',
    sparkle: 'm12 3 1.9 5.8L20 11l-6.1 2.2L12 19l-1.9-5.8L4 11l6.1-2.2L12 3zM19 14l1 3 3 1-3 1-1 3-1-3-3-1 3-1z',
    plus: 'M12 5v14m-7-7h14',
    check: 'm5 12 4 4L19 6',
    warning: 'M10.3 3.9 2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4m0 4h.01',
    trash: 'M3 6h18m-2 0-1 15H6L5 6m3 0V4h8v2m-5 4v7m4-7v7',
    login: 'M10 17l5-5-5-5m5 5H3m9-9h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6',
};

export interface DashboardIconProps {
    name: DashboardIconName;
    size?: number;
}

export function DashboardIcon({ name, size = 20 }: DashboardIconProps) {
    return (
        <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d={PATHS[name]} />
        </svg>
    );
}
