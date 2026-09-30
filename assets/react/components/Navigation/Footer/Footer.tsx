import React from 'react';
import { useFooter, UseFooterProps } from '../../../hook-components/Navigation/Footer';
import { useI18n } from '@/react/i18n/I18nContext';

export interface FooterProps extends UseFooterProps {}

export function Footer({ brand, sections = [], bottomContent, logo, variant, className }: FooterProps) {
    const { t } = useI18n();
    const { classes } = useFooter({ brand, sections, bottomContent, logo, variant, className });

    return (
        <footer className={classes}>
            <div className="footer__inner">
                {brand && <div className="footer__brand">{typeof brand === 'string' ? t(brand) : brand}</div>}
                {sections.map((section) => (
                    <div key={section.id} className="footer__section">
                        <h4 className="footer__section-title">{typeof section.title === 'string' ? t(section.title) : section.title}</h4>
                        <ul className="footer__links">
                            {section.links.map((link) => (
                                <li key={link.id}>
                                    <a href={link.href} className="footer__link">
                                        {link.icon && <span className="footer__link-icon">{link.icon}</span>}
                                        {typeof link.label === 'string' ? t(link.label) : link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
                {logo && <div className="footer__logo">{logo}</div>}
            </div>
            {bottomContent && <div className="footer__bottom">{typeof bottomContent === 'string' ? t(bottomContent) : bottomContent}</div>}
        </footer>
    );
}
