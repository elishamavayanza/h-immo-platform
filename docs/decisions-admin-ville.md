# Décisions — Espace ADMIN_VILLE

Décisions prises lors du socle (V0) de l'espace ADMIN_VILLE. Toute décision
contraire à ces lignes doit être soumise avant d'altérer le comportement.

## D-V1 — Locataire sans bail (visibilité + premier bail)

**Constat B-V1.** `TenantRepository::findPaginatedAccessible()` ne renvoie, pour
un ADMIN_VILLE, que les locataires ayant un bail sur une unité d'une de ses
villes. Un locataire qu'il vient de créer est donc invisible dans la liste, et
il ne peut pas lui créer son premier bail. Par ailleurs `checkTenantAccess()`
ne contrôlait que l'organisation : un ADMIN_VILLE pouvait ouvrir par UUID un
locataire d'une autre ville de la même organisation.

**Décision (recommandation appliquée).** En couche backend :

- Un locataire d'une organisation rattachée à un ADMIN_VILLE est visible s'il
  a un bail sur une unité d'une de SES villes **ou** s'il n'a encore **aucun**
  bail (locataire créé, en attente de son premier bail). Un locataire dont tous
  les baux sont hors de ses villes reste invisible.
- `checkTenantAccess()` est aligné sur la liste : pour un ADMIN_VILLE (rôle
  résolu dans l'organisation du locataire), l'accès est refusé si le locataire
  a au moins un bail et qu'aucun n'est situé dans une ville affectée.
- Côté interface, le formulaire « Nouveau locataire » enchaîne sur « Créer le
  bail » (assistant en deux étapes, l'UUID du locataire reste en mémoire) et le
  sélecteur d'unités ne propose que les unités des villes affectées sans bail
  actif.

## D-V2 — Annulation d'une dépense

**Constat B-V3.** `ExpenseService::cancelExpense()` s'appuie sur
`SecurityAction::DELETE_EXPENSE`, action absente de `checkAdminVilleAction()`.

**Décision : ne pas donner l'annulation/suppression de dépense à l'ADMIN_VILLE.**
Le bouton « Annuler la dépense » est masqué dans son espace Dépenses (seuls
créer et modifier sont proposés). Un appel forcé reste refusé par le backend
(403). La matrice `ADMIN_VILLE_ACTIONS` de `permissions.ts` est conforme
(`view_expense`, `create_expense`, `update_expense` uniquement).

## D-V3 — Affectations d'ouvriers (pas d'entrée « Personnel »)

**Constat.** `checkAdminVilleAction()` autorise la gestion des affectations
d'ouvriers (`VIEW/CREATE/UPDATE/DELETE_WORKER_ASSIGNMENT`), mais l'espace
ADMIN_VILLE n'a pas d'entrée « Personnel » : les ouvriers restent en lecture
seule côté « Personnel » (non présent dans le menu) et les affectations n'ont
aucun écran en V1.

**Décision : ne pas ajouter de menu « Personnel » ni d'écran d'affectations en
V1.** Le pouvoir backend de l'ADMIN_VILLE sur les affectations est conservé
(toute autre résolution créera un écart entre la matrice et l'UI). L'écart est
documenté ici, à réévaluer si un besoin se manifeste.

## Permissions — vérification de la table `ADMIN_VILLE_ACTIONS`

La table `ADMIN_VILLE_ACTIONS` de `assets/react/features/shared/permissions.ts`
a été comparée champ à champ à `SecurityService::checkAdminVilleAction()`
(`src/Security/SecurityService.php`) : **aucune divergence**. Le front expose
exactement ce que le backend autorise. Aucune correction nécessaire ; les
boutons s'affichent via `canDo('admin_ville', action)`.

## P0 — Contre-écritures d'annulation (Paiement / Dépense)

**Constat.** `SecurityAction::MARK_RENT_OVERDUE` (l.84 de
`SecurityAction.php`) est référencée dans `SecurityService::checkAdminVilleAction()`
(l.1161) et `checkAdminImmobilierAction()` (l.1110), mais n'est **implémentée
nulle part** : aucun contrôleur, service ni événement ne pose le statut
`OVERDUE` sur un `Rent`. L'entité `Rent` (l.206-289) documente explicitement
que `OVERDUE` est un **état dérivé à la lecture** (via `getComputedStatus()`
selon la date d'échéance et la somme payée), jamais persisté.

**Décision : `OVERDUE` reste un état dérivé, jamais persisté.**

- `MARK_RENT_OVERDUE` est une action **dormante** (réservée à un usage futur
  éventuel, sans implémentation actuelle).
- `Rent::syncStatus()` (appelé par `PaymentService::refreshRentStatus()` et
  `RentService`) ne pose que `PENDING` / `PARTIALLY_PAID` / `PAID`.
- L'endpoint `GET /api/v1/rents/overdue` filtre dynamiquement les échéances
  impayées dont la date d'échéance est dépassée.
- L'annulation d'un paiement (contre-écriture négative) fait redescendre la
  somme payée, donc une échéance `PAID` peut redevenir `PARTIALLY_PAID` ou
  `PENDING` et, si sa date est passée, réapparaître dans la liste des impayés.
  Cela est cohérent avec le modèle dérivé.
- L'action `MARK_RENT_OVERDUE` est conservée dans l'enum et la matrice pour
  l'ADMIN_VILLE (droit de lecture sur les impayés) mais n'a pas de contrôle
  d'écriture associé.

Cette décision garantit que les sommes et statuts restent cohérents sans
introduire de colonne persistante `OVERDUE` supplémentaire, et que la règle
historique « le statut ne descend jamais » est **supprimée** (elle masquait
précisément le bug d'annulation).