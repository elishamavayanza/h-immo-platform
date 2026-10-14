import { useCallback, useEffect, useMemo, useRef, useState, type Dispatch, type SetStateAction } from 'react';

import { useAuth } from '../app/providers/AuthProvider';
import { useOrganization } from '../app/providers/OrganizationProvider';

const STORAGE_PREFIX = 'soft-immo:form-draft:v1:';

function readDraft<T>(key: string, fallback: T): { value: T; found: boolean } {
    try {
        const raw = window.sessionStorage.getItem(key);
        if (raw === null) return { value: fallback, found: false };
        return { value: JSON.parse(raw) as T, found: true };
    } catch {
        return { value: fallback, found: false };
    }
}

/**
 * Brouillon de formulaire temporaire, séparé par compte et organisation.
 * À utiliser pour les données métier non secrètes (jamais pour mot de passe,
 * jeton, secret ou justificatif) afin de reprendre un formulaire interrompu.
 */
export function useSessionFormDraft<T>(scope: string, initialValue: T): [T, Dispatch<SetStateAction<T>>, boolean, () => void] {
    const { user } = useAuth();
    const { currentOrganization } = useOrganization();
    const storageKey = useMemo(() => user
        ? `${STORAGE_PREFIX}${user.uuid}:${currentOrganization?.uuid ?? 'platform'}:${scope}`
        : null, [user?.uuid, currentOrganization?.uuid, scope]);
    const activeKey = useRef(storageKey);
    const [value, setValue] = useState<T>(() => storageKey ? readDraft(storageKey, initialValue).value : initialValue);
    const [hasDraft, setHasDraft] = useState(() => storageKey ? readDraft(storageKey, initialValue).found : false);
    const clearDraft = useCallback(() => {
        if (storageKey) {
            try { window.sessionStorage.removeItem(storageKey); } catch { /* stockage désactivé */ }
        }
        setHasDraft(false);
    }, [storageKey]);

    useEffect(() => {
        if (activeKey.current !== storageKey) {
            activeKey.current = storageKey;
            const restored = storageKey ? readDraft(storageKey, initialValue) : { value: initialValue, found: false };
            setValue(restored.value);
            setHasDraft(restored.found);
            return;
        }
        if (!storageKey) return;

        try {
            if (JSON.stringify(value) === JSON.stringify(initialValue)) {
                window.sessionStorage.removeItem(storageKey);
                setHasDraft(false);
            } else {
                window.sessionStorage.setItem(storageKey, JSON.stringify(value));
                setHasDraft(true);
            }
        } catch {
            // Le formulaire reste utilisable lorsque le stockage est indisponible.
        }
    }, [storageKey, value, initialValue]);

    return [value, setValue, hasDraft, clearDraft];
}
