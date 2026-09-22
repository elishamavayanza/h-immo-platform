# Soft-IMMO

## Application de gestion immobilière multi-entreprise

Soft-IMMO est une application professionnelle de gestion immobilière développée avec Symfony.

Le projet est conçu dès le départ comme une plateforme **multi-entreprise**. Une même instance de Soft-IMMO peut gérer plusieurs entreprises immobilières indépendantes, chacune avec son propre périmètre de données, ses utilisateurs, ses villes, son patrimoine immobilier, ses locataires, ses contrats et ses paiements.

L'objectif est de construire une solution fiable, sécurisée, maintenable et évolutive, tout en conservant une architecture volontairement simple.

---

## 1. Objectifs

Soft-IMMO doit permettre de gérer le cycle immobilier principal :

```text
Organization
    |
    v
City
    |
    v
Parcel
    |
    v
Building
    |
    v
Unit
    |
    v
Lease
    |
    v
Rent
    |
    v
Payment
```

Avec la relation :

```text
Tenant
    |
    v
Lease
    |
    v
Unit
```

L'application doit notamment permettre de :

* gérer plusieurs entreprises ;
* gérer les utilisateurs et leurs responsabilités ;
* gérer les villes d'une entreprise ;
* gérer les parcelles ;
* gérer les immeubles ;
* gérer les unités immobilières ;
* gérer les locataires ;
* gérer les contrats de location ;
* générer et suivre les échéances ;
* enregistrer les paiements ;
* conserver l'historique des locations ;
* contrôler les accès ;
* tracer les opérations importantes.

---

# 2. Architecture multi-entreprise

Soft-IMMO n'est pas une application mono-entreprise.

Le système doit pouvoir héberger plusieurs entreprises dans une même application.

Exemple :

```text
Soft-IMMO
|
+-- Organization A
|     |
|     +-- Users
|     +-- Cities
|     +-- Parcels
|     +-- Buildings
|     +-- Units
|     +-- Tenants
|     +-- Leases
|     +-- Rents
|     +-- Payments
|
+-- Organization B
|     |
|     +-- Users
|     +-- Cities
|     +-- Parcels
|     +-- Buildings
|     +-- Units
|     +-- Tenants
|     +-- Leases
|     +-- Rents
|     +-- Payments
|
+-- Organization C
      |
      +-- Users
      +-- Cities
      +-- Parcels
      +-- Buildings
      +-- Units
      +-- Tenants
      +-- Leases
      +-- Rents
      +-- Payments
```

Les données d'une organisation doivent être strictement isolées des données des autres organisations.

Un utilisateur appartenant à une entreprise ne doit jamais pouvoir consulter ou modifier les données d'une autre entreprise.

Cette isolation doit être garantie côté backend.

Le frontend ne doit jamais être considéré comme une couche de sécurité.

---

# 3. Principes architecturaux

Le projet suit les principes suivants :

* simplicité ;
* sécurité ;
* intégrité des données ;
* isolation multi-entreprise ;
* historique fiable ;
* relations explicites ;
* faible duplication ;
* maintenabilité ;
* évolutivité.

Le principe directeur est :

> Simple dans la structure, strict dans les règles.

Le terme "niveau entreprise" ne signifie pas qu'il faut multiplier les tables, les services ou les abstractions.

Il signifie que le système doit être suffisamment solide pour être utilisé et faire évoluer plusieurs entreprises sans devoir refaire toute l'architecture.

---

# 4. Stack technique

## Backend

* Symfony
* PHP
* Doctrine ORM
* Doctrine Migrations
* API REST

## Frontend

* React
* TypeScript

## Base de données

La base de données est relationnelle et doit utiliser :

* clés primaires internes ;
* UUID lorsque prévu par l'architecture ;
* clés étrangères ;
* contraintes d'intégrité ;
* contraintes d'unicité ;
* index appropriés ;
* soft-delete lorsque nécessaire.

Les sessions utilisateurs ne sont pas persistées dans la base de données.

Aucune table `sessions` ne doit être créée.

---

# 5. Organisation fonctionnelle

Le modèle est organisé en quatre packages UML principaux.

```text
01 - Identity & Access

02 - Property Management

03 - Rental Management

04 - System & Audit
```

---

# 6. Identity & Access

Entités :

```text
User
Organization
OrganizationUser
UserCity
```

## User

`User` représente une identité utilisateur.

Il ne doit pas directement porter le rôle métier d'une organisation si ce rôle dépend de son appartenance à celle-ci.

Le rôle est porté par `OrganizationUser`.

## Organization

`Organization` représente une entreprise cliente de Soft-IMMO.

Exemples :

```text
IMMO PLUS
KIVU PROPERTY
CONGO REAL ESTATE
```

Une organisation possède son propre périmètre de données.

## OrganizationUser

`OrganizationUser` représente l'appartenance d'un utilisateur à une organisation.

Structure logique :

```text
User
    |
    v
OrganizationUser
    |
    v
Organization
```

Elle permet notamment de définir le rôle de l'utilisateur dans l'organisation.

## UserCity

`UserCity` permet de limiter un utilisateur ayant le rôle `ADMIN_VILLE` à certaines villes.

Exemple :

```text
Marie
Role: ADMIN_VILLE

UserCity:
    Marie -> Butembo
    Marie -> Beni
```

Marie ne doit pas automatiquement avoir accès aux autres villes de l'entreprise.

---

# 7. Rôles

Les rôles principaux sont :

```text
SUPER_ADMIN
PATRON
ADMIN_IMMOBILIER
ADMIN_VILLE
```

## SUPER_ADMIN

Le `SUPER_ADMIN` représente l'administration globale de la plateforme Soft-IMMO.

Il se situe au niveau plateforme et ne doit pas être considéré comme un simple utilisateur métier d'une entreprise.

Son espace est distinct de celui des entreprises.

## PATRON

Le `PATRON` appartient à une organisation.

Il administre son entreprise selon les permissions prévues par l'application.

Il ne doit jamais accéder aux données d'une autre organisation.

## ADMIN_IMMOBILIER

L'`ADMIN_IMMOBILIER` gère les opérations immobilières autorisées dans son organisation.

## ADMIN_VILLE

L'`ADMIN_VILLE` est limité aux villes qui lui sont explicitement attribuées via `UserCity`.

---

# 8. Property Management

Entités :

```text
City
Parcel
Building
Unit
```

Relations :

```text
Organization
    |
    +--< City
            |
            +--< Parcel
                    |
                    +--< Building
                            |
                            +--< Unit
```

## City

Une ville appartient à une organisation dans le contexte de Soft-IMMO.

Une même ville géographique peut donc exister dans plusieurs organisations sans collision.

Exemple :

```text
IMMO PLUS
    -> Butembo

KIVU PROPERTY
    -> Butembo
```

Ces deux enregistrements restent indépendants.

## Parcel

Une parcelle appartient à une ville.

Elle possède notamment :

* référence ;
* nom ;
* adresse ;
* quartier ;
* superficie ;
* coordonnées géographiques ;
* description.

## Building

Un immeuble appartient à une parcelle.

Il possède notamment :

* référence ;
* nom ;
* type ;
* nombre d'étages ;
* description.

Types possibles :

```text
APARTMENT
COMMERCIAL
OFFICE
RESTAURANT
MIXED
```

## Unit

Une unité appartient à un immeuble.

Types possibles :

```text
APARTMENT
SHOP
OFFICE
RESTAURANT
OTHER
```

Le statut d'occupation ne doit pas être stocké comme source de vérité si l'occupation peut être déterminée à partir du contrat actif.

---

# 9. Rental Management

Entités :

```text
Tenant
Lease
Rent
Payment
```

Relations :

```text
Tenant
    |
    +--< Lease >-- Unit
             |
             +--< Rent
                    |
                    +--< Payment
```

---

# 10. Tenant

Un locataire est indépendant d'une unité.

Un locataire peut avoir plusieurs contrats au cours du temps.

Types :

```text
INDIVIDUAL
COMPANY
```

Ne jamais stocker simplement :

```text
Unit.tenant_id
```

car cela détruirait l'historique des anciennes locations.

L'historique doit être représenté par :

```text
Tenant
    |
    +--< Lease
            |
            +-- Unit
```

---

# 11. Lease

`Lease` représente un contrat historique entre un locataire et une unité.

Statuts :

```text
DRAFT
ACTIVE
EXPIRED
TERMINATED
CANCELLED
```

Un locataire peut avoir plusieurs contrats.

Une unité peut également avoir plusieurs contrats successifs.

Une unité ne doit normalement avoir qu'un seul contrat actif à une date donnée.

Cette règle doit être protégée au niveau métier.

Exemple :

```text
Unit 12
    |
    +-- Lease 2025 -> Tenant A
    |
    +-- Lease 2026 -> Tenant B
```

L'ancien contrat doit rester conservé.

---

# 12. Rent

`Rent` représente une échéance de loyer.

Une ligne correspond à une période donnée.

Exemple :

```text
2026-01 -> 500 USD
2026-02 -> 500 USD
2026-03 -> 500 USD
2026-04 -> 500 USD
```

Statuts :

```text
PENDING
PARTIALLY_PAID
PAID
OVERDUE
```

Ne jamais créer une table par mois.

Une échéance doit être unique pour un contrat et une période donnée.

---

# 13. Payment

`Payment` représente un paiement lié à une échéance.

Méthodes :

```text
CASH
BANK_TRANSFER
MOBILE_MONEY
CARD
OTHER
```

Dans la V1 :

```text
Payment -> Rent
```

Un paiement concerne une seule échéance.

Ne pas créer `PaymentAllocation` par anticipation.

Cette table pourra être ajoutée ultérieurement uniquement si le métier confirme qu'un même paiement peut couvrir plusieurs échéances.

---

# 14. System & Audit

Entité :

```text
AuditLog
```

Le système doit pouvoir répondre aux questions :

```text
Qui ?
A fait quoi ?
Sur quel objet ?
Dans quelle organisation ?
Quand ?
```

Exemples d'actions :

```text
CREATE_PROPERTY
UPDATE_PROPERTY
CREATE_LEASE
TERMINATE_LEASE
CREATE_PAYMENT
UPDATE_PAYMENT
DELETE_PAYMENT
```

L'audit doit être utilisé pour les opérations importantes et sensibles.

---

# 15. Modèle de données V1

Le modèle V1 doit rester autour des entités suivantes :

```text
IDENTITY & ACCESS
-----------------
User
Organization
OrganizationUser
UserCity

PROPERTY MANAGEMENT
-------------------
City
Parcel
Building
Unit

RENTAL MANAGEMENT
-----------------
Tenant
Lease
Rent
Payment

SYSTEM
------
AuditLog
```

Soit environ 13 tables principales.

Toute nouvelle table doit être justifiée par un besoin métier réel.

---

# 16. Relations principales

Le diagramme principal doit rester lisible :

```text
                         User
                           |
                           |
                    OrganizationUser
                           |
                           |
                     Organization
                           |
                           |
                         City
                           |
                         Parcel
                           |
                        Building
                           |
                          Unit
                           |
                         Lease
                        /     \
                       /       \
                  Tenant       Rent
                                  |
                               Payment
```

Relation territoriale :

```text
User
 |
 +--< UserCity >-- City
```

Audit :

```text
User
 |
 +--< AuditLog
```

---

# 17. Sécurité et autorisation

Le backend doit toujours vérifier l'autorisation.

La sécurité doit suivre le principe :

```text
User
  |
  v
OrganizationUser
  |
  v
Organization
  |
  v
Resource
```

Pour une ressource immobilière :

```text
User
  |
  v
OrganizationUser
  |
  v
Organization
  |
  v
City
  |
  v
Parcel
  |
  v
Building
  |
  v
Unit
  |
  v
Lease
  |
  v
Rent
  |
  v
Payment
```

Pour `ADMIN_VILLE` :

```text
User
  |
  v
OrganizationUser
  |
  v
Role = ADMIN_VILLE
  |
  v
UserCity
  |
  v
City
  |
  v
Resource
```

Si l'utilisateur n'est pas autorisé à accéder à la ressource :

```text
403 Forbidden
```

Le contrôle doit être effectué côté backend Symfony.

---

# 18. Isolation des organisations

L'isolation des données est une exigence fondamentale.

Exemple :

```text
Organization A
    |
    +-- City: Butembo
          |
          +-- Parcel A1

Organization B
    |
    +-- City: Butembo
          |
          +-- Parcel B1
```

Un utilisateur de l'Organization A ne doit jamais pouvoir accéder à :

```text
Parcel B1
```

même si :

* la ville porte le même nom ;
* la référence est identique ;
* les données sont techniquement présentes dans la même base.

Toute requête métier doit respecter le périmètre de l'organisation.

---

# 19. Contraintes d'intégrité

Les contraintes SQL doivent empêcher les incohérences.

Exemples :

```text
Organization.code
    UNIQUE
```

```text
OrganizationUser
    UNIQUE(organization_id, user_id)
```

```text
UserCity
    UNIQUE(user_id, city_id)
```

```text
City
    UNIQUE(organization_id, code)
```

```text
Parcel
    UNIQUE(city_id, reference)
```

```text
Building
    UNIQUE(parcel_id, reference)
```

```text
Unit
    UNIQUE(building_id, reference)
```

```text
Lease
    UNIQUE(organization_id, reference)
```

```text
Rent
    UNIQUE(lease_id, period)
```

Les contraintes doivent respecter le périmètre métier.

---

# 20. Index

Créer des index sur les colonnes réellement utilisées pour :

* les relations ;
* les recherches ;
* les filtres ;
* les contrôles d'accès ;
* les dates ;
* les échéances ;
* les paiements.

Notamment lorsque nécessaire :

```text
organization_id
city_id
parcel_id
building_id
unit_id
tenant_id
lease_id
user_id
startDate
endDate
dueDate
paymentDate
```

Ne pas créer automatiquement un index sur chaque colonne.

---

# 21. UUID et identifiants

Lorsque l'architecture du projet utilise les UUID, les entités principales doivent disposer de :

```text
id
uuid
```

`id` sert notamment aux relations internes de la base.

`uuid` peut être utilisé comme identifiant externe dans l'API afin d'éviter d'exposer directement les identifiants internes lorsque cela est pertinent.

Les UUID doivent être générés et gérés de manière cohérente dans toute l'application.

---

# 22. Soft Delete

Utiliser `deletedAt` uniquement lorsqu'il est pertinent de conserver l'historique tout en masquant logiquement l'enregistrement.

Ne pas ajouter automatiquement `deletedAt` à chaque table sans justification.

Les données historiques importantes, notamment les contrats et les paiements, doivent être traitées avec prudence.

Un paiement ou un contrat ne doit pas disparaître de l'historique simplement parce qu'une suppression logique a été demandée.

---

# 23. Enums

Les petites valeurs stables doivent être représentées par des enums lorsque cela est approprié.

Exemples :

```text
Role
BuildingType
UnitType
TenantType
LeaseStatus
RentStatus
PaymentMethod
```

Ne pas créer une table pour chaque enum.

Une table séparée n'est justifiée que si la valeur devient une véritable donnée métier administrable, évolutive ou possédant ses propres attributs.

---

# 24. Tables à ne pas créer prématurément

Ne pas créer automatiquement :

```text
Session
Role
Permission
RolePermission
UserRole
Country
Province
District
Commune
Neighborhood
PropertyType
PaymentMethod
Currency
RentStatus
UnitStatus
BuildingStatus
```

comme tables séparées.

Ne pas créer non plus :

```text
OrganizationCity
OrganizationParcel
OrganizationBuilding
OrganizationUnit
```

si les relations existantes suffisent.

Ne pas créer :

```text
ParcelDocument
BuildingDocument
TenantDocument
LeaseDocument
PaymentDocument
```

dans la V1 sans besoin fonctionnel réel.

---

# 25. Documents

Les documents sont hors du périmètre obligatoire de la V1.

Si la fonctionnalité devient nécessaire, une table générique pourra être étudiée :

```text
Document
--------
id
uuid
organization
name
path
mimeType
size
type
createdBy
createdAt
```

Éviter les relations polymorphiques complexes.

---

# 26. Sessions

Aucune table `sessions` ne doit être créée.

Les sessions ne sont pas persistées dans la base de données.

L'authentification et les mécanismes de session doivent utiliser les mécanismes appropriés de l'application sans ajouter de stockage inutile dans le modèle métier.

---

# 27. Méthode de conception

La conception de la base doit suivre cet ordre :

```text
1. Comprendre le métier
        |
        v
2. Construire le modèle UML
        |
        v
3. Vérifier les cardinalités
        |
        v
4. Vérifier le multi-entreprise
        |
        v
5. Vérifier la sécurité
        |
        v
6. Vérifier les contraintes
        |
        v
7. Définir les enums
        |
        v
8. Créer les entités Doctrine
        |
        v
9. Générer la migration
        |
        v
10. Relire la migration
        |
        v
11. Exécuter la migration
        |
        v
12. Vérifier le schéma réel
        |
        v
13. Créer les fixtures
        |
        v
14. Tester
```

Ne pas commencer directement par les migrations sans avoir validé le modèle.

---

# 28. Doctrine

Les entités Doctrine doivent respecter le modèle UML.

Les relations doivent être explicites.

Éviter :

* les relations ambiguës ;
* les relations polymorphiques complexes ;
* les mappings inutiles ;
* les propriétés redondantes ;
* les champs calculables stockés sans raison.

Les règles métier complexes ne doivent pas être entièrement déléguées aux contrôleurs.

---

# 29. API

L'API REST doit respecter les règles d'autorisation.

Une route ne doit jamais supposer qu'un utilisateur peut accéder à une ressource simplement parce qu'il connaît son identifiant ou son UUID.

Exemple incorrect :

```text
GET /api/units/{uuid}
```

avec récupération directe de l'unité sans vérifier son organisation.

Le backend doit vérifier le périmètre :

```text
User
    |
    v
Organization
    |
    v
Unit
```

et, pour `ADMIN_VILLE`, également :

```text
UserCity
    |
    v
City
```

---

# 30. Historique immobilier

Le système doit conserver l'historique.

Exemple :

```text
Unit 12
    |
    +-- Lease 2024 -> Tenant A
    |
    +-- Lease 2025 -> Tenant B
    |
    +-- Lease 2026 -> Tenant C
```

L'application doit pouvoir retrouver les anciennes locations.

Ne jamais écraser un ancien contrat uniquement parce qu'un nouveau contrat commence.

---

# 31. Occupation d'une unité

Ne pas créer plusieurs sources de vérité.

Éviter :

```text
Unit.status = OCCUPIED

ET

Lease.status = ACTIVE
```

si les deux représentent la même information.

Lorsque cela est possible, l'occupation doit être déterminée à partir du contrat actif.

---

# 32. Paiements et historique financier

Les paiements doivent rester traçables.

Un paiement doit pouvoir être associé à :

```text
Payment
    |
    v
Rent
    |
    v
Lease
    |
    v
Unit
    |
    v
Building
    |
    v
Parcel
    |
    v
City
    |
    v
Organization
```

Cela permet de déterminer l'entreprise, le patrimoine, l'unité et le contrat concernés par une opération financière.

---

# 33. Audit

Les opérations importantes doivent être enregistrées dans `AuditLog`.

L'audit doit permettre d'identifier :

```text
Utilisateur
Organisation
Action
Objet concerné
Identifiant de l'objet
Anciennes valeurs
Nouvelles valeurs
Date
```

Les informations d'audit doivent être protégées contre les modifications non autorisées.

---

# 34. Tests

Les tests doivent couvrir en priorité :

### Isolation multi-entreprise

```text
User Organization A
    -> peut accéder aux données de A

User Organization A
    -> ne peut pas accéder aux données de B
```

### ADMIN_VILLE

```text
ADMIN_VILLE
    -> accès à sa ville
    -> refus des autres villes
```

### Historique

```text
Tenant A
    -> Lease 2025

Tenant B
    -> Lease 2026

Unit
    -> conserve les deux contrats
```

### Loyers

```text
Lease
    -> une échéance par période
```

### Paiements

```text
Payment
    -> appartient à la bonne échéance
```

### Autorisation

Tester les réponses :

```text
401 Unauthorized
403 Forbidden
404 Not Found
```

selon le contexte et la stratégie de sécurité choisie.

---

# 35. Boucle de contrôle obligatoire

Après chaque étape importante, vérifier :

```text
1. Le modèle correspond-il au métier ?

2. Soft-IMMO peut-il gérer plusieurs entreprises ?

3. Les données de chaque organisation sont-elles isolées ?

4. Le PATRON est-il limité à son organisation ?

5. ADMIN_IMMOBILIER est-il limité à son organisation ?

6. ADMIN_VILLE est-il limité à ses villes ?

7. SUPER_ADMIN est-il correctement séparé du périmètre métier
   des organisations ?

8. Existe-t-il une table inutile ?

9. Une information est-elle dupliquée ?

10. Une relation peut-elle casser l'historique ?

11. Les cardinalités sont-elles correctes ?

12. Les contrats historiques sont-ils conservés ?

13. Les paiements sont-ils traçables ?

14. Les échéances sont-elles uniques ?

15. Les contraintes SQL empêchent-elles les doublons ?

16. Les index sont-ils réellement nécessaires ?

17. Un utilisateur peut-il contourner l'isolation d'une organisation ?

18. Le frontend est-il utilisé à tort comme mécanisme de sécurité ?

19. Le modèle reste-t-il lisible dans Visual Paradigm ?

20. Un nouveau développeur peut-il comprendre le modèle rapidement ?
```

Si une réponse est négative, corriger le modèle avant de continuer.

---

# 36. Règle de développement

Avant toute modification importante :

1. analyser l'existant ;
2. comprendre le besoin ;
3. identifier les entités concernées ;
4. vérifier les relations ;
5. vérifier l'impact multi-entreprise ;
6. vérifier les permissions ;
7. vérifier l'impact sur l'historique ;
8. choisir la solution la plus simple ;
9. implémenter ;
10. tester ;
11. vérifier les éventuelles régressions.

Ne pas introduire une abstraction uniquement parce qu'elle semble plus "enterprise".

Ne pas ajouter une table uniquement parce qu'elle pourrait éventuellement être utile dans le futur.

Toute complexité doit avoir une justification métier ou technique réelle.

---

# 37. Livrables

La conception doit aboutir à :

```text
database/
    UML model
    ERD
    entity relationship documentation
```

Et côté Symfony :

```text
src/
    Entity/
        User.php
        Organization.php
        OrganizationUser.php
        UserCity.php
        City.php
        Parcel.php
        Building.php
        Unit.php
        Tenant.php
        Lease.php
        Rent.php
        Payment.php
        AuditLog.php
```

---

# 38. Vision à long terme

Soft-IMMO doit pouvoir évoluer progressivement.

La V1 doit fournir une base solide permettant ensuite d'ajouter, si le besoin métier est confirmé :

* gestion documentaire ;
* notifications ;
* rapports ;
* tableaux de bord ;
* statistiques ;
* maintenance immobilière ;
* gestion des dépenses ;
* gestion des charges ;
* intégrations de paiement ;
* fonctionnalités avancées de permissions ;
* applications mobiles ;
* fonctionnalités SaaS avancées.

Ces fonctionnalités futures ne doivent cependant pas être implémentées prématurément dans la base V1.

---

# 39. Principe final

Soft-IMMO doit être :

```text
Multi-entreprise
        +
Sécurisé
        +
Isolé
        +
Historique
        +
Traçable
        +
Normalisé
        +
Maintenable
        +
Évolutif
```

Tout en restant :

```text
Simple
Compréhensible
Cohérent
Sans sur-ingénierie
```

La règle fondamentale du projet est :

> Construire une architecture suffisamment solide pour une vraie application professionnelle, mais suffisamment simple pour qu'un développeur puisse comprendre le modèle complet en quelques minutes.
# h-immo-platform
