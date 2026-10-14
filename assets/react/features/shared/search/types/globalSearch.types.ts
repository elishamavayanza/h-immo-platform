export interface GlobalSearchResult {
    id: string;
    type: 'Ville' | 'Parcelle' | 'Bâtiment' | 'Unité' | 'Locataire';
    label: string;
    detail: string;
    path: string;
}
