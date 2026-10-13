import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useLocation, useNavigate } from 'react-router-dom';

import { useOrganization } from '../../providers/OrganizationProvider';
import { useAdminCity } from '../../providers/AdminCityProvider';
import { buildPatrimoinePath } from '../../../features/admin_immobilier/patrimoine/components/patrimoineRouting';
import { useAuth } from '../../providers/AuthProvider';
import { defaultPathFor } from './sidebar/sidebar.config';
import type { OrganizationRole, SessionOrganizationMembership } from '../../../../services/api/api.types';

const ROLE_NAMES: Record<OrganizationRole, string> = {
    patron: 'Espace Patron',
    admin_immobilier: 'Administration immobilière',
    admin_ville: 'Administration de ville',
};

export function WorkspaceSwitcher({ mobile = false, open, onOpenChange }: { mobile?: boolean; open: boolean; onOpenChange: (open: boolean) => void }) {
    const { workspaces, currentOrganization, organizationRole, platformRole, switchWorkspace, isSwitching } = useOrganization();
    const { cities, activeCity, selectCity } = useAdminCity();
    const { refreshUser } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const triggerRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);
    const [position, setPosition] = useState({ left: 8, top: 8, width: 280, maxHeight: 360, placement: 'above' as 'above' | 'below' });

    const currentKey = currentOrganization ? `${currentOrganization.uuid}:${currentOrganization.role}` : null;
    const updatePosition = () => {
        const rect = triggerRef.current?.getBoundingClientRect();
        if (!rect) return;
        const gutter = mobile ? 12 : 8;
        const width = Math.min(mobile ? 340 : 300, window.innerWidth - gutter * 2);
        const left = mobile
            ? Math.max(gutter, Math.min(rect.left, window.innerWidth - width - gutter))
            : Math.max(gutter, Math.min(rect.left, window.innerWidth - width - gutter));
        const availableAbove = Math.max(0, rect.top - gutter - 8);
        const availableBelow = Math.max(0, window.innerHeight - rect.bottom - gutter - 8);
        const placement = mobile
            ? (availableBelow >= Math.min(220, availableAbove) ? 'below' : 'above')
            : (availableAbove >= 260 || availableAbove >= availableBelow ? 'above' : 'below');
        const available = placement === 'above' ? availableAbove : availableBelow;
        const maxHeight = Math.max(150, Math.min(mobile ? 250 : 360, Math.round(window.innerHeight * (mobile ? 0.34 : 0.62)), available));
        setPosition({
            left,
            top: placement === 'above' ? Math.max(gutter, rect.top - 8) : Math.min(rect.bottom + 8, window.innerHeight - gutter),
            width,
            maxHeight,
            placement,
        });
    };

    useEffect(() => {
        if (!open) return;
        updatePosition();
        const onPointer = (event: PointerEvent) => {
            if (!menuRef.current?.contains(event.target as Node) && !triggerRef.current?.contains(event.target as Node)) onOpenChange(false);
        };
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') { onOpenChange(false); triggerRef.current?.focus(); }
        };
        document.addEventListener('pointerdown', onPointer);
        document.addEventListener('keydown', onKey);
        window.addEventListener('resize', updatePosition);
        window.addEventListener('scroll', updatePosition, true);
        return () => {
            document.removeEventListener('pointerdown', onPointer);
            document.removeEventListener('keydown', onKey);
            window.removeEventListener('resize', updatePosition);
            window.removeEventListener('scroll', updatePosition, true);
        };
    }, [open, onOpenChange]);

    const canSwitchCity = organizationRole === 'admin_ville' && cities.length > 1;
    if (platformRole || (workspaces.length < 2 && !canSwitchCity)) return null;

    const chooseWorkspace = async (workspace: SessionOrganizationMembership) => {
        const keepOpenForCitySelection = workspace.role === 'admin_ville';
        if (`${workspace.uuid}:${workspace.role}` === currentKey || isSwitching) {
            onOpenChange(keepOpenForCitySelection);
            return;
        }
        try {
            await switchWorkspace(workspace);
            onOpenChange(keepOpenForCitySelection);
            navigate(defaultPathFor(null, workspace.role), { replace: true });
        } catch {
            onOpenChange(keepOpenForCitySelection);
        }
    };

    const menu = open ? (
        <div
            ref={menuRef}
            className={`main-layout__workspace-menu${mobile ? ' main-layout__workspace-menu--mobile' : ''}`}
            role="menu"
            aria-label="Espaces de travail et villes disponibles"
            style={{
                left: position.left,
                top: position.top,
                width: position.width,
                maxHeight: position.maxHeight,
                transform: position.placement === 'above' ? 'translateY(-100%)' : undefined,
            }}
        >
            {(workspaces.length > 1 || !canSwitchCity) && <div className="main-layout__workspace-heading">Changer d’espace</div>}
            {workspaces.length > 1 && <div className="main-layout__workspace-heading">Espaces de travail</div>}
            {workspaces.map(workspace => {
                const selected = `${workspace.uuid}:${workspace.role}` === currentKey;
                const containsCities = canSwitchCity && selected && workspace.role === 'admin_ville';
                return (
                    <div key={`${workspace.uuid}:${workspace.role}`} className={`main-layout__workspace-group${containsCities ? ' main-layout__workspace-group--nested' : ''}`}>
                    <button
                        type="button"
                        role="menuitemradio"
                        aria-checked={selected}
                        className={`main-layout__workspace-option${selected ? ' is-active' : ''}`}
                        onClick={() => void chooseWorkspace(workspace)}
                    >
                        <span className="main-layout__workspace-icon" aria-hidden="true">{workspace.role === 'admin_ville' ? '⌂' : workspace.role === 'admin_immobilier' ? '▦' : '◇'}</span>
                        <span><strong>{ROLE_NAMES[workspace.role]}</strong><small>{workspace.name} · {workspace.code}</small></span>
                        {selected && <span aria-hidden="true">✓</span>}
                    </button>
                    {containsCities && <div className="main-layout__workspace-city-list" role="group" aria-label={`Villes de l’espace ${ROLE_NAMES[workspace.role]}`}>
                        {cities.map(city => {
                            const citySelected = city.id === activeCity?.id;
                            return <button key={city.id} type="button" role="menuitemradio" aria-checked={citySelected} className={`main-layout__workspace-option main-layout__workspace-option--city${citySelected ? ' is-active' : ''}`} onClick={() => {
                                selectCity(city.id);
                                onOpenChange(false);
                                if (location.pathname.startsWith('/app/patrimoine')) {
                                    navigate(buildPatrimoinePath('parcelles', { cityId: city.id, parcelId: null, buildingId: null }));
                                }
                            }}>
                                <span className="main-layout__workspace-icon" aria-hidden="true">⌂</span>
                                <span><strong>{city.name}</strong><small>Ville</small></span>
                                {citySelected && <span aria-hidden="true">✓</span>}
                            </button>;
                        })}
                    </div>}
                    </div>
                );
            })}
        </div>
    ) : null;

    return (
        <div className={`main-layout__workspace-switcher${mobile ? ' main-layout__workspace-switcher--mobile' : ''}`}>
            <button
                ref={triggerRef}
                type="button"
                className="main-layout__workspace-trigger"
                aria-label={canSwitchCity ? 'Changer d’espace de travail ou de ville' : 'Changer d’espace de travail'}
                aria-haspopup="menu"
                aria-expanded={open}
                onClick={() => {
                    if (!open) void refreshUser().catch(() => undefined);
                    onOpenChange(!open);
                }}
                disabled={isSwitching}
            >
                <span className="main-layout__workspace-trigger-icon" aria-hidden="true">⋮</span>
                <span className="main-layout__workspace-current">
                    <span className="main-layout__workspace-eyebrow">Espace actif</span>
                    <strong>{organizationRole ? ROLE_NAMES[organizationRole] : 'Espaces de travail'}</strong>
                    {currentOrganization && <small>{currentOrganization.name}</small>}
                    {canSwitchCity && activeCity && <small className="main-layout__workspace-city">Ville active · {activeCity.name}</small>}
                </span>
                <span className="main-layout__workspace-chevron" aria-hidden="true">⌄</span>
            </button>
            {menu && createPortal(menu, document.body)}
        </div>
    );
}
