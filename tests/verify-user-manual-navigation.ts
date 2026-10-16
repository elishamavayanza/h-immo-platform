import { readFileSync } from 'node:fs';
import { resolveSidebar } from '../assets/react/app/layout/MainLayout/sidebar/sidebar.config.ts';
import { filterManualArticles, groupManualArticles, manualActions, manualArticleId, resolveManualFeature } from '../assets/react/features/shared/account/settings/utils/manualNavigation.ts';
import type { UserManualArticle } from '../assets/react/features/shared/account/settings/types/userManual.types.ts';

const catalog = JSON.parse(readFileSync(new URL('../docs/user-manuals/catalog.json', import.meta.url), 'utf8')) as {
    roles: Record<string, { label: string; modules: UserManualArticle[] }>;
};
const roles = [
    ['super_admin', 'super_admin', null],
    ['patron', null, 'patron'],
    ['admin_immobilier', null, 'admin_immobilier'],
    ['admin_ville', null, 'admin_ville'],
] as const;

let checks = 0;
function assert(condition: unknown, message: string): asserts condition {
    checks++;
    if (!condition) throw new Error(`Échec du contrôle ${checks} : ${message}`);
}

for (const [key, platformRole, organizationRole] of roles) {
    const manual = catalog.roles[key];
    const menu = resolveSidebar(platformRole, organizationRole);
    assert(Boolean(manual?.modules.length), `${key} possède un manuel avec des articles`);
    const ids = manual.modules.map(manualArticleId);
    assert(new Set(ids).size === ids.length, `${key} a des URL d’article uniques`);
    assert(manual.modules.every((article) => resolveManualFeature(article, menu) !== null), `${key} relie chaque article à une destination accessible`);
    assert(manual.modules.every((article) => manualActions(article).length === article.steps.length), `${key} expose chaque étape sous forme d’action navigable`);
    assert(manual.modules.every((article) => new Set(manualActions(article).map((action) => action.id)).size === article.steps.length), `${key} utilise des identifiants d’action uniques dans chaque écran`);
    assert(Object.keys(groupManualArticles(manual.modules)).length !== 0, `${key} regroupe ses articles par rubrique`);
    assert(filterManualArticles(manual.modules, 'terme absent du guide').length === 0, `${key} gère une recherche sans résultat`);
    const article = manual.modules[0];
    assert(filterManualArticles(manual.modules, article.steps[0]).some((item) => item.name === article.name), `${key} recherche aussi dans les étapes`);
}

const patronTitles = catalog.roles.patron.modules.map((article) => article.name).sort().join('|');
const cityTitles = catalog.roles.admin_ville.modules.map((article) => article.name).sort().join('|');
assert(patronTitles !== cityTitles, 'les manuels PATRON et ADMIN_VILLE ne sont pas un document générique partagé');

console.log(`OK — ${checks} contrôles des manuels par rôle, URLs, rubriques, recherche et menus réels.`);
