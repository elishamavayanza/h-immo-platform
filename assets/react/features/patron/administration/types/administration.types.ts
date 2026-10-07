export interface TeamMember { id: string; name: string; email: string; role: 'Patron' | 'Admin immobilier' | 'Admin ville'; scope: string; status: 'Actif' | 'Invitation envoyée' | 'Suspendu'; }
