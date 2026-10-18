import type { SidebarMenu } from '../../../../../app/layout/MainLayout/sidebar/sidebar.types';
import type { UserManualAction, UserManualArticle } from '../types/userManual.types';

export function manualArticleId(article: UserManualArticle): string {
    return article.id;
}

/** Chaque étape publiée devient une entrée de l’arbre du manuel. */
export function manualActions(article: UserManualArticle): UserManualAction[] {
    return article.steps.map((instruction, index) => ({
        id: `etape-${index + 1}`,
        index: index + 1,
        title: instruction.replace(/[.!?]+$/, ''),
        instruction,
    }));
}

export function filterManualArticles(articles: UserManualArticle[], query: string): UserManualArticle[] {
    const normalized = query.trim().toLocaleLowerCase('fr');
    if (!normalized) return articles;
    return articles.filter((article) => [article.name, article.menu, article.goal, article.fields, article.result, article.limits, ...(article.steps ?? [])]
        .join(' ').toLocaleLowerCase('fr').includes(normalized));
}

export function groupManualArticles(articles: UserManualArticle[]): Record<string, UserManualArticle[]> {
    return articles.reduce<Record<string, UserManualArticle[]>>((result, article) => {
        const group = article.menu.split('→')[0]?.trim() || 'Guide';
        (result[group] ??= []).push(article);
        return result;
    }, {});
}

/** Relie un article à une destination et une icône déjà déclarées dans le menu réel. */
export function resolveManualFeature(article: UserManualArticle, menu: SidebarMenu): { path: string; icon: string } | null {
    const label = article.menu.split('→').at(-1)?.trim().toLocaleLowerCase('fr') ?? article.name.toLocaleLowerCase('fr');
    const menuPath = article.menu.toLocaleLowerCase('fr');
    const target = label.includes('rapport') ? 'rapports' : label.includes('profil') || label.includes('param') ? '' : label;
    const item = menu.find((candidate) => candidate.label.toLocaleLowerCase('fr') === target)
        ?? menu.find((candidate) => target !== '' && (candidate.label.toLocaleLowerCase('fr').includes(target) || target.includes(candidate.label.toLocaleLowerCase('fr'))));
    if (item?.path) return { path: item.path, icon: item.icon ?? 'report' };
    if (label.includes('organisation') && menuPath.includes('param')) return { path: '/settings/organisation', icon: 'building' };
    if (label.includes('profil') || label.includes('param')) return { path: '/settings', icon: 'id-badge' };
    return null;
}
