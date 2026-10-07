export interface LoyerRow { id: string; tenant: string; city: string; property: string; period: string; dueDate: string; amount: string; status: 'Payé' | 'Partiel' | 'En attente' | 'En retard'; }
