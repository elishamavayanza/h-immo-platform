# Décisions — Espace PATRON

Décisions prises lors du socle (P0) de l'espace PATRON. Toute décision
contraire à ces lignes doit être soumise avant d'altérer le comportement.

Principe produit : **le Patron consulte et pilote, il n'opère pas.** Les écrans
opérationnels (créer un bien, un locataire, un bail, un paiement, une dépense)
appartiennent à l'ADMIN_IMMOBILIER et à l'ADMIN_VILLE. Le Patron dispose donc
de pages **en lecture seule**, plus trois zones d'écriture qui lui sont propres :
l'équipe (Administration), la vitrine publique (réglages) et le paramétrage de
l'organisation.

## D-P1 — Lecture seule : règle d'interface uniquement

**Constat.** Côté backend, `SecurityService::checkPatronAction()` est vide : un
Patron peut, par API, tout faire sur son organisation (il en est le
propriétaire). La lecture seule des pages opérationnelles est donc une règle
d'**interface**, pas une règle de sécurité.

**Décision :** ne pas durcir le backend et ne pas ajouter de bouton d'écriture
opérationnel dans les pages Patron « lecture ». La restriction est portée par
la prop `readOnly` des composants réutilisés (D-P2), jamais par `canDo()`.

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

Le backend autorise `PUT /v1/identity/organization-users/{uuid}` (changement de
rôle). L'exposer multiplie les erreurs de périmètre ville (un ADMIN_VILLE sans
ville, un ADMIN_IMMOBILIER promu sans contrôle).

**Décision :** ne pas l'exposer. Suspendre puis inviter avec le bon rôle suffit.
L'écran Administration ne propose ni « Supprimer » ni « Changer le rôle ».

## D-P5 — Vitrine : réglages publics + logo, annonces en lecture

**Constat.** Le menu Patron n'expose que `VITRINE` et `ADMINISTRATION` comme
zones de paramétrage. L'endpoint `PATCH
/api/v1/identity/organizations/{uuid}/showcase` (`OrganizationShowcaseRequest`,
réservé au PATRON) gère slug / description / visibilité ; le logo passe par
`POST|DELETE /api/v1/media/organizations/{uuid}/logo` ; le paramétrage général
(nom, contacts) par `PUT /api/v1/identity/organizations/{uuid}`.

**Décision (appliquée) :**
- La page **Vitrine** du Patron porte les *réglages publics* (slug, description,
  visibilité) **et** le logo — ce sont les éléments de présence publique.
- Le *paramétrage général* de l'organisation (nom, contacts) reste sur la page
  **Administration**, aux côtés de l'équipe (P5).
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

## En-tête des pages Patron

Les pages Patron « lecture » réutilisent la page ADMIN_IMMOBILIER correspondante
avec `readOnly`. Le bandeau de ces pages (`GESTION IMMOBILIÈRE / Patrimoine`,
`SUIVI FINANCIER / Dépenses`, …) est **neutre vis-à-vis du rôle** et reste donc
partagé. Les statistiques d'organisation (`OrganizationSummary`) sont rendues par
la page elle-même à partir des données réelles de l'organisation active.
