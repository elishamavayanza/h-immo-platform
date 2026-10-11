import { useState } from 'react';

import { Modal } from '../../../../components/UI/Modal';
import { Button } from '../../../../components/UI/Button';
import { IconButton } from '../../../../components/UI/IconButton';
import { Icon } from '../../../../components/UI/Icon/Icon';
import { SearchableSelect } from '../../../../components/Forms/SearchableSelect/SearchableSelect';
import { FormField } from '../../../../components/Forms/FormField';
import { Loading } from '../../../../components/UI/Loading';
import { ErrorState } from '../../../../components/UI/ErrorState';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { useAdminVilleCities } from '../hooks/useAdminVilleCities';
import type { TeamMember } from '../types/administration.types';

interface CityAssignmentModalProps {
    member: TeamMember | null;
    onClose: () => void;
}

/**
 * Périmètre géographique d'un ADMIN_VILLE (espace PATRON).
 *
 * Le périmètre ne peut pas être vide : le retrait de la dernière ville est
 * désactivé côté UI (le backend refuse la création d'un ADMIN_VILLE sans
 * ville, et un périmètre vide le rendrait inopérant). Chaque action appelle
 * l'API et recharge la liste réelle.
 */
export function CityAssignmentModal({ member, onClose }: CityAssignmentModalProps) {
    const { assigned, available, isLoading, error, reload, assign, revoke } = useAdminVilleCities(member?.id ?? null);
    const [selected, setSelected] = useState('');
    const [pending, setPending] = useState(false);

    const open = member !== null;
    const lastCity = assigned.length <= 1;

    const submit = async () => {
        if (!selected || pending) return;
        setPending(true);
        try {
            await assign(selected);
            setSelected('');
        } catch {
            // Le toast d'échec est déjà émis par le hook.
        } finally {
            setPending(false);
        }
    };

    const remove = async (linkUuid: string) => {
        if (pending) return;
        setPending(true);
        try {
            await revoke(linkUuid);
        } catch {
            // Le toast d'échec est déjà émis par le hook.
        } finally {
            setPending(false);
        }
    };

    return (
        <Modal
            isOpen={open}
            onClose={onClose}
            title={member ? `Villes de « ${member.name} »` : 'Villes du membre'}
            size="medium"
            footer={<Button variant="outline" onClick={onClose}>Fermer</Button>}
        >
            <div className="organization-management-form">
                <p className="organization-management-form__hint">
                    Un administrateur ville ne voit que les villes qui lui sont attribuées. Il en faut au moins une.
                </p>

                <FormField label="Attribuer une ville">
                    <div className="patron-city-assignment__add">
                        <SearchableSelect
                            options={available.map((city) => ({ value: city.uuid, label: city.name }))}
                            value={selected}
                            onChange={setSelected}
                            placeholder="Rechercher une ville…"
                            disabled={pending || available.length === 0}
                        />
                        <Button onClick={submit} isLoading={pending} disabled={!selected || pending}>Ajouter</Button>
                    </div>
                </FormField>

                {isLoading ? (
                    <Loading text="Chargement des villes…" />
                ) : error ? (
                    <ErrorState title="Impossible de charger les villes" message={error} onRetry={() => void reload()} />
                ) : assigned.length === 0 ? (
                    <EmptyState title="Aucune ville attribuée" description="Cet administrateur ville n’a encore aucune ville." />
                ) : (
                    <ul className="patron-city-assignment__list">
                        {assigned.map(({ link, city }) => (
                            <li key={link.id}>
                                <span><Icon name="mapPin" /> {city?.name ?? 'Ville inconnue'}</span>
                                <IconButton
                                    ariaLabel="Retirer la ville"
                                    icon={<Icon name="trash" />}
                                    variant="ghost"
                                    disabled={pending || lastCity}
                                    title={lastCity ? 'Au moins une ville est requise' : 'Retirer cette ville'}
                                    onClick={() => void remove(link.id)}
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Modal>
    );
}
