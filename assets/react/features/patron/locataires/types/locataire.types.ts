export type TenantStatus = 'Actif' | 'En attente' | 'Ancien';
export interface LocataireRow { id: string; name: string; email: string; phone: string; city: string; property: string; leaseEnd: string; balance: string; status: TenantStatus; }
