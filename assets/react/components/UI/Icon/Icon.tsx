import type { ReactNode, SVGProps } from 'react';

export type IconName = 'edit' | 'document' | 'check' | 'archive' | 'trash' | 'mapPin' | 'money' | 'alert' | 'camera' | 'cloud' | 'globe' | 'power' | 'plus' | 'more' | 'chevronDown';

const paths: Record<IconName, ReactNode> = {
    edit: <><path d="m4 20 4.5-1 10.8-10.8a2.1 2.1 0 0 0-3-3L5.5 16 4 20Z" /><path d="m14.8 6.2 3 3" /></>,
    document: <><path d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" /><path d="M14 3v5h5M9 13h6M9 17h6" /></>,
    check: <path d="m5 12 4 4L19 6" />,
    archive: <><path d="M4 7h16v13H4zM3 4h18v3H3z" /><path d="M9 11h6" /></>,
    trash: <><path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6" /><path d="M10 11v5m4-5v5" /></>,
    mapPin: <><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="2.5" /></>,
    money: <><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M7 9a2 2 0 0 1-2 2m14 2a2 2 0 0 0-2 2m-5-6v4" /><circle cx="12" cy="12" r="2" /></>,
    alert: <><path d="m12 3 10 18H2L12 3Z" /><path d="M12 9v5m0 3h.01" /></>,
    camera: <><path d="M4 7h3l1.5-2h7L17 7h3v12H4z" /><circle cx="12" cy="13" r="3.5" /></>,
    cloud: <path d="M7 18a5 5 0 1 1 .7-9.95A6 6 0 0 1 19 10a4 4 0 0 1-1 8H7Z" />,
    globe: <><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18" /></>,
    power: <><path d="M12 2v10" /><path d="M6.2 5.8a8 8 0 1 0 11.6 0" /></>,
    plus: <path d="M12 5v14M5 12h14" />,
    more: <><circle cx="5" cy="12" r="1" /><circle cx="12" cy="12" r="1" /><circle cx="19" cy="12" r="1" /></>,
    chevronDown: <path d="m6 9 6 6 6-6" />,
};

export interface IconProps extends SVGProps<SVGSVGElement> {
    name: IconName;
    size?: number;
}

export function Icon({ name, size = 16, ...props }: IconProps) {
    return <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false" {...props}>{paths[name]}</svg>;
}
