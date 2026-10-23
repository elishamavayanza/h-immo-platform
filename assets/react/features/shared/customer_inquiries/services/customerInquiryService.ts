import { apiClient } from '../../../../../services/api/client'
import type { Feedback } from '../../../../../services/api/api.types'
import type { CustomerInquiry, CustomerInquiryHistoryItem, CustomerInquiryPage, CustomerInquiryStatus, CustomerInquiryType, CustomerVisit } from '../types/customerInquiry.types'

function base(organizationUuid: string): string {
  return `/v1/organizations/${encodeURIComponent(organizationUuid)}/inquiries`
}

export type CustomerInquiryAlert = {
  uuid: string
  inquiryUuid: string
  listingTitle: string
  city: string
  createdAt: string
  readAt: string | null
}

export async function fetchCustomerInquiryAlerts(organizationUuid: string): Promise<{ items: CustomerInquiryAlert[]; unread: number }> {
  const { data } = await apiClient.get<Feedback<{ items: CustomerInquiryAlert[]; unread: number }>>(`${base(organizationUuid)}/notifications`)
  return data.data
}

export async function markCustomerInquiryAlertRead(organizationUuid: string, notificationUuid: string): Promise<void> {
  await apiClient.post(`${base(organizationUuid)}/notifications/${encodeURIComponent(notificationUuid)}/read`, {})
}

export async function fetchCustomerInquiries(organizationUuid: string, status: CustomerInquiryStatus | 'all', type: CustomerInquiryType | 'all', page: number): Promise<CustomerInquiryPage> {
  const params = new URLSearchParams({ page: String(page), limit: '20' })
  if (status !== 'all') params.set('status', status)
  if (type !== 'all') params.set('type', type)
  const { data } = await apiClient.get<Feedback<CustomerInquiryPage>>(`${base(organizationUuid)}?${params.toString()}`)
  return data.data
}

export async function fetchCustomerInquiry(organizationUuid: string, inquiryUuid: string): Promise<CustomerInquiry> {
  const { data } = await apiClient.get<Feedback<CustomerInquiry>>(`${base(organizationUuid)}/${encodeURIComponent(inquiryUuid)}`)
  return data.data
}

export async function fetchCustomerInquiryHistory(organizationUuid: string, inquiryUuid: string): Promise<CustomerInquiryHistoryItem[]> {
  const { data } = await apiClient.get<Feedback<CustomerInquiryHistoryItem[]>>(`${base(organizationUuid)}/${encodeURIComponent(inquiryUuid)}/history`)
  return data.data
}

export async function takeCustomerInquiry(organizationUuid: string, inquiryUuid: string): Promise<CustomerInquiry> {
  const { data } = await apiClient.post<Feedback<CustomerInquiry>>(`${base(organizationUuid)}/${encodeURIComponent(inquiryUuid)}/take-ownership`, {})
  return data.data
}

export async function transitionCustomerInquiry(organizationUuid: string, inquiryUuid: string, status: CustomerInquiryStatus, reason?: string): Promise<CustomerInquiry> {
  const { data } = await apiClient.post<Feedback<CustomerInquiry>>(`${base(organizationUuid)}/${encodeURIComponent(inquiryUuid)}/transition`, { status, ...(reason ? { reason } : {}) })
  return data.data
}

export async function fetchCustomerVisits(organizationUuid: string, inquiryUuid: string): Promise<CustomerVisit[]> {
  const { data } = await apiClient.get<Feedback<CustomerVisit[]>>(`${base(organizationUuid)}/${encodeURIComponent(inquiryUuid)}/visits`)
  return data.data
}

export async function proposeCustomerVisit(organizationUuid: string, inquiryUuid: string, scheduledAt: string): Promise<CustomerVisit> {
  const { data } = await apiClient.post<Feedback<CustomerVisit>>(`${base(organizationUuid)}/${encodeURIComponent(inquiryUuid)}/visits`, { scheduledAt })
  return data.data
}

export async function rescheduleCustomerVisit(organizationUuid: string, inquiryUuid: string, visitUuid: string, scheduledAt: string): Promise<CustomerVisit> {
  const { data } = await apiClient.patch<Feedback<CustomerVisit>>(`${base(organizationUuid)}/${encodeURIComponent(inquiryUuid)}/visits/${encodeURIComponent(visitUuid)}/reschedule`, { scheduledAt })
  return data.data
}

export async function transitionCustomerVisit(organizationUuid: string, inquiryUuid: string, visitUuid: string, status: 'confirmed' | 'completed' | 'cancelled', reason?: string): Promise<CustomerVisit> {
  const { data } = await apiClient.patch<Feedback<CustomerVisit>>(`${base(organizationUuid)}/${encodeURIComponent(inquiryUuid)}/visits/${encodeURIComponent(visitUuid)}/transition`, { status, ...(reason ? { reason } : {}) })
  return data.data
}

export async function convertCustomerInquiryToLease(organizationUuid: string, inquiryUuid: string): Promise<CustomerInquiry> {
  const { data } = await apiClient.post<Feedback<CustomerInquiry>>(`${base(organizationUuid)}/${encodeURIComponent(inquiryUuid)}/convert-to-lease`, {})
  return data.data
}
