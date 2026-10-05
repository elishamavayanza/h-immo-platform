import type { ReactNode } from 'react';

const STROKE = {
    viewBox: '0 0 24 24',
    width: 28,
    height: 28,
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 2,
    strokeLinecap: 'round' as const,
    strokeLinejoin: 'round' as const,
};

const IconGauge = () => (
    <svg {...STROKE}>
        <path d="M12 21a9 9 0 1 1 9-9" />
        <path d="M12 12 16 8" />
        <path d="M12 21v-1M4.2 15H3M6 7.8 5.3 7M21 15h-1.2M18 7.8l.7-.8" />
    </svg>
);
const IconBuilding = () => (
    <svg {...STROKE}>
        <path d="M3 21h18" />
        <path d="M5 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16" />
        <path d="M16 9h3a1 1 0 0 1 1 1v11" />
        <path d="M9 8h2M9 12h2M9 16h2" />
    </svg>
);
const IconKey = () => (
    <svg {...STROKE}>
        <circle cx="8" cy="15" r="4" />
        <path d="M10.8 12.2 21 2" />
        <path d="M17 6l3 3" />
    </svg>
);
const IconCoins = () => (
    <svg {...STROKE}>
        <circle cx="9" cy="9" r="6" />
        <path d="M14.5 5.5a6 6 0 1 1-9 9" />
        <path d="M9 6v6M6.5 8h5" />
    </svg>
);
const IconStorefront = () => (
    <svg {...STROKE}>
        <path d="M4 10 5.5 4h13L20 10" />
        <path d="M4 10a2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0" />
        <path d="M5 13v7h14v-7" />
        <path d="M10 20v-4h4v4" />
    </svg>
);
const IconIdBadge = () => (
    <svg {...STROKE}>
        <rect x="5" y="3" width="14" height="18" rx="2" />
        <circle cx="12" cy="10" r="2.5" />
        <path d="M8 17c.6-2 2-3 4-3s3.4 1 4 3" />
        <path d="M9 3v2M15 3v2" />
    </svg>
);
const IconHardHat = () => (
    <svg {...STROKE}>
        <path d="M3 18h18" />
        <path d="M8 18v-6a4 4 0 0 1 4-4 4 4 0 0 1 4 4v6" />
        <path d="M12 5a7 7 0 0 0-7 7v-1.5a1 1 0 0 0-2 0V12" />
        <path d="M19 12v-1.5a1 1 0 0 1 2 0V12" />
    </svg>
);
const IconUsers = () => (
    <svg {...STROKE}>
        <circle cx="9" cy="8" r="3.5" />
        <path d="M3.5 20a5.5 5.5 0 0 1 11 0" />
        <path d="M16 8a3 3 0 0 1 3 3" />
        <path d="M17 14.5a4.5 4.5 0 0 1 3.5 5.5" />
    </svg>
);
const IconBriefcase = () => (
    <svg {...STROKE}>
        <rect x="3" y="8" width="18" height="12" rx="2" />
        <path d="M8 8V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
        <path d="M3 13h18" />
        <path d="M10 13v2h4v-2" />
    </svg>
);
const IconAudit = () => (
    <svg {...STROKE}>
        <path d="M6 10h12M6 6h12M6 14h12M6 18h7" />
        <path d="m15 18 2 2 3.5-4" />
    </svg>
);
const IconExchange = () => (
    <svg {...STROKE}>
        <path d="M3 8h13l-3-3M21 16H8l3 3" />
        <path d="M16 5l5 3M3 16l5-3" />
    </svg>
);

const ICONS: { name: string; label: string; usedFor: string; Icon: () => ReactNode }[] = [
    { name: 'gauge', label: 'Tableau de bord', usedFor: 'tous les rôles, 1re entrée', Icon: IconGauge },
    { name: 'building', label: 'Patrimoine', usedFor: 'villes/parcelles/bâtiments/unités', Icon: IconBuilding },
    { name: 'key', label: 'Location', usedFor: 'locataires/baux/loyers/paiements', Icon: IconKey },
    { name: 'coins', label: 'Dépenses', usedFor: 'feuille directe', Icon: IconCoins },
    { name: 'storefront', label: 'Vitrine', usedFor: 'annonces publiques', Icon: IconStorefront },
    { name: 'id-badge', label: 'Administration', usedFor: 'équipe & rôles — PATRON', Icon: IconIdBadge },
    { name: 'hard-hat', label: 'Personnel', usedFor: 'ouvriers/affectations', Icon: IconHardHat },
    { name: 'users', label: 'Utilisateurs', usedFor: 'plateau SUPER_ADMIN', Icon: IconUsers },
    { name: 'briefcase', label: 'Organisations', usedFor: 'entreprises clientes — SUPER_ADMIN', Icon: IconBriefcase },
    { name: 'audit', label: "Journal d'audit", usedFor: 'plateforme & organisation', Icon: IconAudit },
    { name: 'exchange', label: 'Taux de change', usedFor: 'plateau SUPER_ADMIN', Icon: IconExchange },
];

export default function IconPreview() {
    return (
        <div style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fill, minmax(190px, 1fr))',
            gap: 16,
            padding: 24,
            background: '#0f172a',
            color: '#e2e8f0',
            fontFamily: 'system-ui, sans-serif',
            minHeight: '100vh',
        }}>
            {ICONS.map(({ name, label, usedFor, Icon }) => (
                <div key={name} style={{
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    textAlign: 'center',
                    gap: 8,
                    padding: 16,
                    borderRadius: 10,
                    background: '#1e293b',
                    border: '1px solid #334155',
                }}>
                    <Icon />
                    <strong style={{ fontSize: 14 }}>{label}</strong>
                    <code style={{ fontSize: 11, color: '#94a3b8' }}>icon: '{name}'</code>
                    <span style={{ fontSize: 11, color: '#64748b' }}>{usedFor}</span>
                </div>
            ))}
        </div>
    );
}
