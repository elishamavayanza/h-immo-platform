# Soft-IMMO — Contrôleur API unique (`ApiResourceController`)

## 1. Principe

Plutôt qu'un contrôleur par entité (13 classes quasi identiques :
list/show/create/update/delete), Soft-IMMO expose désormais **un seul
contrôleur générique**, `App\Controller\Api\ApiResourceController`, monté
sur la route dynamique :

```
/api/{resource}
/api/{resource}/{uuid}
```

`{resource}` est un slug (`units`, `leases`, `tenants`, ...). Le contrôleur
ne connaît aucune entité par son nom : il délègue à trois services
d'infrastructure, tous dans `src/Api/` :

| Service               | Rôle                                                                 |
|------------------------|-----------------------------------------------------------------------|
| `ResourceRegistry`      | Résout `{resource}` -> `ResourceDefinition` (entité, DTO request/response, champs de recherche/tri). |
| `ResourceQueryBuilder`  | Construit la requête Doctrine générique (pagination, recherche, tri, exclusion des lignes soft-deleted). |
| `EntityHydrator`        | Recopie un DTO de requête vers une entité, par réflexion (aucune carte de champs à maintenir à la main). |

Ajouter une 14ᵉ ressource = ajouter une entrée dans `ResourceRegistry`.
Aucune ligne du contrôleur, du hydrateur ou du query builder ne change.

---

## 2. Endpoints exposés (identiques pour les 13 ressources)

| Méthode | Route                     | Action    | Codes de retour |
|---------|----------------------------|-----------|------------------|
| GET     | `/api/{resource}`          | `index`   | 200, 422 (pagination invalide) |
| GET     | `/api/{resource}/{uuid}`   | `show`    | 200, 404 |
| POST    | `/api/{resource}`          | `create`  | 201, 422, 405 (ressource lecture seule) |
| PUT/PATCH | `/api/{resource}/{uuid}` | `update`  | 200, 404, 422, 405 |
| DELETE  | `/api/{resource}/{uuid}`   | `delete`  | 200, 404, 405 (ressource non supprimable) |

Ressources déclarées dans `ResourceRegistry` : `users`, `organizations`,
`organization-users`, `user-cities`, `cities`, `parcels`, `buildings`,
`units`, `tenants`, `leases`, `rents`, `payments`, `audit-logs`.

`audit-logs` est en lecture seule (`requestDtoClass = null` -> 405 sur
POST/PUT/PATCH). `payments` est marqué `deletable: false` : un paiement
enregistré ne se supprime pas via l'API (cohérent avec la note déjà posée
sur `PaymentRequest`).

---

## 3. Comment fonctionne l'hydratation générique (`EntityHydrator`)

Pour chaque propriété publique **non nulle** du DTO de requête :

1. Si le nom se termine par `Uuid` (ex. `buildingUuid`) :
   - le nom de base est déduit (`building`) ;
   - le type du paramètre du setter `setBuilding()` de l'entité est lu par
     réflexion pour connaître la classe liée (`App\Entity\Property\Building`) ;
   - l'entité liée est chargée par son UUID (`findOneBy(['uuid' => ...])`) ;
   - si elle n'existe pas -> `404 Not Found`.
2. Sinon, le setter `setXxx()` correspondant est appelé directement avec la
   valeur du DTO, s'il existe sur l'entité.

Une valeur `null` dans le DTO est **toujours ignorée** : c'est le
mécanisme utilisé pour les mises à jour partielles (un champ omis par le
client = inchangé) et pour les champs optionnels non renseignés à la
création.

Cette classe est volontairement isolée dans `src/Api/`, en dehors des
entités et des DTO : elle fait du mapping technique, jamais de règle
métier.

---

## 4. Pagination, recherche, tri (`ResourceQueryBuilder` + `PaginationQuery`)

Paramètres de requête acceptés sur `GET /api/{resource}` :

```
?page=1&limit=10&search=Butembo&sortBy=createdAt&sortOrder=DESC
```

- `search` filtre en `LIKE %terme%` sur les champs listés dans
  `ResourceDefinition::$searchableFields` pour cette ressource (`OR` entre
  les champs). Si la ressource n'a aucun champ de recherche déclaré,
  `search` est ignoré.
- `sortBy` doit figurer dans `ResourceDefinition::$sortableFields`, sinon
  le premier champ triable déclaré est utilisé.
- `limit` est plafonné à 100 côté serveur, quelle que soit la valeur reçue.
- Les entités héritant de `SoftDeletableEntity` sont automatiquement
  filtrées (`deletedAt IS NULL`), détecté par réflexion — pas de
  configuration à ajouter par ressource.

Réponse enveloppée dans `PaginatedResponse` :

```json
{
  "flush": "Succès d'exécution de l'opération",
  "flushDescription": "Liste récupérée avec succès.",
  "status": 200,
  "errors": {},
  "warnings": {},
  "data": {
    "items": [ /* UnitResponse[] */ ],
    "page": 1,
    "limit": 10,
    "totalItems": 45,
    "totalPages": 5
  }
}
```

L'enveloppe externe (`flush`, `status`, `errors`, `data`, ...) provient de
`App\Dto\Feedback`, via `FeedbackTrait::respondSuccess()`.

---

## 5. Gestion des erreurs

Deux mécanismes complémentaires, déjà présents dans les fichiers fournis :

- **Erreurs de validation** (`Assert\...` violées sur un DTO) : interceptées
  dans le contrôleur lui-même, renvoyées via `FeedbackTrait::respondWithViolations()`
  -> `422`, avec le détail champ par champ dans `errors`.
- **Toute autre exception** (404, 405, 500, ...) : interceptée globalement
  par `App\EventListener\ApiExceptionListener`, qui produit une réponse au
  format `HttpErrorResponsePayload` (indépendant de `Feedback` — c'est le
  format des erreurs *transverses*, pas des erreurs de validation métier).

**Correction apportée** par rapport aux fichiers fournis : dans `Feedback`,
`autoInitFlush()` fixait auparavant systématiquement `status` à `200` ou
`422`, ce qui écrasait un code `201` (création) ou `204`/`200` (suppression)
positionné juste avant par le contrôleur. `autoInitFlush()` ne fixe
désormais que le libellé `flush` ; c'est toujours l'appelant
(`respondSuccess($data, $message, $statusCode)`) qui a le dernier mot sur
le code HTTP réellement renvoyé — `FeedbackTrait` a été mis à jour en
conséquence (voir commentaire dans le fichier).

---

## 6. Exemples d'appels

**Créer une Unit :**

```http
POST /api/units
Content-Type: application/json

{
  "buildingUuid": "b7e2c9f0-....-....-....-............",
  "reference": "A-101",
  "type": "apartment",
  "floor": 1,
  "surface": "45.50",
  "monthlyRent": "350.00",
  "currency": "USD"
}
```

**Lister les baux d'une organisation, triés par date de début, page 2 :**

```
GET /api/leases?page=2&limit=20&sortBy=startDate&sortOrder=ASC
```

**Mise à jour partielle d'un Tenant (seul le téléphone change) :**

```http
PATCH /api/tenants/3f1a....-....-....-....-............
Content-Type: application/json

{ "phone": "+243990000000" }
```

**Suppression d'une City (soft delete automatique car `City extends SoftDeletableEntity`) :**

```
DELETE /api/cities/1c9e....-....-....-....-............
```

---

## 7. Limites assumées de cette version générique

- Aucune règle métier n'est branchée dans `ApiResourceController` (ex. « un
  seul bail ACTIVE par Unit », hachage du mot de passe `User`, calcul de
  `RentStatus` à partir des `Payment`). Le contrôleur reste un simple
  orchestrateur CRUD ; ces règles doivent être ajoutées via une couche
  `Service/` invoquée explicitement (voir `docs/REPOSITORY_ET_DTO.md`,
  section 2.2, pour l'exemple de `LeaseActivationService`).
- Pas encore de `Voter`/sécurité pour l'isolation multi-entreprise (un
  utilisateur ADMIN_VILLE limité à ses `City` via `UserCity`) : à ce stade,
  toute requête authentifiée peut accéder à toutes les ressources.
- La cohérence conditionnelle de `TenantRequest` (`firstName`/`lastName` vs
  `companyName` selon `type`) n'est pas vérifiée : à ajouter via un
  `Assert\Callback` ou dans un service de validation dédié.
