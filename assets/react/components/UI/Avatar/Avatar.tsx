import React from 'react';
import { useAvatar, UseAvatarProps } from '../../../hook-components/UI/Avatar';

// ============================================================
// Utilitaire local — résolution d’URL d’avatar
// ------------------------------------------------------------
// Prend en charge :
//   - une URL absolue (http://, https://, data:)
//   - un chemin relatif (ex. "avatars/user.png")
//   - un nom de fichier seul (ex. "user.png")
// Retourne `undefined` si `src` est vide.
// ============================================================
function resolveAvatarUrl(src?: string, basePath = '/uploads/avatars'): string | undefined {
    if (!src) return undefined;

    // URL absolue ou data-URI → on renvoie tel quel
    if (/^(https?:|data:|blob:)/i.test(src)) {
        return src;
    }

    // Chemin absolu (commence par /) → on renvoie tel quel
    if (src.startsWith('/')) {
        return src;
    }

    // Sinon, on préfixe avec le dossier par défaut
    return `${basePath}/${src}`;
}

export interface AvatarProps extends UseAvatarProps {
    src?: string;
    alt?: string;
    name?: string;
    icon?: React.ReactNode;
    status?: 'online' | 'offline' | 'busy';
}

export function Avatar({ src, alt, name, icon, status, size, shape, className }: AvatarProps) {
    const { classes } = useAvatar({ size, shape, className });
    const imageSrc = resolveAvatarUrl(src);

    const getInitials = (fullName: string) => {
        return fullName
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    return (
        <div className={classes}>
            {imageSrc ? (
                <img src={imageSrc} alt={alt || name} className="avatar__image" />
            ) : icon ? (
                <span className="avatar__icon">{icon}</span>
            ) : name ? (
                <span className="avatar__initials">{getInitials(name)}</span>
            ) : (
                <span className="avatar__placeholder">?</span>
            )}
            {status && <span className={`avatar__status avatar__status--${status}`} />}
        </div>
    );
}
