import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Button } from '../../../../../components/UI/Button';
import { ApiError } from '../../../../../../services/api/api.types';
import { resolveSidebar } from '../../../../../app/layout/MainLayout/sidebar/sidebar.config';
import { SIDEBAR_ICON_MAP } from '../../../../../app/layout/MainLayout/sidebar/sidebar.icons';
import { useAuth } from '../../../../../app/providers/AuthProvider';
import { useOrganization } from '../../../../../app/providers/OrganizationProvider';
import { userManualService } from '../services/userManualService';
import type { UserManual, UserManualArticle } from '../types/userManual.types';
import { filterManualArticles, groupManualArticles, manualActions, manualArticleId, resolveManualFeature } from '../utils/manualNavigation';

type GuideGlyphKind = 'open' | 'search' | 'create' | 'save' | 'success' | 'error' | 'note';

function GuideGlyph({ kind }: { kind: GuideGlyphKind }) {
    const paths: Record<GuideGlyphKind, ReactNode> = {
        open: <><path d="M4 12h15"/><path d="m13 5 7 7-7 7"/></>,
        search: <><circle cx="10.8" cy="10.8" r="6.5"/><path d="m16 16 4 4"/></>,
        create: <><path d="M12 5v14M5 12h14"/></>,
        save: <><path d="m5 12 4 4L19 6"/></>,
        success: <><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16.5 9"/></>,
        error: <><path d="M12 3 22 20H2L12 3Z"/><path d="M12 9v5M12 17h.01"/></>,
        note: <><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></>,
    };
    return <svg className="manual-guide-glyph" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">{paths[kind]}</svg>;
}

function actionGlyphKind(instruction: string): GuideGlyphKind {
    const text = instruction.toLocaleLowerCase('fr');
    if (/recherch|filtre|sélection|choisissez|choisir/.test(text)) return 'search';
    if (/crée|ajoutez|ajouter|nouveau|nouvelle/.test(text)) return 'create';
    if (/enregistr|confirmez|validez|publiez|activez|terminez/.test(text)) return 'save';
    return 'open';
}

function errorGuidance(article: UserManualArticle): string {
    const context = article.menu.toLocaleLowerCase('fr').includes('ville') || article.menu.toLocaleLowerCase('fr').includes('opérations')
        ? 'Vérifiez d’abord la ville active et son attribution à votre compte.'
        : 'Vérifiez d’abord l’espace actif et le rôle affiché dans votre compte.';
    return `${context} Relisez ensuite les champs obligatoires et le message affiché. Corrigez uniquement les éléments signalés puis réessayez. Si l’erreur persiste, notez le nom de l’écran, l’action et le message exact, sans communiquer de mot de passe ni de jeton.`;
}

export function UserManualSection() {
    const { currentOrganization, organizationRole, platformRole, isLoading: contextLoading } = useOrganization();
    const { user } = useAuth();
    const [searchParams, setSearchParams] = useSearchParams();
    const [manual, setManual] = useState<UserManual | null>(null);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [query, setQuery] = useState('');
    const [navigationOpen, setNavigationOpen] = useState(false);
    const [expandedArticleId, setExpandedArticleId] = useState<string | null>(null);
    const contextKey = `${user?.uuid ?? 'unknown'}:${platformRole ?? ''}:${currentOrganization?.uuid ?? ''}:${organizationRole ?? ''}`;

    useEffect(() => {
        if (contextLoading) return;
        let active = true;
        setLoading(true);
        setError(null);
        setManual(null);
        void userManualService.getCurrent()
            .then((value) => { if (active) setManual(value); })
            .catch((cause: unknown) => {
                if (active) setError(cause instanceof ApiError ? cause.message : 'Impossible de charger le manuel de cet espace.');
            })
            .finally(() => { if (active) setLoading(false); });
        return () => { active = false; };
    }, [contextKey, contextLoading]);

    const menu = useMemo(() => resolveSidebar(platformRole, organizationRole, currentOrganization?.showcaseEnabled === true), [platformRole, organizationRole, currentOrganization?.showcaseEnabled]);
    const articles = manual?.modules ?? [];
    const currentId = searchParams.get('article');
    const selectedArticle = articles.find((article) => manualArticleId(article) === currentId) ?? articles[0] ?? null;
    const actions = selectedArticle ? manualActions(selectedArticle) : [];
    const selectedAction = actions.find((action) => action.id === searchParams.get('action')) ?? null;
    const selectedFeature = selectedArticle ? resolveManualFeature(selectedArticle, menu) : null;
    const groups = groupManualArticles(filterManualArticles(articles, query));

    useEffect(() => {
        setExpandedArticleId(selectedArticle ? manualArticleId(selectedArticle) : null);
    }, [selectedArticle ? manualArticleId(selectedArticle) : null]);

    useEffect(() => {
        if (!manual || articles.length === 0) return;
        if (currentId && articles.some((article) => manualArticleId(article) === currentId)) return;
        const params = new URLSearchParams(searchParams);
        params.set('article', manualArticleId(articles[0]));
        setSearchParams(params, { replace: true });
    }, [manual, articles, currentId, searchParams, setSearchParams]);

    const download = async () => {
        setBusy(true);
        setError(null);
        try {
            const blob = await userManualService.download();
            const url = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }));
            const link = document.createElement('a');
            link.href = url;
            link.download = manual?.filename ?? 'manuel-soft-immo.pdf';
            link.click();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de télécharger le manuel.');
        } finally { setBusy(false); }
    };

    const selectArticle = (article: UserManualArticle) => {
        const params = new URLSearchParams(searchParams);
        params.set('article', manualArticleId(article));
        params.delete('action');
        setSearchParams(params);
        setExpandedArticleId(manualArticleId(article));
        setNavigationOpen(false);
    };

    const selectAction = (article: UserManualArticle, actionId: string) => {
        const params = new URLSearchParams(searchParams);
        params.set('article', manualArticleId(article));
        params.set('action', actionId);
        setSearchParams(params);
        setExpandedArticleId(manualArticleId(article));
        setNavigationOpen(false);
    };

    return (
        <section className="settings-section manual-workspace" aria-label="Manuel d’utilisation">
            {loading ? <p className="manual-state" role="status">Chargement du manuel de l’espace actif…</p> : manual && <>
                <header className="manual-workspace__header">
                    <div>
                        <p className="manual-workspace__eyebrow">GUIDE · {manual.label}</p>
                        <h2>{manual.title}</h2>
                        <p>{manual.description}</p>
                        <div className="manual-workspace__meta"><span>Version {manual.version}</span><span>Mis à jour le {manual.updatedAt}</span>{currentOrganization && <span>{currentOrganization.name}</span>}</div>
                    </div>
                    <div className="manual-workspace__downloads">
                        <Button variant="outline" as="a" href="/documents/presentation-generale-soft-immo.pdf" download="presentation-generale-soft-immo.pdf" aria-label="Télécharger la présentation générale de Soft-IMMO">Télécharger la présentation générale</Button>
                        <Button variant="secondary" disabled={busy} onClick={() => void download()}>{busy ? 'Préparation…' : 'Télécharger le PDF'}</Button>
                    </div>
                </header>
                <div className="manual-workspace__toolbar">
                    <label className="manual-workspace__search"><span aria-hidden="true">⌕</span><span className="sr-only">Rechercher dans le manuel</span><input type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Rechercher un écran, une action, un champ…" /></label>
                    <button className="manual-workspace__nav-toggle" type="button" aria-expanded={navigationOpen} aria-controls="manual-navigation" onClick={() => setNavigationOpen((open) => !open)}>{navigationOpen ? 'Fermer le sommaire' : 'Parcourir les rubriques'}</button>
                </div>
                <div className={`manual-workspace__layout${navigationOpen ? ' is-navigation-open' : ''}`}>
                    <aside id="manual-navigation" className={`manual-navigation${navigationOpen ? ' is-open' : ''}`} aria-label="Rubriques du manuel" tabIndex={0}>
                        {Object.keys(groups).length === 0 ? <p className="manual-navigation__empty">Aucune rubrique ne correspond à cette recherche.</p> : Object.entries(groups).map(([group, items]) => <section className="manual-navigation__group" key={group}>
                            <h3>{group}</h3>
                            <nav>
                                {items.map((article) => {
                                    const selected = selectedArticle !== null && manualArticleId(article) === manualArticleId(selectedArticle) && currentId === manualArticleId(article);
                                    const feature = resolveManualFeature(article, menu);
                                    const Icon = feature ? SIDEBAR_ICON_MAP[feature.icon] : undefined;
                                    const actionsForArticle = manualActions(article);
                                    const expanded = expandedArticleId === manualArticleId(article);
                                    const actionTreeId = `manual-actions-${manualArticleId(article)}`;
                                    return <div className="manual-navigation__item" key={manualArticleId(article)}>
                                        <div className="manual-navigation__item-header">
                                            <Link to={`?article=${encodeURIComponent(manualArticleId(article))}`} aria-current={selected && !selectedAction ? 'location' : undefined} className={selected && !selectedAction ? 'is-active' : undefined} onClick={() => selectArticle(article)}>
                                                {Icon && <span className="manual-navigation__icon" aria-hidden="true"><Icon /></span>}
                                                <span>{article.name}</span>
                                            </Link>
                                            <button type="button" className="manual-navigation__expand" aria-label={`${expanded ? 'Réduire' : 'Développer'} les actions de ${article.name}`} aria-expanded={expanded} aria-controls={actionTreeId} onClick={() => setExpandedArticleId(expanded ? null : manualArticleId(article))}>{expanded ? '⌄' : '›'}</button>
                                        </div>
                                        {expanded && <div className="manual-navigation__actions" id={actionTreeId} role="group" aria-label={`Actions : ${article.name}`}>
                                            {actionsForArticle.map((action) => {
                                                const active = selected && searchParams.get('action') === action.id;
                                                return <Link key={action.id} to={`?article=${encodeURIComponent(manualArticleId(article))}&action=${encodeURIComponent(action.id)}`} aria-current={active ? 'location' : undefined} className={active ? 'is-active' : undefined} onClick={() => selectAction(article, action.id)}>
                                                    <GuideGlyph kind={actionGlyphKind(action.instruction)} />
                                                    <span><small>Étape {action.index}</small>{action.title}</span>
                                                </Link>;
                                            })}
                                        </div>}
                                    </div>;
                                })}
                            </nav>
                        </section>)}
                    </aside>
                    {selectedArticle ? <article className="manual-article" aria-label={`Article : ${selectedArticle.name}`} tabIndex={0} key={`${manual.role}:${manualArticleId(selectedArticle)}`}>
                        <section className="manual-article__location" aria-label="Emplacement de l’écran">
                            <div><h3>Où trouver cet écran</h3><p>{selectedArticle.menu}</p></div>
                        {selectedFeature && <Link to={selectedFeature.path} target="_blank" rel="noreferrer">Ouvrir cet écran ↗</Link>}
                        </section>
                        <h2>{selectedArticle.name}</h2>
                        <section><h3>Objectif</h3><p>{selectedArticle.goal}</p></section>
                        {selectedAction ? <section className="manual-article__action-focus">
                            <div className="manual-article__action-title"><GuideGlyph kind={actionGlyphKind(selectedAction.instruction)} /><div><p>Étape {selectedAction.index} sur {actions.length} · Comment procéder</p><h3>{selectedAction.title}</h3></div></div>
                            <p>{selectedAction.instruction}</p>
                        </section> : <section><h3>Comment procéder</h3><ol>{selectedArticle.steps.map((step, index) => <li key={`${index}-${step}`}>{step}</li>)}</ol></section>}
                        {selectedArticle.fields && <section className="manual-article__callout manual-article__callout--note"><h3><GuideGlyph kind="note" /> À préparer / à vérifier</h3><p>{selectedArticle.fields}</p></section>}
                        {selectedArticle.result && <section className="manual-article__callout manual-article__callout--success"><h3><GuideGlyph kind="success" /> Comment reconnaître la réussite</h3><p>{selectedArticle.result}</p></section>}
                        <section className="manual-article__callout manual-article__callout--error"><h3><GuideGlyph kind="error" /> En cas de blocage ou d’erreur</h3><p>{errorGuidance(selectedArticle)}</p>{selectedArticle.limits && <p className="manual-article__limit-note">Règle d’accès : {selectedArticle.limits}</p>}</section>
                        <footer className="manual-article__footer">
                            {articles.indexOf(selectedArticle) > 0 && <button type="button" onClick={() => selectArticle(articles[articles.indexOf(selectedArticle) - 1])}>← Article précédent</button>}
                            {articles.indexOf(selectedArticle) < articles.length - 1 && <button type="button" onClick={() => selectArticle(articles[articles.indexOf(selectedArticle) + 1])}>Article suivant →</button>}
                        </footer>
                    </article> : <p className="manual-state">Aucun article n’est disponible dans ce manuel.</p>}
                </div>
            </>}
            {error && <p className="settings-error" role="alert">{error}</p>}
        </section>
    );
}
