# Soft-IMMO — Couche Repository & DTO

Ce document décrit l'organisation et les conventions des deux couches ajoutées
au-dessus des entités Doctrine : les **Repositories** (accès aux données) et
les **DTO** (Data Transfer Objects, `Request/` et `Response/`).

---

## 1. Objectif de chaque couche

| Couche               | Rôle                                                                 | Contient de la logique ? |
|-----------------------|-----------------------------------------------------------------------|:---:|
| `src/Entity/*`         | Modèle persistant (mapping Doctrine)                                 | Non — propriétés + accesseurs uniquement |
| `src/Repository/*`     | Requêtes de lecture/écriture sur une entité (Doctrine `QueryBuilder`) | Requêtes uniquement, pas de règles métier |
| `src/Dto/Request/*`    | Données **entrantes** (body JSON d'une requête HTTP) à valider       | Non — propriétés + contraintes `Assert` uniquement |
| `src/Dto/Response/*`   | Données **sortantes** exposées par l'API                             | Un seul assembleur statique `fromEntity()` |
| *(non fourni ici)* `src/Service/*` | Règles métier (ex. « un seul bail actif par Unit »), orchestration, transactions | Oui — c'est sa raison d'être |

Cette séparation garantit que **ni l'entité, ni le DTO, ni le repository**
ne portent de règle métier : celle-ci est concentrée dans une couche Service
(non générée dans cette livraison), ce qui facilite les tests unitaires et
évite les effets de bord cachés dans le modèle de données.

---

## 2. Repositories (`src/Repository/`)

### 2.1 Convention commune

Chaque repository :

- étend `Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository` ;
- est déclaré `@extends ServiceEntityRepository<Entité>` (PHPDoc générique,
  utile pour l'autocomplétion et PHPStan/Psalm) ;
- expose `save(Entity $e, bool $flush = false)` et `remove(Entity $e, bool $flush = false)`
  — wrappers fins autour de l'EntityManager, sans logique ;
- expose `findOneByUuid(Uuid $uuid)` quand l'entité est recherchée par son
  identifiant public ;
- expose des méthodes de recherche nommées explicitement selon le besoin
  métier documenté dans le modèle UML (ex. `findActiveLeaseForUnit`,
  `findOverdueByOrganization`), plutôt que des `findBy()` génériques peu
  lisibles dans les contrôleurs/services appelants.

`AuditLogRepository` fait exception : pas de méthode `remove()`, car un
journal d'audit ne doit jamais être supprimé (cohérent avec `CreatedOnlyEntity`).

### 2.2 Où la règle métier « un seul bail ACTIVE par Unit » est-elle appliquée ?

Ni dans `Lease`, ni dans `LeaseRepository`. Le repository fournit seulement
l'outil de vérification :

```php
$activeLease = $leaseRepository->findActiveLeaseForUnit($unit->getId());
```

C'est au service applicatif (ex. `LeaseActivationService`, à créer) de
décider quoi faire si `$activeLease !== null` (rejeter, terminer l'ancien
bail, etc.). Cette séparation est volontaire : le repository répond à
« quel est l'état actuel ? », le service décide « que faire de cet état ? ».

### 2.3 Exemple d'utilisation dans un service (illustratif)

```php
final class LeaseActivationService
{
    public function __construct(
        private readonly LeaseRepository $leaseRepository,
    ) {}

    public function activate(Lease $lease): void
    {
        $current = $this->leaseRepository->findActiveLeaseForUnit(
            $lease->getUnit()->getId()
        );

        if ($current !== null && $current->getId() !== $lease->getId()) {
            throw new \DomainException('Cette unité a déjà un bail actif.');
        }

        $lease->setStatus(LeaseStatus::ACTIVE);
        $this->leaseRepository->save($lease, flush: true);
    }
}
```

---

## 3. DTO de requête (`src/Dto/Request/`)

### 3.1 Convention commune

- Une seule classe par entité (ex. `UnitRequest`), utilisée à la fois pour
  la création et la mise à jour, distinguées par des **groupes de
  validation Symfony** : `'create'` et `'update'`.
- Propriétés publiques typées, sans constructeur ni méthode : un DTO de
  requête est un pur sac de données désérialisé depuis le JSON entrant
  (via le Serializer Symfony ou `RequestPayload` selon la version).
- Les relations vers d'autres entités sont exprimées par leur **UUID
  public** (`organizationUuid`, `unitUuid`, ...), jamais par leur id
  technique interne — cohérent avec le choix fait dans `BaseEntity`.
- Les montants (`area`, `monthlyRent`, `amount`, ...) restent des `string`
  côté DTO, pour la même raison que côté entité (précision décimale).

### 3.2 Exemple d'utilisation dans un contrôleur (illustratif)

```php
#[Route('/api/units', methods: ['POST'])]
public function create(
    #[MapRequestPayload(validationGroups: ['create'])] UnitRequest $request,
    BuildingRepository $buildingRepository,
    UnitRepository $unitRepository,
): JsonResponse {
    $building = $buildingRepository->findOneByUuid(Uuid::fromString($request->buildingUuid));

    $unit = new Unit();
    $unit->setBuilding($building)
        ->setReference($request->reference)
        ->setType($request->type)
        ->setFloor($request->floor)
        ->setSurface($request->surface)
        ->setMonthlyRent($request->monthlyRent)
        ->setCurrency($request->currency);

    $unitRepository->save($unit, flush: true);

    return $this->json(UnitResponse::fromEntity($unit), Response::HTTP_CREATED);
}
```

La logique d'assemblage `Request DTO -> Entity` reste dans le contrôleur ou
un service applicatif dédié, jamais dans le DTO ni dans l'entité.

### 3.3 Cas particulier — `TenantRequest`

`Tenant.type` détermine si `firstName`/`lastName` ou `companyName` sont
attendus. Cette cohérence conditionnelle n'est **pas** codée comme méthode
sur le DTO (ce serait de la logique) : elle doit être vérifiée par un
`Assert\Callback` déclaré via la configuration de validation (fichier YAML/XML
ou attribut sur un Validator dédié), ou dans le service applicatif.

---

## 4. DTO de réponse (`src/Dto/Response/`)

### 4.1 Convention commune

- Classes `final readonly`, propriétés promues en lecture seule.
- Une seule méthode : `public static function fromEntity(Entity $e): self`,
  qui est un assembleur de données pur (aucune branche métier, aucun appel
  externe) — il s'agit de mapping, pas de logique applicative.
- `id` correspond toujours à l'UUID public de l'entité (`(string) $entity->getUuid()`),
  jamais à la clé auto-incrémentée.
- Les relations sont résumées par l'UUID de l'entité liée (`organizationId`,
  `unitId`, ...) et non par un DTO imbriqué complet, pour garder des réponses
  API légères et prévisibles ; un client ayant besoin du détail d'une
  ressource liée appelle l'endpoint dédié à cette ressource.

### 4.2 Exemple d'utilisation

```php
#[Route('/api/units/{uuid}', methods: ['GET'])]
public function show(string $uuid, UnitRepository $unitRepository): JsonResponse
{
    $unit = $unitRepository->findOneByUuid(Uuid::fromString($uuid));

    if ($unit === null) {
        throw $this->createNotFoundException();
    }

    return $this->json(UnitResponse::fromEntity($unit));
}
```

---

## 5. Vue d'ensemble des fichiers livrés

```
src/
├── Entity/
│   ├── Shared/        BaseEntity, TimestampedEntity, SoftDeletableEntity, CreatedOnlyEntity
│   ├── Identity/       User, Organization, OrganizationUser, UserCity
│   ├── Property/       City, Parcel, Building, Unit
│   ├── Rental/         Tenant, Lease, Rent, Payment
│   └── System/         AuditLog
├── Enum/                11 enums (PlatformRole, OrganizationRole, ...)
├── Repository/
│   ├── Identity/        UserRepository, OrganizationRepository, OrganizationUserRepository, UserCityRepository
│   ├── Property/        CityRepository, ParcelRepository, BuildingRepository, UnitRepository
│   ├── Rental/          TenantRepository, LeaseRepository, RentRepository, PaymentRepository
│   └── System/          AuditLogRepository
└── Dto/
    ├── Request/
    │   ├── Identity/    UserRequest, OrganizationRequest, OrganizationUserRequest, UserCityRequest
    │   ├── Property/    CityRequest, ParcelRequest, BuildingRequest, UnitRequest
    │   └── Rental/      TenantRequest, LeaseRequest, RentRequest, PaymentRequest
    └── Response/
        ├── Identity/    UserResponse, OrganizationResponse, OrganizationUserResponse, UserCityResponse
        ├── Property/    CityResponse, ParcelResponse, BuildingResponse, UnitResponse
        ├── Rental/      TenantResponse, LeaseResponse, RentResponse, PaymentResponse
        └── System/      AuditLogResponse
```

**Note** : `AuditLog` n'a pas de `Request DTO` — c'est une ressource générée
par le système (traçabilité), jamais créée directement via une requête
client.

---

## 6. Ce qui reste à écrire (hors périmètre de cette livraison)

- Migrations Doctrine (`doctrine:migrations:diff`).
- Couche `Service/` portant les règles métier (bail actif unique, calcul du
  statut `RentStatus` en fonction des `Payment` reçus, hachage du mot de
  passe `User`, génération automatique des `Rent` mensuelles, etc.).
- Contrôleurs API (`src/Controller/Api/...`) reliant Request DTO → Service →
  Response DTO.
- `Voter`/`Security` pour appliquer la règle d'isolation multi-entreprise
  (un utilisateur ADMIN_VILLE limité à ses `City` via `UserCity`).
- Listener Doctrine pour la mise à jour automatique de `updatedAt`.
