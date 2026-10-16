export type UserManualArticle = {
    id: string;
    name: string;
    menu: string;
    goal: string;
    steps: string[];
    fields?: string;
    result?: string;
    limits?: string;
    image?: string;
};

export type UserManualAction = {
    id: string;
    index: number;
    title: string;
    instruction: string;
};

export type UserManual = {
    role: string;
    label: string;
    title: string;
    description: string;
    version: string;
    updatedAt: string;
    filename: string;
    pageCount: number;
    modules: UserManualArticle[];
};
