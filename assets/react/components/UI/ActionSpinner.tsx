import type { SVGProps } from 'react';

/** Indicateur commun aux boutons pendant une action en cours. */
export function ActionSpinner(props: SVGProps<SVGSVGElement>) {
    const { className, ...svgProps } = props;
    return (
        <svg
            className={`action-spinner${className ? ` ${className}` : ''}`}
            viewBox="0 0 100 100"
            aria-hidden="true"
            focusable="false"
            {...svgProps}
        >
            <g stroke="currentColor" strokeWidth="10" strokeLinecap="round">
                <line x1="50" y1="9" x2="50" y2="26" opacity="1" />
                <line x1="50" y1="9" x2="50" y2="26" transform="rotate(30 50 50)" opacity=".92" />
                <line x1="50" y1="9" x2="50" y2="26" transform="rotate(60 50 50)" opacity=".84" />
                <line x1="50" y1="9" x2="50" y2="26" transform="rotate(90 50 50)" opacity=".76" />
                <line x1="50" y1="9" x2="50" y2="26" transform="rotate(120 50 50)" opacity=".68" />
                <line x1="50" y1="9" x2="50" y2="26" transform="rotate(150 50 50)" opacity=".6" />
                <line x1="50" y1="9" x2="50" y2="26" transform="rotate(180 50 50)" opacity=".52" />
                <line x1="50" y1="9" x2="50" y2="26" transform="rotate(210 50 50)" opacity=".44" />
                <line x1="50" y1="9" x2="50" y2="26" transform="rotate(240 50 50)" opacity=".36" />
                <line x1="50" y1="9" x2="50" y2="26" transform="rotate(270 50 50)" opacity=".28" />
                <line x1="50" y1="9" x2="50" y2="26" transform="rotate(300 50 50)" opacity=".2" />
                <line x1="50" y1="9" x2="50" y2="26" transform="rotate(330 50 50)" opacity=".12" />
            </g>
        </svg>
    );
}
