import type { ReactNode } from 'react';

import { DashboardIcon } from '../dashbord/components/DashboardIcon';

export interface PlatformPageHeaderProps {
    title: string;
    description: string;
    icon: 'briefcase' | 'users';
    action?: ReactNode;
}

export function PlatformPageHeader({ title, description, icon, action }: PlatformPageHeaderProps) {
    return (
        <header className="sa-header">
            <div className="sa-header__intro">
                <span className="sa-header__eyebrow">
                    <DashboardIcon name="shield" size={14} />
                    Administration de la plateforme
                </span>
                <h1 className="sa-header__title">
                    <DashboardIcon name={icon} size={24} />
                    {title}
                </h1>
                <p className="sa-header__subtitle">{description}</p>
            </div>
            {action && <div className="sa-header__actions">{action}</div>}
        </header>
    );
}
