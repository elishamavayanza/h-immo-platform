import { useState } from 'react'
import { useCustomerInquiries } from '../hooks/useCustomerInquiries'
import { Button } from '../../../../components/UI/Button'
import { Badge } from '../../../../components/UI/Badge'
import { EmptyState } from '../../../../components/Data/EmptyState'
import { Select } from '../../../../components/Forms/Select'
import { Textarea } from '../../../../components/Forms/Textarea'
import { FormField } from '../../../../components/Forms/FormField'
import { Input } from '../../../../components/Forms/Input'
import { Modal } from '../../../../components/UI/Modal'
import {
  CUSTOMER_INQUIRY_STATUS_LABEL, CUSTOMER_INQUIRY_TRANSITIONS, CUSTOMER_INQUIRY_TYPE_LABEL,
  type CustomerInquiryStatus,
} from '../types/customerInquiry.types'
import '../../../../../styles/pages/shared/_customer-inquiries.scss'

const statusOptions = [
  { value: 'all', label: 'Tous les statuts' },
  ...Object.entries(CUSTOMER_INQUIRY_STATUS_LABEL).map(([value, label]) => ({ value, label })),
]
const typeOptions = [
  { value: 'all', label: 'Tous les types' },
  { value: 'information', label: 'Information' },
  { value: 'visit', label: 'Demande de visite' },
]

function dateLabel(value: string): string {
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? 'Date indisponible' : new Intl.DateTimeFormat('fr-CD', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}

function statusVariant(status: CustomerInquiryStatus): 'default' | 'warning' | 'success' | 'error' {
  if (status === 'closed') return 'default'
  if (status === 'converted' || status === 'visit_completed') return 'success'
  if (status === 'new' || status === 'in_progress') return 'warning'
  return 'default'
}

const historyAction: Record<string, string> = {
  CREATED_PUBLIC: 'Demande reçue depuis la vitrine',
  TAKEN_OVER: 'Demande prise en charge',
  STATUS_CHANGED: 'Statut modifié',
  VISIT_PROPOSED: 'Proposition de visite enregistrée',
  VISIT_RESCHEDULED: 'Visite reportée',
  VISIT_CONFIRMED: 'Visite confirmée',
  VISIT_COMPLETED: 'Résultat de visite enregistré',
  VISIT_CANCELLED: 'Visite annulée',
  CONVERTED_TO_LEASE: 'Demande liée à un bail existant',
}

const visitStatusLabel = { proposed: 'Proposée', confirmed: 'Confirmée', rescheduled: 'À reconfirmer', completed: 'Effectuée', cancelled: 'Annulée' } as const

function localDateTimeValue(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const pad = (part: number) => String(part).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function toApiInstant(value: string): string {
  return new Date(value).toISOString().replace(/\.\d{3}Z$/, 'Z')
}

export function CustomerInquiriesPage() {
  const state = useCustomerInquiries()
  const [closeReason, setCloseReason] = useState('')
  const [closing, setClosing] = useState(false)
  const [closeError, setCloseError] = useState<string | null>(null)
  const [visitDate, setVisitDate] = useState('')
  const [creatingVisit, setCreatingVisit] = useState(false)
  const [reschedulingVisit, setReschedulingVisit] = useState<string | null>(null)
  const [visitAction, setVisitAction] = useState<{ uuid: string; status: 'completed' | 'cancelled' } | null>(null)
  const [visitReason, setVisitReason] = useState('')
  const [visitReasonError, setVisitReasonError] = useState<string | null>(null)
  const [confirmConversion, setConfirmConversion] = useState(false)
  const selected = state.selected
  const hasOpenVisit = state.visits.some((visit) => ['proposed', 'confirmed', 'rescheduled'].includes(visit.status))
  const transitionTargets: ReadonlyArray<CustomerInquiryStatus> = selected?.status === 'visit_proposed'
    ? state.historyLoading || hasOpenVisit ? [] : ['contacted', 'closed']
    : selected ? CUSTOMER_INQUIRY_TRANSITIONS[selected.status] : []

  const changeStatus = async (status: CustomerInquiryStatus) => {
    if (status === 'closed') {
      setCloseReason(''); setCloseError(null); setClosing(true)
      return
    }
    await state.transition(status)
  }

  const confirmClose = async () => {
    const reason = closeReason.trim()
    if (!reason) { setCloseError('Indiquez le motif de clôture.'); return }
    if (reason.length > 1000) { setCloseError('Le motif ne peut pas dépasser 1000 caractères.'); return }
    if (await state.transition('closed', reason)) setClosing(false)
  }

  return <main className="organization-page customer-inquiries-page">
    <header className="organization-page__header">
      <div><span className="organization-page__eyebrow">SUIVI COMMERCIAL</span><h1>Demandes clients</h1>
        <p>{state.canManage ? 'Consultez les contacts reçus et suivez chaque demande jusqu’à sa clôture.' : 'Consultez les demandes reçues et leur historique. Cet espace est en lecture seule.'}</p>
      </div>
    </header>

    <section className="customer-inquiries__panel" aria-labelledby="customer-inquiries-list-title">
      <div className="customer-inquiries__toolbar">
        <div><h2 id="customer-inquiries-list-title">Demandes reçues</h2><p>{state.total} demande(s)</p></div>
        <div className="customer-inquiries__filters">
          <Select aria-label="Filtrer par statut" options={statusOptions} value={state.status} onChange={(event) => state.setStatus(event.target.value as typeof state.status)} />
          <Select aria-label="Filtrer par type" options={typeOptions} value={state.type} onChange={(event) => state.setType(event.target.value as typeof state.type)} />
        </div>
      </div>

      {state.error && <div className="customer-inquiries__error" role="alert"><p>{state.error}</p><Button variant="secondary" onClick={() => void state.reload()}>Réessayer</Button></div>}
      {state.loading && <p className="customer-inquiries__status" role="status" aria-live="polite">Chargement des demandes…</p>}
      {!state.loading && !state.error && state.items.length === 0 && <EmptyState title="Aucune demande" description="Les demandes reçues depuis les annonces publiques apparaîtront ici." />}

      {!state.loading && !state.error && state.items.length > 0 && <>
        <div className="customer-inquiries__table-wrap" role="region" aria-label="Liste des demandes clients" tabIndex={0}>
          <table className="customer-inquiries__table">
            <thead><tr><th scope="col">Demandeur</th><th scope="col">Annonce</th><th scope="col">Type</th><th scope="col">Reçue le</th><th scope="col">Statut</th><th scope="col"><span className="sr-only">Détail</span></th></tr></thead>
            <tbody>{state.items.map((item) => <tr key={item.uuid} className={selected?.uuid === item.uuid ? 'is-selected' : undefined}>
              <td><strong>{item.name}</strong><small>{item.phone}</small></td>
              <td><strong>{item.listingTitle}</strong><small>{item.listingReference} · {item.city}</small></td>
              <td>{CUSTOMER_INQUIRY_TYPE_LABEL[item.type]}</td>
              <td>{dateLabel(item.createdAt)}</td>
              <td><Badge variant={statusVariant(item.status)}>{CUSTOMER_INQUIRY_STATUS_LABEL[item.status]}</Badge></td>
              <td><Button variant="secondary" size="small" aria-label={`Voir la demande de ${item.name}`} onClick={() => void state.select(item)}>Voir</Button></td>
            </tr>)}</tbody>
          </table>
        </div>
        {state.pages > 1 && <nav className="customer-inquiries__pagination" aria-label="Pagination des demandes">
          <Button variant="secondary" disabled={state.page <= 1 || state.loading} onClick={() => state.setPage(state.page - 1)}>Précédent</Button>
          <span aria-live="polite">Page {state.page} sur {state.pages}</span>
          <Button variant="secondary" disabled={state.page >= state.pages || state.loading} onClick={() => state.setPage(state.page + 1)}>Suivant</Button>
        </nav>}
      </>}
    </section>

    {selected && <section className="customer-inquiries__detail" aria-labelledby="customer-inquiry-detail-title">
      <div className="customer-inquiries__detail-heading"><div><span className="organization-page__eyebrow">DEMANDE DU {dateLabel(selected.createdAt)}</span><h2 id="customer-inquiry-detail-title">{selected.name}</h2></div><Badge variant={statusVariant(selected.status)}>{CUSTOMER_INQUIRY_STATUS_LABEL[selected.status]}</Badge></div>
      <div className="customer-inquiries__detail-grid">
        <div><h3>Coordonnées</h3><p><a href={`tel:${selected.phone}`}>{selected.phone}</a></p>{selected.email && <p><a href={`mailto:${selected.email}`}>{selected.email}</a></p>}</div>
        <div><h3>Annonce concernée</h3><p><strong>{selected.listingTitle}</strong></p><p>{selected.listingReference} · {selected.unitType} · {selected.surface} m²</p><p>{selected.building} · {selected.city}</p><p>{selected.offerType === 'rent' ? 'Location' : 'Vente'} · {selected.price} {selected.currency}</p></div>
        <div><h3>Demande</h3><p>{CUSTOMER_INQUIRY_TYPE_LABEL[selected.type]}</p><p className="customer-inquiries__message">{selected.message || 'Aucun message ajouté.'}</p></div>
        <div><h3>Suivi</h3><p>Responsable : {selected.assignedTo || 'Non attribuée'}</p><p>Dernière activité : {dateLabel(selected.updatedAt)}</p>{selected.convertedLeaseReference && <p>Contrat lié : {selected.convertedLeaseReference}</p>}</div>
      </div>

      {state.canManage && <div className="customer-inquiries__actions" aria-label="Actions de suivi">
        {selected.status === 'new' && <Button disabled={state.pending} isLoading={state.pending} onClick={() => void state.takeOwnership()}>Prendre en charge</Button>}
        {transitionTargets.map((target) => <Button key={target} variant={target === 'closed' ? 'secondary' : 'primary'} disabled={state.pending || state.historyLoading} onClick={() => void changeStatus(target)}>{target === 'closed' ? 'Clôturer' : CUSTOMER_INQUIRY_STATUS_LABEL[target]}</Button>)}
        {selected.status === 'negotiation' && selected.offerType === 'rent' && <Button variant="secondary" disabled={state.pending} onClick={() => setConfirmConversion(true)}>Lier au bail actif</Button>}
      </div>}

      <section className="customer-inquiries__visits" aria-labelledby="customer-visits-title">
        <div className="customer-inquiries__subheading"><div><h3 id="customer-visits-title">Visites</h3><p>Une proposition n’est pas une confirmation. Confirmez l’horaire avec le client en dehors de l’application.</p></div>
          {state.canManage && ['contacted', 'visit_proposed', 'visit_completed'].includes(selected.status) && !creatingVisit && <Button variant="secondary" size="small" disabled={state.pending} onClick={() => { setReschedulingVisit(null); setVisitDate(''); setCreatingVisit(true) }}>Proposer une visite</Button>}
        </div>
        {state.visits.length === 0 ? <p className="customer-inquiries__muted">Aucun rendez-vous enregistré.</p> : <ul className="customer-inquiries__visit-list">
          {state.visits.map((visit) => <li key={visit.uuid}>
            <div><strong>{dateLabel(visit.scheduledAt)}</strong><Badge variant={visit.status === 'completed' ? 'success' : visit.status === 'cancelled' ? 'default' : 'warning'}>{visitStatusLabel[visit.status]}</Badge><span>Proposée par {visit.createdBy}</span>{visit.outcome && <p>{visit.outcome}</p>}</div>
            {state.canManage && <div className="customer-inquiries__actions">
              {['proposed', 'rescheduled'].includes(visit.status) && <Button size="small" disabled={state.pending} onClick={() => void state.changeVisit(visit.uuid, 'confirmed')}>Confirmer</Button>}
              {visit.status === 'confirmed' && <Button size="small" disabled={state.pending} onClick={() => { setVisitReason(''); setVisitReasonError(null); setVisitAction({ uuid: visit.uuid, status: 'completed' }); }}>Enregistrer le résultat</Button>}
              {['proposed', 'rescheduled', 'confirmed'].includes(visit.status) && <Button size="small" variant="secondary" disabled={state.pending} onClick={() => { setVisitReason(''); setVisitReasonError(null); setVisitAction({ uuid: visit.uuid, status: 'cancelled' }); }}>Annuler</Button>}
              {['proposed', 'rescheduled', 'confirmed'].includes(visit.status) && <Button size="small" variant="secondary" disabled={state.pending} onClick={() => { setVisitDate(localDateTimeValue(visit.scheduledAt)); setReschedulingVisit(visit.uuid); }}>Reporter</Button>}
            </div>}
            {reschedulingVisit === visit.uuid && <form className="customer-inquiries__visit-form" onSubmit={(event) => { event.preventDefault(); if (visitDate) void state.rescheduleVisit(visit.uuid, toApiInstant(visitDate)).then((ok) => { if (ok) setReschedulingVisit(null) }); }}>
              <FormField label="Nouvelle date et heure" htmlFor={`visit-reschedule-${visit.uuid}`} required><Input id={`visit-reschedule-${visit.uuid}`} type="datetime-local" value={visitDate} min={localDateTimeValue(new Date(Date.now() + 60_000).toISOString())} onChange={(event) => setVisitDate(event.target.value)} required fullWidth /></FormField>
              <Button type="submit" size="small" disabled={state.pending || !visitDate}>Enregistrer le report</Button><Button type="button" size="small" variant="secondary" onClick={() => setReschedulingVisit(null)}>Annuler</Button>
            </form>}
          </li>)}
        </ul>}
        {state.canManage && ['contacted', 'visit_proposed', 'visit_completed'].includes(selected.status) && creatingVisit && reschedulingVisit === null && <form className="customer-inquiries__visit-form" onSubmit={(event) => { event.preventDefault(); if (visitDate) void state.proposeVisit(toApiInstant(visitDate)).then((ok) => { if (ok) { setVisitDate(''); setCreatingVisit(false) } }); }}>
          <FormField label="Date et heure proposées" htmlFor="customer-visit-scheduled-at" required><Input id="customer-visit-scheduled-at" type="datetime-local" value={visitDate} min={localDateTimeValue(new Date(Date.now() + 60_000).toISOString())} onChange={(event) => setVisitDate(event.target.value)} required fullWidth /></FormField>
          <Button type="submit" disabled={state.pending || !visitDate}>Enregistrer la proposition</Button><Button type="button" variant="secondary" disabled={state.pending} onClick={() => setCreatingVisit(false)}>Annuler</Button>
        </form>}
      </section>

      <div className="customer-inquiries__history"><h3>Historique</h3>
        {state.historyLoading && <p role="status">Chargement de l’historique…</p>}
        {state.historyError && <p role="alert" className="customer-inquiries__error-text">{state.historyError}</p>}
        {!state.historyLoading && !state.historyError && <ol>{state.history.map((event) => <li key={event.uuid}><time dateTime={event.createdAt}>{dateLabel(event.createdAt)}</time><strong>{historyAction[event.action] ?? 'Activité de suivi'}</strong><span>{event.actor ? `Par ${event.actor}` : 'Par le visiteur'}</span>{event.oldStatus && <span>{CUSTOMER_INQUIRY_STATUS_LABEL[event.oldStatus]} → {CUSTOMER_INQUIRY_STATUS_LABEL[event.newStatus]}</span>}{event.reason && <p>{event.reason}</p>}</li>)}</ol>}
      </div>
    </section>}

    <Modal isOpen={closing} onClose={() => setClosing(false)} title="Clôturer la demande" footer={<><Button variant="secondary" disabled={state.pending} onClick={() => setClosing(false)}>Annuler</Button><Button disabled={state.pending} isLoading={state.pending} onClick={() => void confirmClose()}>Confirmer la clôture</Button></>}>
      <p>Indiquez le résultat ou le motif de clôture. Cette information sera conservée dans l’historique.</p>
      <FormField label="Motif de clôture" htmlFor="inquiry-close-reason" required error={closeError ?? undefined}>
        <Textarea id="inquiry-close-reason" value={closeReason} maxLength={1000} rows={4} onChange={(event) => { setCloseReason(event.target.value); setCloseError(null) }} />
      </FormField>
    </Modal>
    <Modal isOpen={visitAction !== null} onClose={() => setVisitAction(null)} title={visitAction?.status === 'completed' ? 'Résultat de la visite' : 'Annuler la visite'} footer={<><Button variant="secondary" disabled={state.pending} onClick={() => setVisitAction(null)}>Retour</Button><Button disabled={state.pending} isLoading={state.pending} onClick={() => {
      const reason = visitReason.trim()
      if (!reason) { setVisitReasonError(visitAction?.status === 'completed' ? 'Indiquez le résultat de la visite.' : 'Indiquez le motif d’annulation.'); return }
      if (!visitAction) return
      void state.changeVisit(visitAction.uuid, visitAction.status, reason).then((ok) => { if (ok) setVisitAction(null) })
    }}>Enregistrer</Button></>}>
      <p>{visitAction?.status === 'completed' ? 'Le résultat sera conservé dans l’historique commercial.' : 'Le rendez-vous sera annulé. Indiquez le motif.'}</p>
      <FormField label={visitAction?.status === 'completed' ? 'Résultat' : 'Motif d’annulation'} htmlFor="customer-visit-reason" required error={visitReasonError ?? undefined}><Textarea id="customer-visit-reason" value={visitReason} maxLength={1000} rows={4} onChange={(event) => { setVisitReason(event.target.value); setVisitReasonError(null) }} /></FormField>
    </Modal>
    <Modal isOpen={confirmConversion} onClose={() => setConfirmConversion(false)} title="Lier la demande à un bail actif" footer={<><Button variant="secondary" disabled={state.pending} onClick={() => setConfirmConversion(false)}>Annuler</Button><Button disabled={state.pending} isLoading={state.pending} onClick={() => void state.convertToLease().then((ok) => { if (ok) setConfirmConversion(false) })}>Confirmer le lien</Button></>}>
      <p>Cette action recherche un bail actif existant pour l’unité de l’annonce et le relie à la demande. Aucun bail, vente ou réservation ne sera créé.</p>
    </Modal>
  </main>
}
