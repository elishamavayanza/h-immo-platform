// ============================================================
// assets/react/app/pages/sections/SectionLayout.tsx
// Enveloppe commune d'une « grande section » du menu.
//
// Chaque section est chargée par `React.lazy` (découpage du bundle) et
// rend ses feuilles via `<Outlet/>` (Pattern placeholders à venir).
// ============================================================

import { Outlet } from 'react-router-dom';

interface SectionLayoutProps {
    title: string;
}

export function SectionLayout({ title }: SectionLayoutProps) {
    return (
        <div className="app-section">
            <h2 className="app-section__title">{title}</h2>
            <div className="app-section__body">
                <Outlet />
            </div>
        </div>
    );
}