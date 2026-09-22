# Contribuer à Soft-IMMO
Merci de l'intérêt que vous portez à **H-Immo**.

Ce document décrit les règles de contribution, le processus de développement, les conventions de code et les bonnes pratiques adoptées par le projet.

L'objectif est de maintenir une base de code propre, sécurisée, maintenable et évolutive, tout en permettant à H-Immo de fonctionner comme une **plateforme immobilière multi-entreprises**.

---

# 1. À propos du projet

**H-Immo** est une plateforme de gestion immobilière conçue pour permettre à plusieurs entreprises indépendantes de gérer leurs activités immobilières au sein d'un même système.

La plateforme est conçue selon une architecture **multi-tenant**, dans laquelle chaque entreprise dispose de son propre espace et de ses propres données.

H-Immo peut notamment gérer :

* les entreprises et leurs informations ;
* les utilisateurs et leurs comptes ;
* les propriétaires de biens ;
* les locataires ;
* les biens immobiliers ;
* les unités et espaces immobiliers ;
* les contrats de location ;
* les loyers ;
* les paiements ;
* les dépenses ;
* la maintenance et les interventions ;
* les documents ;
* les rapports et statistiques ;
* les notifications ;
* l'historique et l'audit des opérations.

L'architecture doit permettre d'ajouter de nouvelles entreprises sans modifier le fonctionnement des entreprises existantes.

---

# 2. Architecture multi-entreprises

H-Immo n'est pas destiné à une seule entreprise.

Le système doit pouvoir accueillir plusieurs entreprises indépendantes :

```text
H-Immo Platform
│
├── Organization A
│   ├── Owner
│   ├── Administrators
│   ├── Employees
│   ├── Properties
│   ├── Owners
│   ├── Tenants
│   └── Contracts
│
├── Organization B
│   ├── Owner
│   ├── Administrators
│   ├── Employees
│   ├── Properties
│   ├── Owners
│   ├── Tenants
│   └── Contracts
│
└── Organization C
    ├── Owner
    ├── Administrators
    ├── Employees
    ├── Properties
    ├── Owners
    ├── Tenants
    └── Contracts
```

Les données d'une entreprise doivent être strictement isolées de celles des autres entreprises.

Un utilisateur appartenant à une entreprise ne doit jamais pouvoir accéder aux données d'une autre entreprise sans autorisation explicite prévue par l'architecture.

---

# 3. Niveaux d'administration

H-Immo distingue plusieurs niveaux d'accès.

## Administration de la plateforme

Le **SUPER_ADMIN** appartient à la plateforme H-Immo et peut gérer les éléments globaux de la plateforme.

Il ne doit pas disposer automatiquement d'un accès aux données privées des entreprises.

## Administration d'une entreprise

Chaque entreprise possède un utilisateur pouvant agir comme **ORGANIZATION_OWNER**.

Le propriétaire de l'entreprise peut notamment :

* gérer les informations de son entreprise ;
* gérer les administrateurs ;
* gérer les utilisateurs ;
* gérer les permissions ;
* consulter les données de son entreprise ;
* configurer les paramètres de son entreprise.

Les administrateurs et employés restent limités au périmètre de leur entreprise.

## Administration territoriale

H-Immo peut également gérer un niveau **CITY_ADMIN** lorsqu'une administration municipale est intégrée à la plateforme.

Le périmètre d'un administrateur municipal doit être limité à la ville qui lui est attribuée.

Les droits d'accès de l'administration municipale doivent être explicitement définis et ne doivent pas donner automatiquement accès aux données privées des entreprises.

---

# 4. Technologies utilisées

Le projet repose notamment sur les technologies suivantes :

* PHP 8.4 ou supérieur ;
* Symfony 7.4 LTS ;
* Doctrine ORM ;
* Doctrine Migrations ;
* MariaDB 11.8 ;
* Composer ;
* React 19 ;
* TypeScript ;
* Vite ;
* Yarn 4.18 ;
* JWT Authentication ;
* NelmioApiDocBundle / OpenAPI ;
* PHPUnit.

Les versions réellement utilisées doivent rester cohérentes avec les fichiers `composer.json` et `package.json`.

---

# 5. Workflow Git

Le projet suit une organisation inspirée de **Gitflow**.

## Branches principales

| Branche      | Description                                         |
| ------------ | --------------------------------------------------- |
| `main`       | Version stable destinée à la production             |
| `develop`    | Branche d'intégration des nouvelles fonctionnalités |
| `feature/*`  | Développement de nouvelles fonctionnalités          |
| `fix/*`      | Correction de bogues                                |
| `hotfix/*`   | Correctifs urgents                                  |
| `refactor/*` | Réorganisation interne du code                      |
| `docs/*`     | Documentation                                       |
| `test/*`     | Ajout ou amélioration des tests                     |

### Règles de contribution

* Ne jamais effectuer de commit directement sur `main`.
* Ne jamais pousser directement sur `develop`.
* Toute modification importante doit être proposée via une Pull Request.
* Une Pull Request doit traiter un sujet clairement défini.
* Les commits doivent être clairs et explicites.
* Les modifications de sécurité doivent être traitées avec une attention particulière.
* Une modification du modèle de données doit être accompagnée des éléments nécessaires à sa migration et à sa validation.

---

# 6. Convention des messages de commit

Format recommandé :

```text
[TAG] Type #Issue : Description
```

Exemples :

```text
[AUTH] Add #12 : Ajout de l'authentification JWT

[ORG] Add #18 : Ajout de la gestion des entreprises

[PROPERTY] Add #25 : Ajout de la gestion des biens immobiliers

[RENT] Update #31 : Amélioration de la gestion des loyers

[PAYMENT] Fix #42 : Correction du calcul des paiements

[SECURITY] Fix #48 : Correction du contrôle d'accès

[API] Update #55 : Mise à jour de la documentation OpenAPI

[DB] Add #61 : Ajout de la migration des contrats

[TEST] Add #70 : Ajout des tests Property

[DOC] Update #75 : Mise à jour de la documentation
```

### Tags disponibles

```text
AUTH
USER
ORG
CITY
PROPERTY
OWNER
TENANT
CONTRACT
RENT
PAYMENT
EXPENSE
MAINTENANCE
DOCUMENT
REPORT
NOTIFICATION
API
DB
SECURITY
AUDIT
TEST
UI
DOC
```

---

# 7. Structure du projet

La structure doit séparer clairement les responsabilités.

```text
src/
├── Controller/
├── DTO/
├── Entity/
├── Enum/
├── Event/
├── Exception/
├── Mapper/
├── Repository/
├── Security/
├── Service/
├── Trait/
├── Utils/
└── Validator/
```

Lorsque le projet grandit, des sous-espaces fonctionnels peuvent être introduits afin d'éviter que les dossiers deviennent trop volumineux.

Exemple :

```text
src/
├── Controller/
│   ├── Auth/
│   ├── Organization/
│   ├── Property/
│   ├── Tenant/
│   ├── Contract/
│   └── Payment/
│
├── DTO/
├── Entity/
├── Enum/
├── Repository/
├── Security/
├── Service/
└── Validator/
```

---

# 8. Installation de l'environnement de développement

### Cloner le dépôt

```bash
git clone https://github.com/<organisation>/gestion-himmo-app.git
cd gestion-himmo-app
```

### Installer les dépendances PHP

```bash
composer install
```

### Installer les dépendances frontend

```bash
yarn install
```

### Configurer l'environnement

Créer un fichier `.env.local` adapté à l'environnement local.

Les informations sensibles doivent rester uniquement dans les fichiers locaux et ne doivent jamais être versionnées.

### Créer la base de données

```bash
php bin/console doctrine:database:create --if-not-exists
```

### Exécuter les migrations

```bash
php bin/console doctrine:migrations:migrate
```

### Vérifier le mapping Doctrine

```bash
php bin/console doctrine:schema:validate
```

### Lancer Symfony

```bash
symfony server:start
```

### Lancer React/Vite

```bash
yarn dev
```

---

# 9. Conventions de développement

Les bonnes pratiques Symfony et PHP doivent être respectées.

* Utiliser l'injection de dépendances.
* Garder les contrôleurs simples et légers.
* Centraliser la logique métier dans les services.
* Utiliser des DTO pour les données d'entrée et de sortie lorsque cela est pertinent.
* Valider systématiquement les données reçues.
* Utiliser les Repository pour l'accès aux données.
* Éviter la logique métier directement dans les contrôleurs.
* Utiliser les Enums pour les valeurs métier contrôlées.
* Donner des noms explicites aux classes, méthodes et variables.
* Respecter les standards de code PHP du projet.
* Maintenir une séparation claire entre domaine, infrastructure et présentation.

---

# 10. Sécurité et contrôle d'accès

La sécurité constitue une partie fondamentale de l'architecture H-Immo.

L'autorisation ne doit pas être basée uniquement sur le rôle de l'utilisateur.

Le contrôle d'accès doit prendre en compte :

```text
Utilisateur
    +
Rôle
    +
Permission
    +
Organisation
    +
Périmètre
    +
Ressource
```

Par exemple, un utilisateur appartenant à `Organization A` ne doit pas pouvoir consulter une propriété appartenant à `Organization B`.

Les contrôles d'autorisation doivent être appliqués côté serveur.

Le frontend React ne constitue jamais une frontière de sécurité.

### Symfony Voters

Les Voters doivent être utilisés lorsque l'autorisation dépend d'une ressource spécifique.

Exemples :

```text
PropertyVoter
TenantVoter
OwnerVoter
ContractVoter
PaymentVoter
DocumentVoter
UserVoter
```

---

# 11. Gestion des utilisateurs

Un utilisateur possède un compte H-Immo et peut être associé à une organisation selon son rôle et son périmètre.

Les rôles doivent être utilisés pour définir la fonction générale de l'utilisateur.

Les permissions définissent les actions autorisées.

Le périmètre définit les données auxquelles l'utilisateur peut accéder.

Exemple :

```text
User
 ├── Role: ORGANIZATION_ADMIN
 ├── Organization: Organization A
 └── Permissions:
      ├── PROPERTY_VIEW
      ├── PROPERTY_CREATE
      └── PROPERTY_UPDATE
```

Un utilisateur ne doit jamais pouvoir modifier son périmètre d'accès simplement en envoyant un nouvel identifiant dans une requête HTTP.

---

# 12. Base de données

Le projet utilise :

* Doctrine ORM ;
* MariaDB ;
* Doctrine Migrations.

Toute modification du schéma de la base de données doit être représentée par une migration Doctrine.

Exemple :

```bash
php bin/console doctrine:migrations:diff --formatted
```

Puis :

```bash
php bin/console doctrine:migrations:migrate
```

Aucune modification directe du schéma de production ne doit être effectuée en dehors du processus de migration prévu par le projet.

Les données des différentes organisations doivent rester correctement isolées.

---

# 13. API REST

L'API respecte les principes REST.

Les endpoints doivent :

* utiliser correctement les méthodes HTTP ;
* utiliser des codes HTTP appropriés ;
* utiliser le versionnement `/api` lorsque celui-ci est défini ;
* valider les données reçues ;
* retourner des réponses cohérentes ;
* retourner des erreurs structurées ;
* respecter les règles d'autorisation ;
* être documentés avec OpenAPI.

Exemples :

```text
GET    /api/properties
POST   /api/properties
GET    /api/properties/{id}
PATCH  /api/properties/{id}
DELETE /api/properties/{id}
```

Chaque endpoint doit vérifier que la ressource appartient au périmètre autorisé de l'utilisateur.

---

# 14. Documentation API

La documentation de l'API est générée avec **NelmioApiDocBundle / OpenAPI**.

La documentation doit être maintenue lors de l'ajout ou de la modification d'un endpoint.

Les DTO et modèles doivent être documentés de manière claire afin d'éviter une documentation API inutilement complexe.

---

# 15. Tests

Avant de soumettre une Pull Request, exécuter les tests :

```bash
php bin/phpunit
```

Pour le frontend :

```bash
yarn type-check
```

Et lorsque cela est nécessaire :

```bash
yarn build
```

Les nouvelles fonctionnalités importantes doivent être accompagnées de tests.

Les contrôles d'autorisation et l'isolation entre organisations doivent faire l'objet de tests spécifiques.

---

# 16. Pull Requests

Avant d'ouvrir une Pull Request, vérifier que :

* le code respecte les conventions du projet ;
* les tests passent ;
* le type-check frontend passe ;
* le build frontend fonctionne lorsque nécessaire ;
* aucune donnée sensible n'est incluse ;
* les migrations nécessaires sont présentes ;
* la documentation est mise à jour si nécessaire ;
* les règles d'autorisation sont correctement appliquées ;
* l'isolation entre organisations est préservée.

Une Pull Request doit expliquer clairement :

* le problème traité ;
* la solution proposée ;
* les modifications principales ;
* les tests effectués ;
* les éventuelles migrations nécessaires.

---

# 17. Fichiers sensibles et fichiers générés

Les fichiers sensibles ou générés ne doivent pas être versionnés.

Notamment :

```text
.env.local
.env.*.local
/vendor/
/node_modules/
/var/
/public/build/
/public/uploads/
```

Les clés privées, mots de passe, tokens et secrets de production ne doivent jamais être ajoutés au dépôt Git.

Les fichiers générés automatiquement doivent être exclus lorsqu'ils ne sont pas nécessaires au fonctionnement du projet.

---

# 18. Modélisation UML

Le modèle conceptuel et la modélisation UML officielle du projet sont réalisés avec **Visual Paradigm**.

Le projet principal est conservé dans :

```text
docs/
└── uml/
    └── H-Immo.vpp
```

Le fichier `H-Immo.vpp` constitue le modèle UML principal du projet.

Les fichiers temporaires, sauvegardes et exports générés par Visual Paradigm ne doivent pas être ajoutés au dépôt lorsqu'ils ne sont pas nécessaires.

Toute modification importante de l'architecture ou du modèle de données doit être répercutée dans le modèle UML.

---

# 19. Audit et traçabilité

Les opérations importantes effectuées dans H-Immo doivent pouvoir être tracées lorsque cela est nécessaire.

Les informations d'audit peuvent notamment permettre d'identifier :

* l'utilisateur ayant effectué une opération ;
* l'action effectuée ;
* la ressource concernée ;
* la date et l'heure ;
* le contexte de l'opération ;
* les changements importants effectués.

Les informations sensibles telles que les mots de passe, tokens et secrets ne doivent jamais être enregistrées dans les journaux.

---

# 20. Gestion des erreurs

Les erreurs doivent être traitées de manière cohérente.

L'API doit retourner des réponses structurées et compréhensibles sans exposer d'informations internes sensibles.

En production, les exceptions et informations techniques internes ne doivent pas être directement exposées aux utilisateurs.

---

# 21. Performance

Les fonctionnalités doivent être conçues en tenant compte de la croissance du nombre :

* d'entreprises ;
* d'utilisateurs ;
* de propriétés ;
* de contrats ;
* de paiements ;
* de documents ;
* de données historiques.

Les requêtes Doctrine doivent éviter les chargements inutiles et les problèmes N+1.

Les index de base de données doivent être ajoutés lorsque cela est nécessaire pour les recherches et les relations fréquemment utilisées.

---

# 22. Évolutivité

H-Immo doit rester capable d'évoluer sans nécessiter une refonte complète de son architecture.

Les nouvelles fonctionnalités doivent respecter les principes suivants :

* faible couplage ;
* responsabilités clairement séparées ;
* services réutilisables ;
* modèle de données cohérent ;
* sécurité centralisée ;
* isolation des organisations ;
* API versionnée ;
* tests automatisés.

Les solutions excessivement complexes doivent être évitées lorsqu'une solution plus simple répond correctement au besoin.

---

# 23. Signaler un problème

Utilisez les **GitHub Issues** pour signaler :

* un bogue ;
* un problème de sécurité ;
* une demande d'amélioration ;
* une nouvelle fonctionnalité ;
* un problème de documentation ;
* un problème de performance.

Merci de fournir suffisamment d'informations pour permettre la reproduction et l'analyse du problème.

---

# 24. Sécurité

Ne jamais versionner :

* mots de passe ;
* clés API ;
* clés privées ;
* tokens ;
* secrets JWT ;
* fichiers `.env.local` ;
* identifiants de base de données ;
* configurations sensibles de production.

En cas de découverte d'une vulnérabilité de sécurité, celle-ci doit être signalée de manière privée avant toute publication publique.

Les mécanismes de sécurité doivent être implémentés côté serveur et ne doivent jamais dépendre uniquement du frontend.

---

# 25. Revue de code

Chaque Pull Request peut être examinée selon les critères suivants :

* architecture ;
* qualité du code ;
* sécurité ;
* isolation des données ;
* performances ;
* maintenabilité ;
* tests ;
* documentation ;
* cohérence avec le modèle UML.

Les remarques de revue ont pour objectif d'améliorer la qualité globale du projet et de maintenir une architecture cohérente.

---

# 26. Principe général du projet

Toute contribution à H-Immo doit respecter les principes suivants :

```text
Simple
    +
Sécurisé
    +
Maintenable
    +
Évolutif
    +
Multi-entreprises
    +
Isolation des données
```

H-Immo doit rester une plateforme capable d'évoluer d'une petite installation à un système utilisé par plusieurs entreprises immobilières indépendantes, sans compromettre la sécurité, les performances ou la qualité de l'architecture.

---

# 27. Licence

En contribuant à **H-Immo**, vous acceptez que vos contributions soient distribuées sous la licence du projet.
