import type { OrganizationRole, PlatformRole } from '../../../../../services/api/api.types';

export type HelpRole = PlatformRole | OrganizationRole | null;

export interface HelpQuestion {
    id: string;
    question: string;
    answer: string;
    guide?: {
        location: string;
        steps: readonly string[];
        result?: string;
    };
    keywords?: readonly string[];
    roles?: readonly Exclude<HelpRole, null>[];
}

export interface PageHelp {
    path: string;
    title: string;
    description: string;
    questions: readonly HelpQuestion[];
    roles: readonly HelpRole[];
}
