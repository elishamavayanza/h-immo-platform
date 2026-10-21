# Décisions — Espace PATRON

Décisions prises lors du socle (P0) de l'espace PATRON. Toute décision
contraire à ces lignes doit être soumise avant d'altérer le comportement.

Principe produit : **le Patron consulte et pilote, il n'opère pas.** Les écrans
opérationnels (créer un bien, un locataire, un bail, un paiement, une dépense)
appartiennent à l'ADMIN_IMMOBILIER et à l'ADMIN_VILLE. Le Patron dispose donc
de pages **en lecture seule**. Ses écritures sont limitées aux actions
explicitement accordées par `SecurityService::checkPatronAction()`, notamment
l'invitation de certains administrateurs et la gestion de la vitrine/logo.
Les coordonnées générales de l'organisation et les membres existants sont en
consultation seule dans Administration.

## D-P1 — Lecture seule : interface alignée sur les permissions serveur

**Constat actuel.** `SecurityService::checkPatronAction()` applique une liste
d'actions autorisées. Elle inclut notamment `VIEW_ORGANIZATION`, `VIEW_USER`,
`CREATE_CITY_ADMIN` et `CREATE_ORGANIZATION_ADMIN`, mais pas `UPDATE`,
`MANAGE_ORGANIZATION` ni `MANAGE_USERS`. Le backend reste l'autorité ;
l'interface masque également les commandes refusées pour éviter des erreurs
prévisibles.

**Décision :** ne pas ajouter de bouton d'écriture opérationnel dans les pages
Patron « lecture ». Pour les composants réutilisés, la restriction est portée
par `readOnly` (D-P2) ; pour les pages dédiées, les commandes visibles doivent
correspondre aux actions accordées par le backend.

## D-P2 — Réutilisation des écrans ADMIN_IMMOBILIER (`readOnly`)

**Constat.** Les pages ADMIN_IMMOBILIER (patrimoine, locataires, loyers,
dépenses, vitrine) sont déjà branchées sur l'API et exposent leurs propres
contrôles d'écriture via `canDo(role, action)`. Or `permissions.ts` déclare
`patron: 'all'` : `canDo('patron', …)` renvoie **vrai** pour toute action, donc
il ne peut pas servir à masquer les contrôles côté Patron.

**Décision (recommandée, appliquée) :**

- Ne pas réécrire les tables. Ajouter une prop optionnelle `readOnly?: boolean`
  aux composants ADMIN_IMMOBILIER réutilisés (onglets Patrimoine, tables
  locataires/loyers/dépenses/vitrine). Absente ou `false`, le rendu est
  **strictement inchangé** pour ADMIN_IMMOBILIER et ADMIN_VILLE.
- Quand `readOnly` est vrai : aucun bouton « Ajouter / Modifier / Supprimer /
  Annuler / Publier », aucune action de ligne (le `PopoverMenu` n'est pas rendu),
  aucun formulaire d'écriture, aucune modale de mutation.
- Les pages Patron réutilisent les composants avec `readOnly` et leurs propres
  services de lecture. Les écritures de mutation ne sont jamais propagées.
- `canDo()` n'est **pas** utilisé pour décider de la lecture seule ; c'est
  `readOnly` qui tranche.

## D-P3 — Ouvriers et affectations

Pas d'entrée « Personnel » dans le menu du Patron
(`sidebar.config.ts` : `[DASHBOARD, RAPPORTS, PATRIMOINE, LOCATAIRES, LOYERS,
DEPENSES, VITRINE, ADMINISTRATION]`).

**Décision :** ne pas ajouter de menu « Personnel » ni d'écran d'affectations.
Le lien « Employés » de la page Patrimoine ADMIN_IMMOBILIER est masqué pour le
Patron (aucune destination routable ne lui correspond dans son espace).

## D-P4 — Changer le rôle d'un membre

L'endpoint `PUT /v1/identity/organization-users/{uuid}` existe, mais il exige
une permission de gestion des membres que le PATRON ne reçoit pas dans
`checkPatronAction()`. L'exposer à ce rôle multiplierait aussi les erreurs de
périmètre ville (un ADMIN_VILLE sans ville, un ADMIN_IMMOBILIER promu sans
contrôle).

**Décision actuelle :** l'écran Administration n'expose ni « Supprimer », ni
« Changer le rôle », ni suspendre/réactiver, ni modifier les périmètres de villes.
Le PATRON peut consulter les affectations et inviter les rôles autorisés. La
modification, la suspension et les changements de périmètre sont réservés aux
rôles disposant des actions correspondantes côté serveur.

## D-P5 — Vitrine : réglages publics + logo, annonces en lecture

**Constat.** Le menu Patron n'expose que `VITRINE` et `ADMINISTRATION` comme
zones de paramétrage. L'endpoint `PATCH
/api/v1/identity/organizations/{uuid}/showcase` (`OrganizationShowcaseRequest`,
réservé au PATRON) gère slug / description / visibilité ; le logo passe par
`POST|DELETE /api/v1/media/organizations/{uuid}/logo` ; le paramétrage général
(nom, contacts) dispose d'un endpoint `PUT /api/v1/identity/organizations/{uuid}`,
mais l'action `UPDATE` n'est pas accordée au PATRON par la matrice actuelle.

**Décision (appliquée) :**
- La page **Vitrine** du Patron porte les *réglages publics* (slug, description,
  visibilité) **et** le logo — ce sont les éléments de présence publique.
- Les coordonnées générales (nom, contacts) restent consultables sur la page
  **Administration** ; le PATRON ne peut pas les modifier avec les permissions
  actuellement attribuées.
- Les **annonces** (Unit `isPublished`) sont affichées en lecture seule via
  `VitrineTable` + le hook Vitrine de l'ADMIN_IMMOBILIER (`readOnly`) : la
  publication reste une opération d'ADMIN_IMMOBILIER.

**Conséquence backend.** `OrganizationResponse` n'exposait ni `slug`, ni
`publicDescription`, ni `isPubliclyListed` : la page ne pouvait pas pré-remplir
les réglages courants. Les trois champs ont été **ajoutés** au DTO (changement
additif, sans rupture ; OpenAPI mis à jour).

**Limite connue.** Le slug ne peut pas être *effacé* : le backend n'accepte ni
`""` (regex `^[a-z0-9]+(?:-[a-z0-9]+)*$`) ni `null` (interprété comme « ne pas
modifier »). L'interface ne propose donc que la modification, jamais le retrait
du slug, et n'envoie le champ que s'il est renseigné. La description, elle, se
vide en envoyant `""`.

## Écarts backend constatés (P0)

- **D-B1 — `MediaController` sans `use App\Security\SecurityAction;`.** Les
  méthodes `listParcelPhotos()` et `listBuildingPhotos()` référençaient
  `SecurityAction::VIEW_PARCEL` / `VIEW_BUILDING` sans importer l'enum ; dans le
  namespace `App\Controller\Api\System`, cela résolvait vers une classe
  inexistante → 500 (même classe de faute que D15). Corrigé : import ajouté.
  Ces deux routes GET n'étaient couvertes par aucun script.
- **D-B2 — `PatronReportFilterDto` perd les contraintes de validation.** Le DTO
  enfant redéclare les propriétés parentes sans reporter leurs `#[Assert\…]`
  (`page`, `limit`, `sortOrder`, `format`). Le cas critique
  (`organizationUuid` obligatoire) reste couvert par le contrôleur
  (`resolveReportOrganization()` → 400/404/403). Aucune correction d'office ;
  signalé ici.

## P0 — Contre-écritures d'annulation (Paiement / Dépense)

**Constat.** `SecurityAction::MARK_RENT_OVERDUE` est référencée dans la matrice
PATRON mais n'est **implémentée nulle part**. L'entité `Rent` documente que
`OVERDUE` est un **état dérivé à la lecture**, jamais persisté.

**Décision : `OVERDUE` reste un état dérivé, jamais persisté.**

- L'action `MARK_RENT_OVERDUE` est **dormante** (réservée à un usage futur).
- `Rent::syncStatus()` ne pose que `PENDING` / `PARTIALLY_PAID` / `PAID`.
- L'endpoint `GET /api/v1/rents/overdue` filtre dynamiquement.
- L'annulation d'un paiement (contre-écriture négative) fait redescendre la
  somme payée → une échéance `PAID` peut redevenir `PARTIALLY_PAID` ou
  `PENDING` et réapparaître dans les impayés si sa date est passée.
- La règle historique « le statut ne descend jamais » est **supprimée** (elle
  masquait précisément le bug d'annulation).

## En-tête des pages Patron

Les pages Patron « lecture » réutilisent la page ADMIN_IMMOBILIER correspondante
avec `readOnly`. Le bandeau de ces pages (`GESTION IMMOBILIÈRE / Patrimoine`,
`SUIVI FINANCIER / Dépenses`, …) est **neutre vis-à-vis du rôle** et reste donc
partagé. Les statistiques d'organisation (`OrganizationSummary`) sont rendues par
la page elle-même à partir des données réelles de l'organisation active.
