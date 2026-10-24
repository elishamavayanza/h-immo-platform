import { useId } from 'react';
interface BrandLogoProps {
    className?: string;
    decorative?: boolean;
}

/** Symbole officiel partagé par les surfaces de marque de H‑Immo. */
export function BrandLogo({ className, decorative = false }: BrandLogoProps) {
    const gradientId = useId().replace(/:/g, '');

    return (
        <svg
            className={`brand-logo${className ? ` ${className}` : ''}`}
            viewBox="75 55 360 360"
            role={decorative ? undefined : 'img'}
            aria-label={decorative ? undefined : 'Logo H‑Immo'}
            aria-hidden={decorative ? true : undefined}
            focusable="false"
        >
            <defs>
                <linearGradient id={`${gradientId}-roof`} x1="100" y1="150" x2="410" y2="150" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stopColor="var(--himmo-logo-roof-start)" />
                    <stop offset="0.48" stopColor="var(--himmo-logo-roof-mid)" />
                    <stop offset="1" stopColor="var(--himmo-logo-accent)" />
                </linearGradient>
                <linearGradient id={`${gradientId}-structure`} x1="150" y1="180" x2="370" y2="410" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stopColor="var(--himmo-logo-structure-start)" />
                    <stop offset="1" stopColor="var(--himmo-logo-structure-end)" />
                </linearGradient>
                <linearGradient id={`${gradientId}-window`} x1="210" y1="180" x2="300" y2="290" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stopColor="var(--himmo-logo-accent-bright)" />
                    <stop offset="1" stopColor="var(--himmo-logo-accent)" />
                </linearGradient>
                <linearGradient id={`${gradientId}-ribbon`} x1="90" y1="290" x2="420" y2="350" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stopColor="var(--himmo-logo-accent-bright)" />
                    <stop offset="0.55" stopColor="var(--himmo-logo-accent)" />
                    <stop offset="1" stopColor="var(--himmo-logo-accent-dark)" />
                </linearGradient>
            </defs>
            <path d="M256 55 L420 175 L393 201 L256 101 L119 201 L92 175 Z" fill={`url(#${gradientId}-roof)`} />
            <g fill={`url(#${gradientId}-structure)`}>
                <path d="M147 193 L193 161 L193 263 L147 263 Z" />
                <path d="M319 161 L365 193 L365 263 L319 263 Z" />
                <path d="M147 263 H365 V302 H147 Z" />
                <path d="M147 302 H193 V415 L147 402 Z" />
                <path d="M319 302 H365 V402 L319 415 Z" />
            </g>
            <g fill={`url(#${gradientId}-window)`}>
                <path d="M220 173 L252 155 V203 H220 Z" />
                <path d="M260 155 L292 173 V203 H260 Z" />
                <path d="M220 211 H252 V243 H220 Z" />
                <path d="M260 211 H292 V243 H260 Z" />
            </g>
            <path d="M142 233 C111 257 99 285 116 301 C144 329 243 322 321 300 C367 287 401 267 404 248 C416 267 399 288 375 305 C317 346 202 357 132 335 C70 316 68 271 142 233 Z" fill={`url(#${gradientId}-ribbon)`} />
            <path d="M335 101 H369 V143 L335 119 Z" fill="var(--himmo-logo-accent)" />
        </svg>
    );
}
