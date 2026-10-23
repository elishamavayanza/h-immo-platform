export type CustomerInquiryStatus =
  | 'new' | 'in_progress' | 'contacted' | 'visit_proposed'
  | 'visit_completed' | 'negotiation' | 'converted' | 'closed'

export type CustomerInquiryType = 'information' | 'visit'

export interface CustomerInquiry {
  uuid: string
  listingId: string
  listingTitle: string
  listingReference: string
  city: string
  building: string
  unitType: string
  surface: string
  bedrooms: number | null
  rooms: number | null
  bathrooms: number | null
  offerType: 'rent' | 'sale'
  price: string
  currency: 'USD' | 'CDF'
  type: CustomerInquiryType
  name: string
  phone: string
  email: string | null
  message: string | null
  status: CustomerInquiryStatus
  assignedTo: string | null
  createdAt: string
  updatedAt: string
  closedReason: string | null
  convertedLeaseUuid: string | null
  convertedLeaseReference: string | null
}

export type CustomerVisitStatus = 'proposed' | 'confirmed' | 'rescheduled' | 'completed' | 'cancelled'

export interface CustomerVisit {
  uuid: string
  inquiryUuid: string
  scheduledAt: string
  status: CustomerVisitStatus
  outcome: string | null
  createdBy: string
  createdAt: string
  updatedAt: string
}

export interface CustomerInquiryHistoryItem {
  uuid: string
  action: string
  oldStatus: CustomerInquiryStatus | null
  newStatus: CustomerInquiryStatus
  reason: string | null
  actor: string | null
  createdAt: string
}

export interface CustomerInquiryPage {
  items: CustomerInquiry[]
  total: number
  page: number
  pages: number
}

export interface CustomerInquiryFilters {
  status: CustomerInquiryStatus | 'all'
  type: CustomerInquiryType | 'all'
  page: number
}

export const CUSTOMER_INQUIRY_STATUSES: ReadonlyArray<CustomerInquiryStatus> = [
  'new', 'in_progress', 'contacted', 'visit_proposed', 'visit_completed', 'negotiation', 'converted', 'closed',
]

export const CUSTOMER_INQUIRY_TRANSITIONS: Readonly<Record<CustomerInquiryStatus, ReadonlyArray<CustomerInquiryStatus>>> = {
  new: [],
  in_progress: ['contacted', 'closed'],
  contacted: ['negotiation', 'closed'],
  visit_proposed: [],
  visit_completed: ['negotiation', 'closed'],
  negotiation: ['closed'],
  converted: [],
  closed: [],
}

export const CUSTOMER_INQUIRY_STATUS_LABEL: Readonly<Record<CustomerInquiryStatus, string>> = {
  new: 'Nouvelle', in_progress: 'En cours', contacted: 'Contact établi',
  visit_proposed: 'Visite proposée', visit_completed: 'Visite effectuée',
  negotiation: 'Négociation', converted: 'Convertie', closed: 'Clôturée',
}

export const CUSTOMER_INQUIRY_TYPE_LABEL: Readonly<Record<CustomerInquiryType, string>> = {
  information: 'Information', visit: 'Demande de visite',
}
