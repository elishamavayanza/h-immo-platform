# AGENTS.md — H-Immo (gestion-himmo-app)

Ce fichier s'adresse à tout agent IA (ou développeur) qui modifie ce dépôt.
Il décrit **le code réellement présent**, vérifié dans les fichiers, et non une
architecture souhaitée. Lorsqu'une règle du projet et le code divergent, la
divergence est signalée explicitement en fin de document.

---

## 1. Projet en une phrase

H-Immo est une plateforme de gestion immobilière **multi-organisation** : API
REST Symfony sans état (JWT) + interface React/Vite, adossée à **MariaDB**.

Règle qui prime sur toutes les autres :

> Toute ressource appartenant à une organisation doit être vérifiée côté backend
> afin d'empêcher l'accès à une autre organisation, quel que soit l'identifiant
> fourni par le client.

---

## 2. Stack réelle (vérifiée dans `composer.json`, `composer.lock`, `package.json`, `symfony.lock`)

### Backend

| Élément | Valeur réelle | Fichier source |
| --- | --- | --- |
| Framework | Symfony `7.4.*` (framework-bundle, security-bundle, serializer, validator, uid, mailer, twig-bundle, console, routing, dotenv, asset, rate-limiter) | `composer.json` |
| Langage | PHP `>=8.2` déclaré, **`config.platform.php = 8.4`** → cible réelle **PHP 8.4** | `composer.json` |
| ORM | `doctrine/orm ^3.7.2`, `doctrine/doctrine-bundle ^3.3.2` | `composer.json` |
| Migrations | `doctrine/doctrine-migrations-bundle ^4.0.1` | `composer.json` |
| Auth | `firebase/php-jwt ^7.2` (JWT HS256) + `symfony/security-bundle 7.4.*` | `composer.json` |
| API docs | `nelmio/api-doc-bundle ^5.12.2` (OpenAPI) | `composer.json` |
| PDF | `dompdf/dompdf ^3.1.6` | `composer.json` |
| SGBD | **MariaDB 11.x** — `DATABASE_URL="mysql://…?serverVersion=mariadb-11.8.6&charset=utf8mb4"` | `.env` |
| Autoload | PSR-4 `App\` → `src/`, `App\Tests\` → `tests/` | `composer.json` |
| Scripts Composer | **aucun script de test** ; seuls `auto-scripts` (`cache:clear`, `assets:install`) | `composer.json` |

**Interdictions de pile :**

- Ne propose ni n'introduis **PostgreSQL** : c'est explicitement exclu (le projet
  a migré de PostgreSQL vers MariaDB ; `doctrine.yaml` documente pourquoi
  `identity_generation_preferences` a été retiré).
- N'introduis pas de package sans besoin réel : chaque ajout doit justifier
  `composer.json` **et** `symfony.lock`.

### Frontend

| Élément | Valeur réelle | Fichier source |
| --- | --- | --- |
| UI | React `19` / `react-dom 19` | `package.json` |
| Langage | TypeScript (`^7.0.2`), `tsconfig.json` `strict: true`, `noEmit`, `jsx: react-jsx`, cible `ES2022` | `package.json`, `tsconfig.json` |
| Bundler | Vite `^8.3.0` + `@vitejs/plugin-react` | `package.json` |
| Package manager | **Yarn 4.18.0** (`.yarn/releases/yarn-4.18.0.cjs`, `packageManager`, `nodeLinker: node-modules`) | `package.json`, `.yarnrc.yml` |
| Scripts | `yarn dev` (ou `dev-server`), `yarn build`, `yarn preview`, `yarn type-check` | `package.json` |

**Emplacement réel :** il n'existe **pas** de dossier `frontend/`. Tout le front
est dans `assets/app/` :

```text
assets/app/
├── index.html          # page Vite (appType: 'spa')
├── main.tsx            # point d'entrée React
├── ResetPasswordPage.tsx
├── password-form.ts    # logique pure testable hors navigateur
├── styles.css
└── vite-env.d.ts
```

`vite.config.ts` : `root: 'assets/app'`, `outDir: '../../public/build'`,
`appType: 'spa'`, port de dev `5173` (`strictPort`), proxy `/api` →
`http://127.0.0.1:8000` (surchargeable par `API_URL`).

> Le proxy Vite existe parce que **le projet n'a pas de bundle CORS** côté
> Symfony. Ne « corrige » pas cela en ajoutant un bundle CORS sans demande
> explicite : en production le front et l'API partagent un même nom de domaine.

---

## 3. Arborescence backend réelle

```text
src/
├── Command/                 # app:super-admin:create, app:rents:generate
├── Controller/Api/          # 19 contrôleurs, par package métier
├── Doctrine/Dql/            # DateFormat (fonction DQL personnalisée)
├── Dto/                     # 67 fichiers : Request/ + Response/ par package
├── Entity/                  # Identity, Property, Rental, Expense, Staff, System, Shared
├── Enum/                    # 14 enums backed
├── EventListener/           # ApiExceptionListener, LastLoginSubscriber
├── Exception/               # AccessDeniedException, UnauthenticatedException
├── Mapper/                  # entité → DTO de réponse, par package
├── Repository/              # requêtes DQL/QueryBuilder par package
├── Security/                # SecurityService, ApiTokenAuthenticator, SecurityAction…
├── Service/                 # Expense, Identity, Property, Rental, Report, Staff, System
├── Trait/                   # FeedbackTrait
└── Kernel.php
```

Les sous-dossiers `src/Utils/` et `src/Validator/` mentionnés dans
`CONTRIBUTING.md` **n'existent pas**. N'y crée pas de code « pour respecter la
doc » : la doc est en retard sur le code, pas l'inverse.

`config/services.yaml` enregistre `App\` en autowire/autoconfigure **sauf**
`src/Dto/Response/Report/` (exclu explicitement du conteneur).

---

## 4. Modèle de données réel

### Hiérarchie des classes de base (vérifiée)

```text
BaseEntity                       (id BIGINT unsigned AUTO_INCREMENT + uuid, non exposés)
├── TimestampedEntity            (+ createdAt, updatedAt)
│   └── SoftDeletableEntity      (+ deletedAt)
└── CreatedOnlyEntity            (+ createdAt seul : immuables)
```

Règles :

- Ce sont des `#[ORM\MappedSuperclass]`, **pas** de l'héritage d'entités Doctrine
  (`SINGLE_TABLE` / `JOINED` est interdit : chaque table est indépendante).
- `BaseEntity::__construct()` génère `Uuid::v4()` une seule fois. **Ne jamais
  régénérer un `uuid` existant** : c'est l'identifiant public déjà exposé.
- Aucune logique technique ou métier (pas de `PreUpdate`, pas de calcul de
  statut persistant automatique) dans les entités. Si `updatedAt` doit changer,
  appelez `setUpdatedAt()` explicitement — **attention : à ce jour aucun appelant
  n'existe dans `src/`** (voir §13, D3).
- Le filtrage automatique des lignes `deletedAt IS NOT NULL` **n'est pas codé** :
  chaque requête doit exclure explicitement les suppressions logiques.
- `deletedAt` **n'existe que** sur `SoftDeletableEntity`. `Expense`, `OrganizationUser`,
  `Payment`, `Rent`, `UserCity`, `PasswordResetToken`, `RevokedToken` et `AuditLog`
  en sont dépourvus : un `r.deletedAt IS NULL` sur ces tables est une erreur DQL
  « no field or association named deletedAt », donc un 500 à l'exécution.

### Entités réellement présentes (18 tables)

| Package | Entités (classe de base) |
| --- | --- |
| Identity | `User` (SoftDeletable), `Organization` (SoftDeletable), `OrganizationUser` (Timestamped), `UserCity` (CreatedOnly), `PasswordResetToken` (CreatedOnly), `RevokedToken` (CreatedOnly) |
| Property | `City`, `Parcel`, `Building`, `Unit` (tous SoftDeletable) |
| Rental | `Tenant`, `Lease` (SoftDeletable), `Rent`, `Payment` (Timestamped) |
| Expense | `Expense` (Timestamped) |
| Staff | `Worker`, `WorkerAssignment` (SoftDeletable) |
| System | `AuditLog` (CreatedOnly) |

Il n'existe **pas** d'entité `Owner`, `Property`, `Contract`, `Maintenance`,
`Document` ni `Notification` dans le code, malgré leur mention dans
`README.md` et `CONTRIBUTING.md`. Ne les documente pas comme existantes et ne
crée pas de migration pour elles sans demande explicite.

### Chaîne de rattachement (source de vérité pour l'isolation)

```text
Organization
└── City
    └── Parcel
        └── Building
            └── Unit
                └── Lease ── tenant_id ──► Tenant   (Tenant est lié au Lease, pas à Unit)
                    └── Rent
                        └── Payment
```

- `User` est **N–N** avec `Organization` via `OrganizationUser` (qui porte le
  rôle) et **N–N** avec `City` via `UserCity` (périmètre `ADMIN_VILLE`).
- Toute table métier porte `organization_id` **directement** (y compris
  `Tenant`, `Lease`, `Expense`, `Worker`, `Payment`, `Rent`) : le contrôle
  d'accès doit s'appuyer sur ce champ, pas seulement sur la navigabilité
  `Unit → City → Organization`.

---

## 5. Isolation multi-organisation (règle critique)

`src/Security/SecurityService.php` est le **point d'entrée unique** de toutes les
décisions d'autorisation. Aucun service métier ne doit réimplémenter un contrôle
d'appartenance à une `Organization` ou à une `City` : il délègue.

### Ce qu'un agent doit faire

1. Résoudre l'`Organization` visée **depuis la ressource** (jamais depuis le
   corps de la requête).
2. Appeler un contrôle de `SecurityService` avec un `SecurityAction` explicite.
   L'API disponible est vérifiée dans `src/Security/SecurityService.php` :
   `checkOrganizationAccess`, `checkCityAccess`, `checkUnitAccess`,
   `checkLeaseAccess`, `checkRentAccess`, `checkPaymentAccess`, `checkExpenseAccess`,
   `checkWorkerAccess`, `checkWorkerAssignmentAccess`, `checkUserAccess`,
   `checkAuditLogAccess`, `checkReportAccess`, `getOrganizationRole`, etc.
   Chaque ressource métier dispose de son `checkXxxAccess()`.
3. Refuser en **403** si l'appelant n'est pas dans le périmètre. Un 404 est
   préférable lorsqu'il évite de révéler l'existence d'une ressource
   appartenant à un autre tenant (énumération de UUID).
4. Pour toute **liste**, borner la requête par `organization_id` **et** par le
   périmètre `UserCity` d'un `ADMIN_VILLE` — un filtre d'affichage postérieur ne
   suffit pas, l'agrégat doit être borné en SQL/DQL.

### Pièges explicites

- `isPatron()`, `isAdminVille()`, `isAdminImmobilier()` parcourent **toutes** les
  organisations de l'utilisateur. Elles servent de prédicat global de
  présentation, **jamais** à autoriser une action sur une ressource précise :
  pour cela, utiliser `getOrganizationRole($user, $organization)` /
  `hasOrganizationRole()`.
- Un `SUPER_ADMIN` n'hérite pas des droits métier. Son accès en lecture à des
  données d'organisation est un choix explicite et tracé via
  `requirePlatformRole()`.
- Un `organizationId` présent dans un DTO de requête est une **intention**, pas
  une autorisation : il doit être recoupé avec l'autorisation réelle.
- Modification d'un UUID, d'un paramètre d'URL, d'un paramètre GET ou du body
  JSON ne doit jamais changer le périmètre d'accès obtenu.

---

## 6. Rôles (valeurs exactes des enums)

| Niveau | Enum | Valeurs |
| --- | --- | --- |
| Plateforme | `src/Enum/PlatformRole.php` | `SUPER_ADMIN` (`super_admin`) |
| Organisation | `src/Enum/OrganizationRole.php` | `PATRON` (`patron`), `ADMIN_IMMOBILIER` (`admin_immobilier`), `ADMIN_VILLE` (`admin_ville`) |

- `CONTRIBUTING.md` mentionne `ORGANIZATION_OWNER` et `CITY_ADMIN` : **ces
  valeurs n'existent pas**. Le vocabulaire réel est celui du tableau ci-dessus.
- `ADMIN_VILLE` n'a un accès borné que si des lignes `UserCity` existent pour lui.
  Une liste de villes autorisées doit passer par
  `CityRepository::findAssignedToUserInOrganization()` (ou l'équivalent côté
  `SecurityService`), pas par toutes les villes de l'organisation.
- Les permissions d'action sont modélisées par `src/Security/SecurityAction`
  (`VIEW`, `CREATE`, `UPDATE`, `DELETE`, `MANAGE_USERS`, `ASSIGN_USER_CITY`, …).
  Privilégier ce vocabulaire à un `hasRole()` générique.

Autres enums métier à utiliser plutôt que des chaînes : `LeaseStatus`
(`draft`, `active`, `expired`, `terminated`, `cancelled`), `RentStatus`
(`pending`, `partially_paid`, `paid`, `overdue`), `Currency` (`USD`, `CDF`),
`OrganizationStatus`, `CityStatus`, `BuildingType`, `UnitType`, `TenantType`,
`PaymentMethod`, `ExpenseCategory`, `WorkerRole`, `CityAccessScope`.

---

## 7. Identifiants et API REST

- `id` = `BIGINT UNSIGNED` interne : usage base de données et relations uniquement.
- `uuid` = `BINARY(16)` (mapping Doctrine `uuid`), **unique**, généré à la
  construction : c'est l'identifiant public.
- Toute route d'accès à une ressource doit utiliser `{uuid}`. N'expose jamais
  `id` dans un DTO de réponse, une URL ou un message d'erreur.
- Les UUID se comparent en base via `src/Repository/UuidParameterTrait`
  (`bindableUuid()`), pas par conversion manuelle en chaîne.
- L'API est versionnée : les contrôleurs Rental/System utilisent `/api/v1/…`
  (voir `#[Route('/api/v1/payments')]`) ; certains contrôleurs Identity/Property
  sont sur `/api/…`. **Vérifie la route existante avant d'en créer une nouvelle**,
  et ne renumérote pas une famille d'Endpoints existante.
- Codes HTTP usuels : 200 lecture, 201 création, 400 requête invalide, 401 non
  authentifié, 403 refus, 404 introuvable, 409 conflit métier, 422 validation.

### Enveloppe de réponse : `Feedback`

`src/Dto/Feedback.php` est l'enveloppe standard de **toute** réponse
(`flush`, `flushDescription`, `status`, `errors`, `warnings`, `data`). Le
contrôleur fait :

```php
$feedback = $this->service->uneAction($request);
return $this->json($feedback, $feedback->getStatus());
```

Introduire un autre format de réponse pour un endpoint du même service casse la
cohérence de l'API et le schéma OpenAPI.

### Mapping HTTP

- Corps JSON / validation → `#[MapRequestPayload]`.
- Filtres de query string (pagination, tri, filtres) → `#[MapQueryString]`.

> **Écart constaté (D1)** : plusieurs endpoints `GET` de liste ajoutés
> récemment (`PaymentController::list`, `ExpenseController`, `WorkerController`,
> `WorkerAssignmentController`, `LeaseController::list`, `RentController`) utilisent
> `#[MapRequestPayload]` sur un DTO de filtre. Les neuf contrôleurs Identity /
> Property / Report utilisent correctement `#[MapQueryString]`. Vérifie le
> mapping avant d'ajouter un endpoint GET, et ne recopie pas le pattern fautif.

Les DTOs de filtre incluent `page`, `limit` (borné), `sortBy`, `sortOrder` :
**tout `sortBy` doit être validé par whitelist** côté repository, jamais
interpolé.

---

## 8. Conventions Symfony / PHP

- `declare(strict_types=1);` en tête de **tous** les fichiers PHP.
- Attributs PHP (`#[Route]`, `#[OA\…]`, `#[ORM\Column]`, `#[AsEventListener]`,
  `#[MapRequestPayload]`), pas d'annotations `@`.
- Imports triés, un usage par ligne, namespaces `App\Controller\Api\<Package>`,
  `App\Service\<Package>`, `App\Dto\Request\<Package>`, etc.
- Comments de docblock en français, expliquant **pourquoi** (contraintes métier,
  pièges), pas **quoi**.
- Contrôleurs fins : aucun accès à la base ni déduction de tenant dans un
  contrôleur. Le contrôleur décode, délègue, sérialise.
- Logique métier → `src/Service/<Package>` (services `final readonly` quand
  l'état est immuable).
- Requêtes SQL complexes, jointures, filtres de tenant, agrégats, pagination →
  `src/Repository/<Package>` via `QueryBuilder` / DQL. Ne construisez pas de DQL
  en concaténant une variable utilisateur.
- Pagination : `src/Repository/PaginatedResultTrait` existe pour normaliser
  `{items, total, page, pages}`.
- **Un type non importé ne casse pas au chargement.** En PHP, un nom de classe
  absent d'un `use` se résout dans le namespace du fichier : une méthode
  typée `?PaymentFilterDto $filter` sans le `use App\Dto\Request\Rental\…`
  se déclare sans erreur, puis lève un `TypeError` **à l'appel** — donc un 500
  sur l'endpoint, pas une erreur visible au lint ni au chargement du conteneur.
  Le cas s'est produit sur les trois endpoints de liste P0-7 ; `php -l` et
  `lint:container` ne le-see pas. Couvrir tout nouveau endpoint par un appel
  réel (`tests/verify-p0-7-list-endpoints.php` en donne le modèle).
- Entité → réponse : `src/Mapper/<Package>` (séparation API / Doctrine). Ne
  sérialisez jamais une entité Doctrine directement.
- Gestion d'erreurs : `src/EventListener/ApiExceptionListener.php` convertit les
  exceptions en JSON `HttpErrorResponsePayload` pour les paths `/api`, masque
  les détails internes hors debug, journalise via `LoggerInterface`, et mappe
  violations de contraintes uniques, `EntityNotFoundException`,
  `OptimisticLockException`, `ValidationFailedException`, 401/403/429.
  Lever une exception applicative (`App\Exception\…`) est le bon réflexe ; ne
  renvoyez pas une 500 pour un cas métier attendu.
- Commandes CLI : `src/Command/` (arguments nommés, sortie lisible, pas de
  données sensibles en sortie).

### Dates, heures et fuseaux

Source unique de vérité : `src/Service/System/DateTimeService.php`. Règles :

- **Toute date est un `DateTimeImmutable`.** Aucun `DateTime` mutable dans
  `src/`. Les colonnes restent `DATE_IMMUTABLE` (dates métier) et
  `datetime_immutable` (horodatages).
- **Stockage et calculs en UTC**, via `DateTimeService::now()`, `today()`,
  `startOfCurrentYear()`, `endOfCurrentYear()`. Chaque construction y est
  explicitement épinglée sur `DateTimeService::STORAGE_TIMEZONE`, donc un
  changement de `date.timezone` du serveur ne déplace plus ni échéances, ni
  bornes de rapports, ni expirations de jetons.
- **Conversion de fuseau = présentation uniquement**, via `toTimezone()` /
  `format()`. `format()` conserve le fuseau de stockage par défaut : la sortie
  est identique à l'ancien `$date->format(...)`, donc aucun changement de
  contrat sur les réponses API. `PRESENTATION_TIMEZONE`
  (`Africa/Kinshasa`) n'est appliquée que si l'appelant le demande
  explicitement.
- **Parsing d'une entrée client** : passer par `parseDate()` / `parseDateTime()`,
  qui renvoient `null` sur une valeur invalide au lieu de lever une exception
  (un `new \DateTimeImmutable($raw)` sur `?from=` malformé renvoyait 500).
  Les entités ne font pas exception : `Rent::isOverdue()` et
  `PasswordResetToken::isExpired()` acceptent une référence `$now` optionnelle,
  que la couche service fournit depuis le service.
- **Aucun `date()`, `time()` ou `strtotime()`** dans `src/`, et aucun
  `new \DateTimeImmutable(...)` hors `DateTimeService` et hors constructeurs
  d'entités : une entité est instanciée par Doctrine et ne peut pas recevoir
  un service ; son horodatage de construction reste donc `new
  \DateTimeImmutable()`, les entités n'étant le seul endroit qui ne fait qu'un
  « maintenant » au moment de l'insertion.
- `'last day of December this year'` résout à **minuit**, pas à 23:59:59 :
  une requête « année en cours » qui doit inclure le 31 décembre combine
  `endOfCurrentYear()` avec un comparateur `<` sur `startOfCurrentYear()` de
  l'année suivante, ou explicite sa borne haute.

Vérification : `php tests/verify-datetime-service.php` (25 contrôles, sans base
ni conteneur).

### Ce qu'il ne faut pas introduire

Pas de CQRS, pas d'Event Sourcing, pas d'Event Bus, pas de repository générique
ou `AbstractRepository` maison, pas de couche de factory, pas de DDD tactique
(« aggregate », « value object ») imposé : **le projet est un CRUD métier
 classique** `Controller → Service → Repository → Entity`. N'ajoute une couche
 que si un besoin concret la justifie, et explique-le.

---

## 9. Validation des entrées

- Contraintes de validation sur les DTO via `#[Assert\…]` (`symfony/validator`).
- Le listener traduit `ValidationFailedException` en 422 avec le détail par champ.
- Ne jamais faire confiance à un enum : valider que la valeur fournie est bien un
  cas de l'enum (un `enumType: Currency::class` invalide doit être refusé, pas
  tronqué).
- Ne jamais « sanitizer » / « nettoyer » une donnée pour la faire passer : rejeter avec une
  erreur explicite.
- Filtrage des médias/uploads : `src/Service/System/FileUploadService.php` et
  `MediaService` gèrent le stockage et l'**autorisation** ; la protection
  anti-traversée de chemin y est obligatoire. Ne construisez jamais un chemin
  disque à partir d'une entrée utilisateur non validée.

---

## 10. Base de données et migrations

**SGBD unique : MariaDB 11.x.** `DATABASE_URL` porte
`serverVersion=mariadb-11.8.6` ; `compose.yaml` / `compose.override.yaml` lancent
MariaDB. Toute incohérence entre `.env`, `doctrine.yaml`, Compose et les
migrations doit être signalée, pas corrigée « en douce ».

Éléments du modèle à respecter :

- Clé primaire : `BIGINT UNSIGNED AUTO_INCREMENT` (généré par `IDENTITY`).
- UUID : `BINARY(16)` `UNIQUE`.
- Entiers montants : `NUMERIC(12,2)` via `Types::DECIMAL` (jamais `FLOAT`/`DOUBLE`).
- Dates : `DATE` pour dates métier, `DATETIME` pour horodatage.
- Texte long : `LONGTEXT` ; stockage de documents : `JSON` (`audit_log`).
- `DEFAULT CHARACTER SET utf8mb4` sur chaque table.
- Index systématiques sur les colonnes de FK et sur les colonnes filtrées
  (`idx_expense_city_date`, `idx_assignment_worker`, …).
- Contraintes d'unicité métier : `uniq_lease_org_reference`, `uniq_rent_lease_period`,
  `uniq_user_city`, `uniq_org_user`, `uniq_active_lease_per_unit`… — ces index
  portent une règle métier, ne les supprime pas pour « simplifier ».

Migrations (`migrations/`, namespace `DoctrineMigrations`, répertoire déclaré dans
`config/packages/doctrine_migrations.yaml`) :

- Elles contiennent du **SQL brut compatible MariaDB**, pas seulement du DDL
  abstrait : toute migration ajoutée doit être relue pour sa compatibilité
  MariaDB (pas de `SERIAL`, `JSONB`, `SERIAL`, `TIMESTAMPTZ`).
- Toute modification du schéma exige une migration, un `up()` **et** un `down()`,
  et la mise à jour de `tests/verify-mariadb.php` si le nombre de tables change.
- Après changement d'entité : `php bin/console doctrine:schema:validate` puis
  `php bin/console doctrine:migrations:diff --formatted` (ce dernier dans un
  environnement à jour, pour ne pas englober une dérive étrangère).
- Ne modifie **jamais** une migration déjà appliquée en base.

---

## 11. Sessions

Le projet **n'a pas et ne doit pas avoir de table `sessions` en base** (la
baseline crée 18 tables, aucune n'est `sessions`).

- Le pare-feu `main` est `stateless: true` : authentification par jeton Bearer
  uniquement, aucun cookie de session émis.
- `config/packages/framework.yaml` conserve un bloc `session` (handler
  `session.handler.native_file`, `save_path` sous `var/sessions/<env>`) **comme
  configuration de repli** et pour éviter que PHP n'écrive dans
  `/var/lib/php/sessions` absent en conteneur. Ce n'est pas une session en base :
  ne le « nettoie » pas et ne le convertis pas en table sans demande explicite.
- N'introduis ni `framework.session.handler_id: pdo` ni aucune table de sessions.

---

## 12. Sécurité (backend = autorité)

- **Le frontend React n'est jamais une frontière de sécurité.** Masquer un bouton
  ne protège rien ; l'API doit refuser l'opération.
- Authentification : `POST /api/auth/login` (`json_login`, champs `email` /
  `password`) renvoie `accessToken`, `tokenType`, `expiresIn`. JWT HS256 signé
  par `firebase/php-jwt` via `App\Service\Identity\TokenManager`.
- `JWT_SECRET` : **jamais dans un fichier versionné** ; 32 octets hexadécimaux
  minimum, sinon `TokenManager` refuse de démarrer. `JWT_TTL` ≥ 60 s (1 h par
  défaut). Régénérer : `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`.
- Révocation : `POST /api/auth/logout` écrit dans `revoked_token`
  (`RevokedTokenRepository`) ; `ApiTokenAuthenticator` recharge le compte en base
  à chaque requête, donc une désactivation est immédiate.
- Le contenu du jeton (revendications `roles`, `cityScope`, `organizations`) sert
  au **confort du client**, jamais à l'autorisation : le compte et les rôles sont
  relus en base.
- `AccountStateUserChecker` refuse un compte désactivé ou supprimé logiquement,
  pour les deux authentificateurs du pare-feu.
- Limitation des tentatives : `login_throttling` (5 tentatives / 15 min) → 429.
- Messages d'authentification génériques : ne jamais distinguer « e-mail inconnu »
  de « mot de passe faux ». `PasswordResetService` répond 200 avec un message
  identique dans les deux cas.
- `access_control` : `PUBLIC_ACCESS` uniquement pour `^/api/auth/login$`,
  `^/api/doc(s)?$`, `^/api/auth/forgot-password$`, `^/api/auth/reset-password$`.
  Tout le reste exige `IS_AUTHENTICATED_FULLY`. **N'ajoute pas de route publique
  sans justification explicite** (une routeForgot/Reset publique le justifie
  dans `security.yaml`).
- Rate limiting applicatif : `symfony/rate-limiter` est installé ; s'en servir
  pour les endpoints sensibles plutôt que de compter à la main.
- Ne journalise jamais de mot de passe, de token, de `JWT_SECRET` ni de donnée
  personnelle dans les logs ou l'audit.

---

## 13. Argent et intégrité financière

- Aucun `float` pour un montant, ni en PHP, ni en base, ni en TypeScript.
  Stockage : `Types::DECIMAL, precision: 12, scale: 2` (→ `NUMERIC(12,2)`).
- Devises supportées : `src/Enum/Currency.php` → `USD`, `CDF`. **Ne jamais additionner
  ni comparer des montants de devises différentes** ; regrouper par devise.
- Un montant financier conserve, autant que possible : montant, devise, date,
  référence, provenance (catégorie / bail / échéance), `createdBy` et
  `organization_id`.
- Un `Payment` est une **trace comptable** : l'API ne le modifie ni ne le
  supprime. Une annulation passe par une contre-écriture tracée, jamais par un
  `DELETE`.
- Un `Rent` est rattaché à un `Lease` + `period` avec unicité
  `uniq_rent_lease_period` : la génération d'échéances doit être idempotente
  (`app:rents:generate`).
- Statut d'un loyer : persistant `pending|partially_paid|paid` ; `overdue` est un
  **état dérivé** du montant payé et de la date d'échéance, pas une valeur à
  écrire à la main. Un statut de bail n'est modifiable que par transition
  (`activate` / `terminate` / `cancel`), jamais par écriture directe.

> **Écart constaté (D2)** : `src/Entity/Rental/Rent.php` caste le montant payé en
> `(float)` pour comparer au montant dû (lignes ~161-162 et ~213-214). C'est
> contraire à la règle « pas de float » et fragile sur les centimes. À corriger
> avec `bccomp`/`bcmul` à un moment donné ; ne pas propager ce pattern ailleurs.

---

## 14. Audit

- `App\Service\System\AuditLogService::log(action, entityType, entityId, organization, user, oldValues, newValues)`.
- `AuditLog` étend `CreatedOnlyEntity` : un journal n'est ni modifié ni supprimé.
- Opérations qui doivent être auditées : création / modification / suspension
  d'organisation, création et modification d'utilisateur, changement de rôle ou
  de périmètre `UserCity`, création et transitions de bail, génération
  d'échéances, enregistrement et annulation de paiement, création et annulation
  de dépense, création d'affectation de personnel, suppressions logiques,
  connexion et déconnexion sensibles, opérations d'administration plateforme.
- `oldValues` / `newValues` doivent être des **tableaux sérialisables** et
  expurgés de secrets (mots de passe, tokens).
- La lecture du journal est bornée à l'Organization du lecteur
  (`AUDIT_LOG_ORGANIZATION_ROLES` = `PATRON`, `ADMIN_IMMOBILIER` ; un
  `ADMIN_VILLE` en est exclu).
- `AuditLogController` et `AuditLogResponse` sont déjà en place : n'expose pas
  l'audit à un rôle non listé.

---

## 15. Tests

**État réel : PHPUnit n'est pas installé** (`composer.json` et `symfony.lock`
sans `phpunit`, `vendor/bin` sans `phpunit`, `composer.json` sans `require-dev`).
`CONTRIBUTING.md` §15 demande pourtant `php bin/phpunit` — commande inexistante.

La vérification repose sur des **scripts PHP autonomes** dans `tests/`, exécutés
en `dev` sur la base de développement, chacun annulant sa transaction et
retournant `0` / `1` :

```bash
php tests/verify-mariadb.php            # SGBD, charset, tables, migrations, DATE_FORMAT
php tests/verify-tenant-isolation.php   # périmètre repositories/services/audit
php tests/verify-http-mapping.php       # verbes HTTP, statuts, routes
php tests/verify-auth.php               # jeton, révocation, désactivation, throttling
php tests/verify-api-token.php          # signature HS256, revendications, altérations
php tests/verify-api-doc.php            # génération OpenAPI
php tests/verify-super-admin.php        # bootstrap SUPER_ADMIN
php tests/verify-password-reset.php     # flux mot de passe oublié
php tests/verify-p0-security.php        # rapports, dépenses, médias, statuts
php tests/verify-p0-7-list-endpoints.php # endpoints de liste rentals (200, pas de fuite)
php tests/check-injected-dependencies.php # dépendances $this-> injectées (statique)
node tests/verify-reset-password-form.ts # logique pure du formulaire React
```

Règles :

- **Tout changement d'autorisation, d'isolation, de paiement ou de calcul
  financier doit étendre un de ces scripts** (ou en ajouter un) plutôt que d'être
  livré sans vérification.
- Un test d'isolation doit créer **deux organisations concurrentes** et prouver
  que l'appelant de A ne lit/écrit rien de B.
- Un script de test ne doit jamais laisser de données : transaction annulée en
  sortie, y compris les lignes d'audit et les tokens révoqués.
- Contrôles d'intendance à passer avant de conclure :

```bash
php bin/console lint:container
php bin/console doctrine:schema:validate
yarn type-check
```

- Migration touchée → `php tests/verify-mariadb.php` (il vérifie l'absence de
  version de migration orpheline).
- Si `bin/phpunit` n'existe pas, ne pas l'invoquer et ne pas prétendre l'avoir
  exécuté.

---

## 16. React / TypeScript

- Composants **fonctionnels** uniquement, hooks, composants réutilisables.
- `strict: true` : **pas de `any`** sans justification écrite dans un commentaire
  adjacent. Préfère `unknown` + narrowing.
- Séparer UI et logique : la logique testable sans navigateur vit dans un module
  pur (exemple : `assets/app/password-form.ts`, vérifié par un script Node).
- Toute réponse d'API consommée par le front a un type TypeScript explicite et
  cohérent avec le schéma OpenAPI ; ne pas redéfinir les règles de métier en TS.
- États de chargement et d'erreur gérés explicitement sur chaque appel réseau.
- Le client ne doit jamais envoyer d'`organizationId` en espérant que le backend
  s'y fie : l'API recalcule le périmètre.
- `yarn build` sort dans `public/build/` (ignoré par Git). `yarn type-check` est
  le contrôle obligatoire avant livraison.
- Pas de dépendance React ajoutée sans justification : le front est aujourd'hui
  minimal (React + ReactDOM seuls en `dependencies`).

---

## 17. Git

- **Interdits sans autorisation explicite de l'utilisateur :**

```text
git reset --hard
git clean -fd
git checkout -- .
git restore .
git push --force / git push -f
```

- Ne supprime ni n'écrase les modifications existantes de l'utilisateur : si
  `git status` montre des fichiers modifiés hors de ta tâche, laisse-les intacts
  et signale-les.
- Branches : `main` (stable), `develop` (intégration), `feature/*`, `fix/*`,
  `hotfix/*`, `refactor/*`, `docs/*`, `test/*`. Ne commit jamais sur `main`, ne
  push jamais directement sur `develop`.
- Un commit = un sujet. Le dépôt est actuellement sur
  `fix/security-session-audit-isolation` : reste dessus sauf demande contraire.
- Format de message en usage dans ce dépôt (premier mot en majuscule, sinon) :

```text
[TAG] Action #Issue : Description courte
```

Tags : `AUTH`, `USER`, `ORG`, `CITY`, `PROPERTY`, `OWNER`, `TENANT`, `CONTRACT`,
`RENT`, `PAYMENT`, `EXPENSE`, `MAINTENANCE`, `DOCUMENT`, `REPORT`,
`NOTIFICATION`, `API`, `DB`, `SECURITY`, `AUDIT`, `TEST`, `UI`, `DOC`.

- Secrets : ne versionne **jamais** de mot de passe, clé API, clé privée, token,
  `JWT_SECRET`, identifiant de base ou configuration de production. `.env.local`
  et `.env.*.local` sont ignorés par Git ; le secret vit là, ou dans une variable
  d'environnement du système.
- Généré et ignoré : `vendor/`, `node_modules/`, `var/`, `public/build/`,
  `public/uploads/`, caches `.phpunit.cache`, `.yarn/cache`, `.local-bin/`.
- Ne pas ajouter de `*.bak`, `*.vpp.bak_*`, `.vpx`, `.vpd`, exports Visual
  Paradigm : le seul modèle à suivre est `docs/uml/H-Immo.vpp`.

---

## 18. Méthode de travail obligatoire de l'agent

```text
Analyser
   ↓
Comprendre
   ↓
Identifier les impacts
   ↓
Proposer la solution
   ↓
Modifier uniquement ce qui est nécessaire
   ↓
Tester
   ↓
Vérifier les régressions
   ↓
Documenter
```

- **Analyser avant d'écrire** : lire le service, le repository, l'entité et le
  contrôleur concernés ; ne pas supposer une structure.
- **Identifier les impacts** : qui est appelé, quelles tables sont lues, quels
  scripts de vérification couvrent la zone, ce que l'API expose.
- **Proposer avant de faire** si le changement touche le schéma, l'authentification,
  l'isolation ou une convention : une ligne de contexte vaut mieux qu'un
  patch surprise.
- **Modifier le minimum nécessaire** : pas de refonte opportuniste, pas de
  renommage de masse, pas de « nettoyage » hors du périmètre demandé.
- **Ne pas changer l'architecture** parce qu'une autre approche te paraît plus
  élégante. L'agent s'adapte au projet, pas l'inverse.
- **Tester** avec les scripts de `tests/` (et `lint:container`,
  `doctrine:schema:validate`, `yarn type-check` selon la zone).
- **Vérifier les régressions** : en priorité la sécurité, l'isolation
  multi-organisation, les calculs financiers et la sérialisation des réponses.
- **Documenter** : OpenAPI à jour pour tout endpoint, commentaire de code
  expliquant les choix non évidents, mise à jour de ce fichier si une règle du
  dépôt change.

Si une étape ne peut pas être réalisée (script absent, base indisponible,
information non confirmée), **le dire explicitement** plutôt que de déclarer
succès.

---

## 19. Écarts constatés entre documentation et code (ne pas corriger d'office)

Ces points sont à signaler, pas à réparer silencieusement. Ils sont listés pour
que l'agent ne construise pas sur une prémisse fausse.

- **D1 — Mapping des filtres GET.** Trois endpoints de liste
  (`ExpenseController`, `WorkerController`, `WorkerAssignmentController`)
  utilisent encore `#[MapRequestPayload]` pour un DTO de query string au lieu
  de `#[MapQueryString]` : sur un GET sans corps, Symfony répond **415** et
  l'endpoint est inatteignable. Corrigés dans `PaymentController::list`,
  `LeaseController::list` et `RentController::listOverdue` (vérifié par
  `tests/verify-p0-7-list-endpoints.php`). Les contrôles
  Identity/Property/Report sont corrects.
- **D2 — `float` dans `Rent`.** `Rent.php` compare le montant payé en `(float)`
  (§13).
- **D3 — `updatedAt` jamais mis à jour.** `TimestampedEntity` expose
  `setUpdatedAt()` mais aucun appelant n'existe dans `src/` ; le commentaire de
  la classe évoque un listener externe qui n'a pas été écrit (`src/EventListener/`
  ne contient que `ApiExceptionListener` et `LastLoginSubscriber`).
- **D4 — `php bin/phpunit` demandé mais inexistant** (§15) : `CONTRIBUTING.md` §15
  et §4 (« PHPUnit ») décrivent un outillage non installé.
- **D5 — Vocabulaire de rôles divergent.** `CONTRIBUTING.md` §3 utilise
  `ORGANIZATION_OWNER` et `CITY_ADMIN`, absents des enums ; le vocabulaire réel
  est `PATRON` / `ADMIN_IMMOBILIER` / `ADMIN_VILLE` + `PlatformRole::SUPER_ADMIN`.
- **D6 — Entités annoncées mais inexistantes.** `Owner`, `Property`, `Contract`,
  `Maintenance`, `Document`, `Notification` sont cités dans `README.md` /
  `CONTRIBUTING.md` sans entité, contrôleur, service ni migration correspondants.
- **D7 — Dossiers annoncés mais absents.** `src/Utils/`, `src/Validator/`, et
  `frontend/` n'existent pas (le front est dans `assets/app/`).
- **D8 — `APP_SECRET` versionné.** `.env` est versionné et contient un
  `APP_SECRET` généré (non vide). C'est un secret de développement dans un
  fichier suivi : il est rotationné, jamais réutilisé en production. Les
  secrets réels (`JWT_SECRET`, mots de passe) restent, eux, dans
  `.env.local`, qui est ignoré par Git. Toute rotation doit être signalée.
- **D9 — Contrainte d'unicité de bail actif annulée.** La migration
  `Version20260928133550` crée `active_unit_id` (colonne générée) + index unique
  `uniq_active_lease_per_unit`, mais la migration auto-générée
  `Version20260928135434` fait `DROP INDEX uniq_active_lease_per_unit` et
  `DROP active_unit_id` pour ajouter `Lease.terms`. **La garantie « un seul bail
  `active` par unité » n'est donc pas en base aujourd'hui** ; seules subsistent
  les contraintes de `Lease` (un bail actif reste contrôlé en PHP). Toute
  affirmation d'intégrité « un bail actif par unité » en base est donc fausse à
  ce jour.
- **D10 — `soft delete` non filtré automatiquement.** `SoftDeletableEntity`
  ne déclare pas de filtre Doctrine ; l'exclusion des lignes supprimées est à la
  charge de chaque requête.
- **D11 — Audit et atomicité.** `AuditLogService::log()` appelle
  `save($auditLog, flush: true)`, donc chaque entrée d'audit est flushée
  immédiatement ; l'atomicité « métier + audit dans la même transaction » n'est
  pas garantie par construction, et certains appels utilisent un `entityId` avant
  le `flush` de l'entité métier (donc potentiellement `null`).
- **D12 — Annulation par contre-écriture.** `PaymentService::cancelPayment()` et
  `ExpenseService::cancelExpense()` créent une ligne de correction **positive**
  (catégorie/motif) plutôt qu'un montant négatif : le total cumulé n'est donc pas
  nécessairement décrémenté. À vérifier avant de s'appuyer sur un total
  d'encaissements nets.
- **D13 — Exemples Swagger / schémas `UnitRequest` et `ParcelRequest`.** Ces deux
  DTO sont les seuls mappés par `#[MapRequestPayload(validationGroups: […])]`.
  Nelmio propage ces groupes comme contexte `serializer_groups` et `PropertyInfo`
  (`SerializerExtractor`) ne rend alors **que** les propriétés portant un
  `#[Groups]` Symfony, sans quoi le schéma OpenAPI généré est vide (`{}`) et
  « Try it out » est inutilisable. Les `#[Groups]` ajoutés sur ces deux DTO
  sont donc de la **métadonnée de documentation uniquement** : le
  `RequestPayloadValueResolver` de Symfony ne passe aucun contexte `groups` à la
  dénormalisation (seul `serializationContext`, vide par défaut), donc le
  comportement runtime est inchangé. Attention : retirer un `#[Groups]` doit
  coïncider avec le retrait du `validationGroups` du contrôleur, sinon le schéma
  redevient vide.

---

## 20. Checklist avant de livrer

```text
[ ] Le changement respecte-t-il l'isolation multi-organisation ?
[ ] Chaque autorisation passe-t-elle par SecurityService + SecurityAction ?
[ ] Les UUID restent-ils les identifiants publics exposés ?
[ ] Les entrées sont-elles validées (DTO + Assert) ?
[ ] Un endpoint ajouté est-il documenté (OpenAPI) et mappé correctement
    (MapRequestPayload pour le corps, MapQueryString pour la query) ?
[ ] Un montant reste-t-il en DECIMAL, avec sa devise, sans float ni mélange ?
[ ] Les opérations sensibles sont-elles auditées ?
[ ] Les erreurs sont-elles traduites en réponses JSON cohérentes ?
[ ] Une modification de schéma a-t-elle sa migration MariaDB (up + down) ?
[ ] Les scripts de tests/ pertinents passent-ils ?
[ ] lint:container / doctrine:schema:validate / yarn type-check passent-ils ?
[ ] Aucun secret ni fichier généré n'a été ajouté au dépôt ?
[ ] Aucune modification préexistante de l'utilisateur n'a été écrasée ?
[ ] AGENTS.md / docs restent-elles cohérentes avec le code ?
```
