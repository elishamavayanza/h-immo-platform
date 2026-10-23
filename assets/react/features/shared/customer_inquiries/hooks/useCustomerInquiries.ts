import { useCallback, useEffect, useRef, useState } from 'react'
import { useOrganization } from '../../../../app/providers/OrganizationProvider'
import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext'
import { ApiError } from '../../../../../services/api/api.types'
import { canDo } from '../../permissions'
import { convertCustomerInquiryToLease, fetchCustomerInquiries, fetchCustomerInquiry, fetchCustomerInquiryHistory, fetchCustomerVisits, proposeCustomerVisit, rescheduleCustomerVisit, takeCustomerInquiry, transitionCustomerInquiry, transitionCustomerVisit } from '../services/customerInquiryService'
import type { CustomerInquiry, CustomerInquiryHistoryItem, CustomerInquiryStatus, CustomerInquiryType, CustomerVisit } from '../types/customerInquiry.types'

const safeError = (error: unknown): string => {
  if (!(error instanceof ApiError)) return 'Impossible de terminer cette action. Vérifiez votre connexion puis réessayez.'
  if (error.status === 401) return 'Votre session a expiré. Connectez-vous à nouveau.'
  if (error.status === 403) return 'Vous n’avez pas les droits nécessaires pour cette action.'
  if (error.status === 404) return 'Cette demande n’est plus accessible.'
  if (error.status === 409) return 'Cette demande a changé. Actualisez la liste avant de réessayer.'
  if (error.status === 400 || error.status === 422) return 'Vérifiez les informations saisies puis réessayez.'
  return 'Le service est momentanément indisponible. Réessayez plus tard.'
}

export function useCustomerInquiries() {
  const { currentOrganization, organizationRole } = useOrganization()
  const organizationUuid = currentOrganization?.uuid ?? null
  const canManage = canDo(organizationRole, 'manage_customer_inquiry')
  const { push } = useToast()
  const [status, setStatus] = useState<CustomerInquiryStatus | 'all'>('all')
  const [type, setType] = useState<CustomerInquiryType | 'all'>('all')
  const [page, setPage] = useState(1)
  const [items, setItems] = useState<CustomerInquiry[]>([])
  const [total, setTotal] = useState(0)
  const [pages, setPages] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [selected, setSelected] = useState<CustomerInquiry | null>(null)
  const [history, setHistory] = useState<CustomerInquiryHistoryItem[]>([])
  const [visits, setVisits] = useState<CustomerVisit[]>([])
  const [historyLoading, setHistoryLoading] = useState(false)
  const [historyError, setHistoryError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)
  const requestId = useRef(0)

  const reload = useCallback(async () => {
    const current = ++requestId.current
    if (!organizationUuid) { setItems([]); setTotal(0); setPages(1); setLoading(false); return }
    setLoading(true); setError(null)
    try {
      const result = await fetchCustomerInquiries(organizationUuid, status, type, page)
      if (current !== requestId.current) return
      setItems(result.items); setTotal(result.total); setPages(result.pages)
    } catch (cause) {
      if (current === requestId.current) setError(safeError(cause))
    } finally {
      if (current === requestId.current) setLoading(false)
    }
  }, [organizationUuid, status, type, page])

  useEffect(() => { void reload() }, [reload])

  const select = useCallback(async (item: CustomerInquiry) => {
    setSelected(item); setHistory([]); setVisits([]); setHistoryError(null); setHistoryLoading(true)
    if (!organizationUuid) { setHistoryLoading(false); return }
    try {
      const [detail, events, scheduledVisits] = await Promise.all([
        fetchCustomerInquiry(organizationUuid, item.uuid),
        fetchCustomerInquiryHistory(organizationUuid, item.uuid),
        fetchCustomerVisits(organizationUuid, item.uuid),
      ])
      setSelected(detail); setHistory(events); setVisits(scheduledVisits)
    } catch (cause) { setHistoryError(safeError(cause)) }
    finally { setHistoryLoading(false) }
  }, [organizationUuid])

  const runAction = useCallback(async (action: () => Promise<CustomerInquiry>, success: string, conflictMessage?: string) => {
    if (!organizationUuid || !selected || !canManage || pending) return false
    setPending(true)
    try {
      const updated = await action()
      setSelected(updated)
      push('success', success)
      await Promise.all([reload(), select(updated)])
      return true
    } catch (cause) {
      push('error', cause instanceof ApiError && cause.status === 409 && conflictMessage ? conflictMessage : safeError(cause))
      return false
    } finally { setPending(false) }
  }, [organizationUuid, selected, canManage, pending, push, reload, select])

  const takeOwnership = () => selected && organizationUuid
    ? runAction(() => takeCustomerInquiry(organizationUuid, selected.uuid), 'La demande vous est attribuée.')
    : Promise.resolve(false)

  const transition = (target: CustomerInquiryStatus, reason?: string) => selected && organizationUuid
    ? runAction(() => transitionCustomerInquiry(organizationUuid, selected.uuid, target, reason), 'Le suivi de la demande a été mis à jour.')
    : Promise.resolve(false)

  const proposeVisit = (scheduledAt: string) => selected && organizationUuid
    ? runAction(async () => {
      await proposeCustomerVisit(organizationUuid, selected.uuid, scheduledAt)
      return fetchCustomerInquiry(organizationUuid, selected.uuid)
    }, 'La proposition de visite a été enregistrée. Confirmez l’horaire avec le client.')
    : Promise.resolve(false)

  const changeVisit = async (visitUuid: string, status: 'confirmed' | 'completed' | 'cancelled', reason?: string) => {
    if (!organizationUuid || !selected || !canManage || pending) return false
    setPending(true)
    try {
      await transitionCustomerVisit(organizationUuid, selected.uuid, visitUuid, status, reason)
      await select(selected)
      await reload()
      push('success', status === 'confirmed' ? 'La visite est confirmée.' : status === 'completed' ? 'Le résultat de la visite est enregistré.' : 'La visite est annulée.')
      return true
    } catch (cause) { push('error', safeError(cause)); return false }
    finally { setPending(false) }
  }

  const rescheduleVisit = async (visitUuid: string, scheduledAt: string) => {
    if (!organizationUuid || !selected || !canManage || pending) return false
    setPending(true)
    try {
      await rescheduleCustomerVisit(organizationUuid, selected.uuid, visitUuid, scheduledAt)
      await select(selected)
      await reload()
      push('success', 'La nouvelle proposition de date est enregistrée. Confirmez-la avec le client.')
      return true
    } catch (cause) { push('error', safeError(cause)); return false }
    finally { setPending(false) }
  }

  const convertToLease = () => selected && organizationUuid
    ? runAction(() => convertCustomerInquiryToLease(organizationUuid, selected.uuid), 'La demande est liée au bail actif existant.', 'Aucun bail actif correspondant au bien et au numéro du demandeur. Créez et activez le bail dans le parcours locatif, puis réessayez.')
    : Promise.resolve(false)

  return { organizationRole, canManage, status, setStatus: (value: CustomerInquiryStatus | 'all') => { setPage(1); setStatus(value) }, type, setType: (value: CustomerInquiryType | 'all') => { setPage(1); setType(value) }, page, setPage, items, total, pages, loading, error, selected, select, history, historyLoading, historyError, visits, pending, takeOwnership, transition, proposeVisit, changeVisit, rescheduleVisit, convertToLease, reload }
}
