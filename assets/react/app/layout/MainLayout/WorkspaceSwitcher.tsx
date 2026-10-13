import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useNavigate } from 'react-router-dom';

import { useOrganization } from '../../providers/OrganizationProvider';
import { useAuth } from '../../providers/AuthProvider';
import { defaultPathFor } from './sidebar/sidebar.config';
import type { OrganizationRole, SessionOrganizationMembership } from '../../../../services/api/api.types';

const ROLE_NAMES: Record<OrganizationRole, string> = {
    patron: 'Espace Patron',
    admin_immobilier: 'Administration immobilière',
    admin_ville: 'Administration de ville',
};

export function WorkspaceSwitcher({ mobile = false }: { mobile?: boolean }) {
    const { workspaces, currentOrganization, organizationRole, platformRole, switchWorkspace, isSwitching } = useOrganization();
    const { refreshUser } = useAuth();
    const navigate = useNavigate();
    const triggerRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const [position, setPosition] = useState({ left: 0, top: 0, width: 280, placement: 'above' as 'above' | 'below' });

    const currentKey = currentOrganization ? `${currentOrganization.uuid}:${currentOrganization.role}` : null;
    const updatePosition = () => {
        if (mobile) return;
        const rect = triggerRef.current?.getBoundingClientRect();
        if (!rect) return;
        const width = Math.min(280, window.innerWidth - 16);
        setPosition({
            left: Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)),
            top: Math.max(8, Math.min(rect.top - 8, window.innerHeight - 8)),
            width,
            placement: 'above',
        });
    };

    useEffect(() => {
        if (!open) return;
        updatePosition();
        const onPointer = (event: PointerEvent) => {
            if (!menuRef.current?.contains(event.target as Node) && !triggerRef.current?.contains(event.target as Node)) setOpen(false);
        };
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') { setOpen(false); triggerRef.current?.focus(); }
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
    }, [open]);

    if (platformRole || workspaces.length < 2) return null;

    const chooseWorkspace = async (workspace: SessionOrganizationMembership) => {
        if (`${workspace.uuid}:${workspace.role}` === currentKey || isSwitching) { setOpen(false); return; }
        try {
            await switchWorkspace(workspace);
            setOpen(false);
            navigate(defaultPathFor(null, workspace.role), { replace: true });
        } catch {
            setOpen(false);
        }
    };

    const menu = open ? (
        <div
            ref={menuRef}
            className={`main-layout__workspace-menu${mobile ? ' main-layout__workspace-menu--mobile' : ''}`}
            role="menu"
            aria-label="Espaces de travail disponibles"
            style={mobile ? undefined : { left: position.left, top: position.top, width: position.width, transform: 'translateY(-100%)' }}
        >
            <div className="main-layout__workspace-heading">Changer d’espace</div>
            {workspaces.map(workspace => {
                const selected = `${workspace.uuid}:${workspace.role}` === currentKey;
                return (
                    <button
                        key={`${workspace.uuid}:${workspace.role}`}
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
                aria-label="Changer d’espace de travail"
                aria-haspopup="menu"
                aria-expanded={open}
                onClick={() => {
                    if (!open) void refreshUser().catch(() => undefined);
                    setOpen(value => !value);
                }}
                disabled={isSwitching}
            >
                <span className="main-layout__workspace-trigger-icon" aria-hidden="true">⋮</span>
                <span className="main-layout__workspace-current">
                    <span className="main-layout__workspace-eyebrow">Espace actif</span>
                    <strong>{organizationRole ? ROLE_NAMES[organizationRole] : 'Espaces de travail'}</strong>
                    {currentOrganization && <small>{currentOrganization.name}</small>}
                </span>
                <span className="main-layout__workspace-chevron" aria-hidden="true">⌄</span>
            </button>
            {mobile ? menu : menu && createPortal(menu, document.body)}
        </div>
    );
}
