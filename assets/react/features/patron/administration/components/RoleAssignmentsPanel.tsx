import { useEffect, useMemo, useState } from 'react';

import { useAuth } from '../../../../app/providers/AuthProvider';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Loading } from '../../../../components/UI/Loading';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { ConfirmDialog } from '../../../../components/UI/ConfirmDialog';
import { CityAssignmentModal } from './CityAssignmentModal';
import { fetchCities } from '../../../admin_immobilier/shared/services/referenceService';
import { administrationService } from '../services/administrationService';
import '../../../../../styles/pages/patron/administration/_administration.scss';

type Role = 'patron' | 'admin_immobilier' | 'admin_ville';
type Assignment = { assignmentUuid: string; userUuid: string; name: string; email: string; active: boolean; role: Role; cities: Array<{ uuid: string; name: string }> };
type Candidate = { uuid: string; name: string; email: string; roles: string[] };
const ROLE_LABEL: Record<Role, string> = { patron: 'Patron', admin_immobilier: 'Admin immobilier', admin_ville: 'Admin ville' };

export function RoleAssignmentsPanel() {
    const { currentOrganization, organizationRole } = useOrganization();
    const { user } = useAuth();
    const { push } = useToast();
    const [items, setItems] = useState<Assignment[]>([]);
    const [candidates, setCandidates] = useState<Candidate[]>([]);
    const [cityOptions, setCityOptions] = useState<Array<{ uuid: string; name: string }>>([]);
    const [query, setQuery] = useState('');
    const [candidateUuid, setCandidateUuid] = useState('');
    const [role, setRole] = useState<'admin_immobilier' | 'admin_ville'>(organizationRole === 'admin_immobilier' ? 'admin_ville' : 'admin_immobilier');
    const [cityUuids, setCityUuids] = useState<string[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [pendingRevoke, setPendingRevoke] = useState<Assignment | null>(null);
    const [revoking, setRevoking] = useState(false);
    const [cityTarget, setCityTarget] = useState<Assignment | null>(null);
    const organizationUuid = currentOrganization?.uuid;
    const isPatron = organizationRole === 'patron';

    const reload = async () => {
        if (!organizationUuid) return;
        setLoading(true);
        setError(null);
        try {
            const [data, cities] = await Promise.all([
                administrationService.listRoleAssignments(organizationUuid),
                fetchCities(organizationUuid),
            ]);
            setItems(data.items as Assignment[]);
            setCandidates(data.candidates);
            setCityOptions(cities.items.map(city => ({ uuid: city.id, name: city.name })));
        } catch (cause) {
            const message = cause instanceof Error ? cause.message : 'Impossible de charger les affectations.';
            setError(message);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { void reload(); }, [organizationUuid, organizationRole]);

    const eligibleCandidates = useMemo(() => candidates.filter(candidate => {
        if (candidate.uuid === user?.uuid || candidate.roles.includes(role)) return false;
        const needle = query.trim().toLocaleLowerCase('fr');
        return needle === '' || `${candidate.name} ${candidate.email}`.toLocaleLowerCase('fr').includes(needle);
    }), [candidates, query, role, user?.uuid]);

    const submit = async () => {
        if (!organizationUuid || !candidateUuid || saving) return;
        if (role === 'admin_ville' && cityUuids.length === 0) {
            push('error', 'Choisissez au moins une ville pour l’accès Administrateur de ville.');
            return;
        }
        const candidate = eligibleCandidates.find(item => item.uuid === candidateUuid);
        const roleLabel = ROLE_LABEL[role];
        setSaving(true);
        try {
            const message = await administrationService.assignExistingRole({ organizationUuid, userUuid: candidateUuid, role, cityUuids: role === 'admin_ville' ? cityUuids : [] });
            push('success', message ?? `L’accès ${roleLabel} a été attribué à ${candidate?.name ?? 'ce membre'}.`);
            setCandidateUuid('');
            setCityUuids([]);
            await reload();
        } catch (cause) {
            push('error', cause instanceof Error ? `Impossible d’attribuer l’accès ${roleLabel} : ${cause.message}` : `Impossible d’attribuer l’accès ${roleLabel}.`);
        } finally {
            setSaving(false);
        }
    };

    const revoke = async () => {
        if (!pendingRevoke || revoking) return;
        const assignment = pendingRevoke;
        setRevoking(true);
        try {
            const message = await administrationService.revokeRole(assignment.assignmentUuid);
            push('success', message ?? `L’accès ${ROLE_LABEL[assignment.role]} de ${assignment.name} a été retiré.`);
            setPendingRevoke(null);
            await reload();
        } catch (cause) {
            push('error', cause instanceof Error ? `Impossible de retirer l’accès ${ROLE_LABEL[assignment.role]} de ${assignment.name} : ${cause.message}` : `Impossible de retirer l’accès ${ROLE_LABEL[assignment.role]} de ${assignment.name}.`);
        } finally {
            setRevoking(false);
        }
    };

    if (loading && items.length === 0) return <Loading text="Chargement des rôles et des accès…" />;

    return (
        <section className="role-assignments">
            <header className="role-assignments__header">
                <div><h2>Rôles et espaces</h2><p>{isPatron ? 'Ajoutez un espace de travail à un membre existant.' : 'Gérez les accès des administrateurs de ville de votre organisation.'}</p></div>
            </header>
            <Card padding="medium" className="role-assignments__form">
                <div className="role-assignments__toolbar">
                    <SearchInput value={query} onSearch={setQuery} placeholder="Rechercher un membre…" fullWidth />
                    <Select aria-label="Rôle à attribuer" value={role} onChange={event => { setRole(event.target.value as typeof role); setCityUuids([]); }} options={isPatron ? [
                        { value: 'admin_immobilier', label: 'Admin immobilier' }, { value: 'admin_ville', label: 'Admin ville' },
                    ] : [{ value: 'admin_ville', label: 'Admin ville' }]} />
                    <Select aria-label="Membre à affecter" value={candidateUuid} onChange={event => setCandidateUuid(event.target.value)} placeholder="Sélectionner un membre" options={eligibleCandidates.map(candidate => ({ value: candidate.uuid, label: `${candidate.name} · ${candidate.email}` }))} />
                </div>
                {role === 'admin_ville' && (
                    <fieldset className="role-assignments__cities"><legend>Villes accessibles</legend>
                        <p>Sélectionnez une ou plusieurs villes. L’administrateur pourra basculer entre elles dans son espace.</p>
                        {cityOptions.length === 0 ? <p>Aucune ville disponible dans cette organisation.</p> : cityOptions.map(city => (
                            <label key={city.uuid}><input type="checkbox" checked={cityUuids.includes(city.uuid)} onChange={() => setCityUuids(current => current.includes(city.uuid) ? current.filter(uuid => uuid !== city.uuid) : [...current, city.uuid])} /> {city.name}</label>
                        ))}
                        {cityUuids.length > 0 && <small>{cityUuids.length} ville{cityUuids.length > 1 ? 's' : ''} sélectionnée{cityUuids.length > 1 ? 's' : ''}</small>}
                    </fieldset>
                )}
                <div className="role-assignments__actions"><Button disabled={!candidateUuid || (role === 'admin_ville' && cityUuids.length === 0)} isLoading={saving} onClick={() => void submit()}>Attribuer cet espace</Button></div>
                {error && <p className="role-assignments__error" role="alert">{error} <button type="button" onClick={() => void reload()}>Réessayer</button></p>}
            </Card>

            <div className="role-assignments__list" aria-live="polite">
                {items.length === 0 ? <p className="role-assignments__empty">Aucune affectation de rôle à afficher.</p> : items.map(item => (
                    <article className="role-assignments__row" key={item.assignmentUuid}>
                        <div className="role-assignments__identity"><strong>{item.name}</strong><span>{item.email}</span></div>
                        <span className="role-assignments__role">{ROLE_LABEL[item.role]}</span>
                        {item.role === 'admin_ville' && <span className="role-assignments__cities-summary">{item.cities.length} ville{item.cities.length === 1 ? '' : 's'} attribuée{item.cities.length === 1 ? '' : 's'} · {item.cities.map(city => city.name).join(', ') || 'aucune'}</span>}
                        <div className="role-assignments__actions-cell">
                            {item.role === 'admin_ville' && <Button variant="outline" size="small" onClick={() => setCityTarget(item)}>Gérer les villes</Button>}
                            <Button variant="outline" size="small" onClick={() => setPendingRevoke(item)}>Retirer le rôle</Button>
                        </div>
                    </article>
                ))}
            </div>
            <ConfirmDialog
                isOpen={pendingRevoke !== null}
                onClose={() => { if (!revoking) setPendingRevoke(null); }}
                onCancel={() => setPendingRevoke(null)}
                onConfirm={() => void revoke()}
                title="Retirer cet espace ?"
                message={pendingRevoke ? `Retirer le rôle « ${ROLE_LABEL[pendingRevoke.role]} » de ${pendingRevoke.name} ?${pendingRevoke.role === 'admin_ville' ? ` Cela retirera son accès aux ${pendingRevoke.cities.length} ville${pendingRevoke.cities.length === 1 ? '' : 's'} attribuée${pendingRevoke.cities.length === 1 ? '' : 's'} (${pendingRevoke.cities.map(city => city.name).join(', ') || 'aucune'}).` : ''} Son compte conservera ses autres espaces.` : ''}
                confirmLabel={revoking ? 'Retrait…' : 'Retirer l’accès'}
                cancelLabel="Annuler"
            />
            <CityAssignmentModal
                member={cityTarget ? { id: cityTarget.userUuid, name: cityTarget.name } : null}
                onClose={() => setCityTarget(null)}
            />
        </section>
    );
}
