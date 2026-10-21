# Audit de parité Backend / Frontend — 21 octobre 2026

## Verdict

**PARITÉ PARTIELLE.** L’inventaire statique couvre les routes API applicatives déclarées et les parcours React visibles. Deux défauts précis ont été corrigés : une action d’échéance qui appelait une route inexistante, et l’absence de récupération des photos d’unité supprimées logiquement. Le typage, le build et les contrôles statiques passent. Les parcours réels nécessitant MariaDB et une session navigateur ne sont pas certifiés dans ce rapport.

## Portée et méthode

- Branche : `fix/security-session-audit-isolation`, HEAD au début de l’audit : `ce4c9d5`.
- Dépôt propre au démarrage. Les modifications listées plus bas sont celles de cette intervention ; aucun fichier préexistant n’a été restauré ou écrasé.
- Inventaire des routes par `php bin/console debug:router --format=json`, lecture des contrôleurs/services/DTO de chaque domaine et recherche des appels API React, pages et menus.
- 169 déclarations de routes `/api` applicatives et techniques recensées, regroupées ci-dessous. Les routes du profiler Symfony et les routes internes hors `/api` ne sont pas des fonctionnalités utilisateur.
- Une route n’est pas considérée comme une interface : la matrice distingue une intégration trouvée dans le code d’une exécution effectivement démontrée.

## Résumé chiffré honnête

| Mesure | Résultat |
| --- | ---: |
| Déclarations de routes `/api` recensées | 169 |
| Domaines fonctionnels/API regroupés dans la matrice | 12 |
| Écarts frontend prouvés corrigés dans cette intervention | 2 |
| Régressions automatisées de code ajoutées | 1 script, 9 assertions |
| Parcours API en base isolée exécutés ici | 0 |
| Parcours navigateur authentifié exécutés ici | 0 |

Le nombre de routes n’est pas un nombre de fonctionnalités métier. Un décompte « complète » à l’échelle de chaque endpoint serait trompeur sans exécution intégrée ; les statuts ci-dessous décrivent la couverture source et signalent séparément la validation runtime.

## Matrice Backend / Frontend

| Fonctionnalités backend et routes réelles | Interface/consommateur frontend identifié | Rôles / accès établis dans le code | Écart, statut et validation |
| --- | --- | --- | --- |
| Authentification et récupération : `/api/auth/*` (6 routes) | Login, mot de passe oublié/réinitialisation (`features/auth`) | Public uniquement pour login/forgot/reset ; le reste authentifié | **Partielle, runtime non vérifié** : pages et services présents. Aucune session de connexion testée durant cet audit. |
| Organisations, comptes, adhésions et périmètres ville : Identity (30 routes) | Administration organisation, utilisateurs, paramètres, sélecteur d’espace et villes | SUPER_ADMIN pour opérations plateforme explicites ; membres selon rôle d’organisation ; ADMIN_VILLE limité par `UserCity` | **Partielle, runtime non vérifié** : pages/services dédiés présents. Les contrôles serveur restent la source d’autorité ; tests d’isolation BD non exécutés. |
| Patrimoine : villes, parcelles, bâtiments, niveaux, accès, unités (36 routes Property) | Patrimoine ADMIN_IMMOBILIER et ADMIN_VILLE, cartes et formulaires | Rôles et portée filtrés côté API, ADMIN_VILLE par ses villes affectées | **Partielle, runtime non vérifié** : parcours de consultation/gestion présents. La parité des combinaisons de filtres n’a pas été prouvée par navigateur. |
| Locataires, baux, simulation et règles locatives : Tenant (8) + Lease (10) + RentalRuleGovernance (9) = 27 routes | Pages locataires, formulaire de bail avec aperçu ; paramètres organisation des règles, brouillons, publication et délégations | Accès par organisation/ville ; gouvernance et publication selon rôle/délégation serveur | **Partielle, runtime non vérifié** : simulation et paramètres sont raccordés dans le code. Aucun test de publication ou simulation persistante exécuté ici. |
| Échéances et paiements : Rent (5) + Payment (4) = 9 routes | Pages loyers des espaces ADMIN_IMMOBILIER et ADMIN_VILLE, saisie/consultation paiements | Permissions propres à l’action et portée organisation/ville | **Écart corrigé** : l’interface proposait « Marquer en retard » via `POST /v1/rents/{uuid}/overdue`, route absente. Le statut overdue est calculé à la lecture ; l’action invalide a été retirée. Tests BD non exécutés. |
| Dépenses, trésorerie, caisse et taux : Expense (5) + CashBalance (2) + ExchangeRate (6) = 13 routes | Dépenses/trésorerie, paramètres organisation et taux historiques | Selon rôle d’organisation et périmètre ville ; accès mutation contrôlé API | **Partielle, runtime non vérifié** : écrans/services présents. Aucune opération financière ni modification de taux exécutée. L’écran superadmin de taux trouvé dans le dépôt est un composant mock non routé ; les fonctions de taux raccordées sont dans les paramètres organisation. |
| Rapports : 5 routes | Rapports pour les rôles concernés et espace superadmin | Contrôleur de rapport applique le rôle et les scopes | **Partielle, runtime non vérifié** : pages/services identifiés ; les réponses réelles et erreurs SQL ne sont pas validées sans API/base cohérente. |
| Personnel et affectations : 9 routes | Écrans personnel/affectations des rôles opérationnels | Permissions de rôle et ville appliquées backend | **Partielle, runtime non vérifié** : écrans existants. Parité des mutations non exercée en navigateur. |
| Vitrine interne et photos : publication d’unités, gestion de médias, travaux (routes Property/System associées) | Pages vitrine et galerie partagée | Publication autorisée côté serveur ; lecture publique bornée aux unités publiées/libres | **Écart corrigé** : l’API autorisait la liste/restauration des photos supprimées logiquement, sans parcours frontend pour les retrouver. Ajout d’une liste dédiée et d’une action de restauration protégée par le contrôle serveur existant. Vérification DB et navigateur en attente. |
| Vitrine publique : 3 routes `/api/public/organizations/{slug}/showcase`, `/listings`, `/listings/{uuid}` | Aucun appel `/api/public` trouvé dans l’application React du backoffice ; le backoffice expose les réglages et la gestion interne | Public sans jeton ; l’API limite structurellement aux annonces publiées et vacantes, et renvoie 404 hors périmètre | **Absente dans ce SPA / consommateur externe possible**. Il s’agit d’une API de vitrine publique explicitement séparée du backoffice. À décider : héberger une page publique dans ce SPA ou laisser cette API à un site/catalogue externe. Aucun écran n’a été ajouté sans décision sur l’emplacement et le domaine public. |
| Audit, publications, manuels, erreurs client, documents privés et médias : 29 routes System | Audit superadmin, annonces de publication, détail, manuel, médias et documents selon les pages/consommateurs | Contrôles spécifiques, audit non accessible à ADMIN_VILLE | **Partielle, runtime non vérifié** : plusieurs parcours sont présents. L’inventaire statique ne prouve pas chaque combinaison de permissions ni chaque téléchargement. |
| API technique (OpenAPI/docs) et service public de présentation | Documentation API ; pas d’écran métier propre | Accès configuré dans Symfony | **Non applicable** pour un écran CRUD dans le backoffice. |

### Rôles observés

Les rôles applicatifs définis sont `PATRON`, `ADMIN_IMMOBILIER`, `ADMIN_VILLE` et le rôle plateforme `SUPER_ADMIN`. Les écrans suivent principalement les espaces correspondants. Cette matrice ne déduit jamais l’autorisation d’un bouton : l’autorité reste dans `SecurityService` et les contrôleurs/services backend. Une vérification exhaustive des matrices par utilisateur nécessite un environnement de test authentifié et des appels réels.

## Écarts corrigés

### 1. Action « Marquer en retard » non supportée par l’API

Le frontend appelait une route d’écriture inexistante, alors que le backend dérive `overdue` de l’échéance et du solde. L’action, sa confirmation et son service ont été supprimés pour éviter un bouton qui échoue et pour ne pas proposer une mutation financière inexistante.

### 2. Photos retirées impossibles à retrouver depuis la galerie

Le backend conservait la capacité de restaurer une photo, mais la galerie ne listait que les photos actives. Ajout d’une lecture des photos supprimées logiquement, bornée à l’unité, et d’un bouton de restauration. L’accès à cette liste est soumis au contrôle serveur de publication déjà utilisé pour la gestion des photos.

## Vérifications exécutées

| Vérification | Résultat |
| --- | --- |
| `node tests/verify-rent-and-unit-photo-parity.mjs` | Réussi — 9 assertions source/API |
| `node tests/verify-sidebar-roles.ts` | Réussi — 124 contrôles existants |
| `node tests/verify-treasury.ts` | Réussi — 22 contrôles existants |
| `yarn type-check` | Réussi |
| `yarn build` | Réussi |
| `php -l` sur les fichiers PHP modifiés et le script d’intégration ajusté | Réussi |
| `php bin/console debug:router api_units_list_deleted_photos --show-controllers` | Réussi — route GET résolue vers le bon contrôleur |
| `git diff --check` | Réussi |
| Tests d’intégration exigeant MariaDB (`tests/verify-admin-immobilier.php`) | **Non exécutés** : identité/isolation effective de la base non confirmée pour ce tour |
| Scénarios UI authentifiés sur les quatre rôles | **Non exécutés** : aucun navigateur automatisé connecté à une base explicitement isolée n’a été utilisé dans cette intervention |

Les assertions statiques ne remplacent ni les tests d’intégration Symfony/MariaDB ni l’essai navigateur. Aucun paiement, loyer, dépense, règle publiée ou donnée métier n’a été écrit.

## Écarts restants et priorité

1. **P1 — Vitrine publique** : décider si les trois endpoints `/api/public/...` sont destinés à être consommés par un site externe ou s’il faut une route/pages publiques dans le SPA. Le domaine/publication (slug, URL partageable, identité graphique) doit être confirmé avant d’ajouter un site utilisateur.
2. **P1 — validation multi-rôle en runtime** : préparer/identifier une base MariaDB réellement isolée, y appliquer le schéma selon la procédure projet, puis jouer les parcours PATRON → ADMIN_IMMOBILIER délégué → ADMIN_VILLE, y compris refus hors ville/organisation.
3. **P1 — fonctions financières et règles locatives** : exécuter en base isolée les simulations, publication/délégation, génération idempotente, paiement, recalcul d’échéance et trésorerie, avec assertions après rechargement.
4. **P2 — audit des listes et formulaires** : scénarios navigateur pour pagination, filtres, erreurs 4xx, états vides, uploads et restaurations.
5. **P2 — écran superadmin des taux** : le composant `features/super_admin/exchange_rates/pages/ExchangeRatesPage.tsx` s’appuie sur des données initiales fictives et n’est pas dans les routes. Les opérations réelles de taux sont dans Paramètres d’organisation. Ne pas exposer ce mock comme fonctionnalité opérationnelle ; soit le supprimer après confirmation, soit définir une API plateforme avant de le raccorder.

## Procédure proposée pour la suite

1. Confirmer le nom de la base de test et son isolation (ne pas déduire cela de son nom seul) ; contrôler `DATABASE_URL` masqué, le serveur MariaDB, le schéma et le statut des migrations.
2. Appliquer les migrations uniquement sur cette base explicitement dédiée, après revue SQL et confirmation qu’elle ne contient pas de données réelles.
3. Exécuter les scripts autonomes existants, puis les tests d’intégration qui annulent leurs transactions.
4. Ouvrir l’interface avec comptes de test, relever les parcours autorisés/refusés et vérifier les résultats après rechargement.
5. Mettre à jour ce rapport avec les preuves d’exécution ; ne passer à **PARITÉ VALIDÉE** que lorsque les fonctions utilisateur concernées et leurs permissions ont réellement été exercées.

## Fichiers modifiés dans cette intervention

- `assets/react/features/admin_immobilier/loyers/components/LoyersTable.tsx`
- `assets/react/features/admin_immobilier/loyers/hooks/useLoyers.ts`
- `assets/react/features/admin_immobilier/loyers/pages/LoyersPage.tsx`
- `assets/react/features/admin_immobilier/loyers/services/loyersService.ts`
- `assets/react/features/admin_ville/loyers/pages/LoyersPage.tsx`
- `assets/react/features/shared/media/components/PhotoGallery.tsx`
- `assets/react/features/shared/media/services/mediaService.ts`
- `src/Controller/Api/Property/UnitController.php`
- `src/Controller/Api/Rental/RentController.php`
- `src/Repository/Property/UnitPhotoRepository.php`
- `src/Service/Property/PublicShowcaseManagementService.php`
- `src/Service/Property/UnitPhotoService.php`
- `tests/verify-admin-immobilier.php`
- `tests/verify-rent-and-unit-photo-parity.mjs`
- `docs/audits/parite-backend-frontend-2026-10-21.md`

**Aucun commit, push, migration ou changement de données n’a été effectué.**
