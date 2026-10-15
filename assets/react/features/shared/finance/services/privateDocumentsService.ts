import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';

export interface PrivateDocumentItem {
    uuid: string;
    category: string;
    originalName: string;
    fileSize: number;
    description: string | null;
    createdAt: string;
    uploadedBy: string;
}

export async function listPrivateDocuments(organizationUuid: string, relatedType: string, relatedUuid: string, cityUuid?: string): Promise<PrivateDocumentItem[]> {
    const response = await apiClient.get<Feedback<PrivateDocumentItem[]>>('/v1/documents', {
        params: { organizationUuid, relatedType, relatedUuid, ...(cityUuid ? { cityUuid } : {}) },
    });
    return response.data.data ?? [];
}

export async function uploadPrivateDocument(organizationUuid: string, relatedType: string, relatedUuid: string, file: File): Promise<void> {
    const form = new FormData();
    form.append('organizationUuid', organizationUuid);
    form.append('relatedType', relatedType);
    form.append('relatedUuid', relatedUuid);
    form.append('category', relatedType === 'lease' ? 'lease_contract' : relatedType === 'payment' ? 'payment_receipt' : 'invoice');
    form.append('file', file);
    await apiClient.post('/v1/documents', form);
}

export async function downloadPrivateDocument(uuid: string, name: string): Promise<void> {
    const response = await apiClient.getBlob(`/v1/documents/${uuid}/download`);
    const url = URL.createObjectURL(response.data);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = name;
    anchor.click();
    URL.revokeObjectURL(url);
}

export async function deletePrivateDocument(uuid: string): Promise<void> {
    await apiClient.delete(`/v1/documents/${uuid}`);
}
