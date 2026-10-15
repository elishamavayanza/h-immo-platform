import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';

export interface MaintenanceWorkOrder {
    uuid: string;
    organizationUuid: string;
    cityUuid: string;
    parcelUuid: string | null;
    buildingUuid: string | null;
    unitUuid: string | null;
    targetLabel: string;
    title: string;
    description: string;
    priority: 'low' | 'normal' | 'high' | 'urgent';
    status: 'open' | 'in_progress' | 'completed' | 'cancelled';
    workerAssignmentUuid: string | null;
    workerName: string | null;
    reportedAt: string;
    dueDate: string | null;
    completedAt: string | null;
    createdBy: string;
}

export type MaintenanceWorkOrderInput = {
    organizationUuid: string;
    cityUuid: string;
    title: string;
    description: string;
    priority: MaintenanceWorkOrder['priority'];
    parcelUuid: string | null;
    buildingUuid: string | null;
    unitUuid: string | null;
    workerAssignmentUuid: string | null;
    dueDate: string | null;
};

export async function listMaintenanceWorkOrders(organizationUuid: string, cityUuid: string): Promise<MaintenanceWorkOrder[]> {
    const { data } = await apiClient.get<Feedback<MaintenanceWorkOrder[]>>('/v1/maintenance/work-orders', { params: { organizationUuid, cityUuid } });
    return data.data;
}

export async function createMaintenanceWorkOrder(input: MaintenanceWorkOrderInput): Promise<MaintenanceWorkOrder> {
    const { data } = await apiClient.post<Feedback<MaintenanceWorkOrder>>('/v1/maintenance/work-orders', input);
    return data.data;
}

export async function editMaintenanceWorkOrder(uuid: string, input: MaintenanceWorkOrderInput): Promise<MaintenanceWorkOrder> {
    const { data } = await apiClient.put<Feedback<MaintenanceWorkOrder>>(`/v1/maintenance/work-orders/${uuid}`, input);
    return data.data;
}

export async function updateMaintenanceStatus(item: MaintenanceWorkOrder, status: MaintenanceWorkOrder['status']): Promise<MaintenanceWorkOrder> {
    const { data } = await apiClient.patch<Feedback<MaintenanceWorkOrder>>(`/v1/maintenance/work-orders/${item.uuid}/status`, {
        organizationUuid: item.organizationUuid, cityUuid: item.cityUuid, status,
    });
    return data.data;
}
