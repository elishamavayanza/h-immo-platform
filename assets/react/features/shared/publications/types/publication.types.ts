export type PublicationCategory = 'feature' | 'improvement' | 'correction' | 'maintenance' | 'important';
export type PublicationStatus = 'draft' | 'published' | 'withdrawn' | 'archived';
export interface PublicationBlock { type: 'paragraph' | 'bullet' | 'ordered'; text: string; }
export interface PublicationImage { path: string; url: string; caption: string; }
export interface Publication {
    uuid: string; title: string; contentBlocks: PublicationBlock[]; category: PublicationCategory;
    audience: 'all' | 'roles' | 'organization'; audienceRoles: string[];
    organization: { uuid: string; name: string } | null; scheduledAt: string | null;
    publishedAt: string | null; endsAt: string | null; status: PublicationStatus; images: PublicationImage[];
    createdAt: string; recipientCount?: number; viewCount?: number; expiredCount?: number; firstViewedAt?: string | null;
}
export interface PublicationDraft {
    title: string; content: string; category: PublicationCategory; audience: 'all' | 'roles' | 'organization';
    audienceRoles: string[]; organizationUuid: string | null; scheduledAt: string | null; endsAt: string | null;
}
