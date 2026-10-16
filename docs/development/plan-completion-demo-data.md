# Guide étape par étape : compléter les données de test

Ce guide décrit comment poursuivre le système de données de test de Soft-IMMO
jusqu’à couvrir les rôles, l’occupation des logements, les finances et
l’isolation. Il s’agit d’un **plan de travail** : les éléments de la section
« Ce qui existe déjà » sont implémentés ; les autres étapes ne le sont pas
encore. Pour les commandes déjà disponibles, voir
[`demo-data.md`](demo-data.md).

## 1. Comprendre le point de départ

Le dépôt utilise Symfony 7.4, Doctrine ORM et MariaDB. Les rôles sont des enums
et les accès métier sont vérifiés par `SecurityService`. Les commandes
`app:system:init`, `app:system:check`, `app:demo:load`, `app:demo:accounts`,
`app:demo:tenants`, `app:demo:check` et `app:demo:status` sont disponibles.

Le générateur crée une organisation non publiée, des villes, parcelles,
bâtiments, niveaux, unités disponibles et locataires. Les commandes démo
peuvent aussi créer les comptes des rôles et ajouter des locataires. Les baux,
mouvements résidentiels et transactions financières ne sont pas encore
générés. Il n’existe pas de commande de réinitialisation.

## 2. Préparer une base réservée aux tests

1. Démarrer MariaDB.
2. Créer une base distincte de la base de développement habituelle, par exemple
   `gestion_himmo_security_test`, en `utf8mb4`.
3. Créer un utilisateur MariaDB dédié avec des droits limités à cette base.
4. Configurer `DATABASE_URL_TEST` dans l’environnement local, sans inscrire le
   mot de passe dans Git ni dans ce document.
5. Vérifier soigneusement le nom ciblé avant toute commande qui écrit :

   ```bash
   DATABASE_URL="$DATABASE_URL_TEST" php bin/console doctrine:database:create --if-not-exists --env=dev
   DATABASE_URL="$DATABASE_URL_TEST" php bin/console doctrine:migrations:migrate --no-interaction --env=dev
   DATABASE_URL="$DATABASE_URL_TEST" php bin/console app:system:init --env=dev
   DATABASE_URL="$DATABASE_URL_TEST" php bin/console app:system:check --env=dev
   ```

   Ne pas utiliser `doctrine:database:create` si la base existe déjà. Ne pas
   lancer ces étapes sur une base de production.

6. Vérifier le garde-fou du générateur avant le premier chargement : le nom de
   la base doit finir par `_test`, `_dev` ou `_demo` et l’environnement Symfony
   doit être `dev` ou `test`.

## 3. Vérifier le socle actuel

Commencer par le petit jeu et les contrôles existants :

```bash
DATABASE_URL="$DATABASE_URL_TEST" php bin/console app:demo:load --scenario=small --seed=20991020 --dry-run --env=dev
DATABASE_URL="$DATABASE_URL_TEST" php bin/console app:demo:load --scenario=small --seed=20991020 --env=dev
DATABASE_URL="$DATABASE_URL_TEST" php bin/console app:demo:check --env=dev
DATABASE_URL="$DATABASE_URL_TEST" php bin/console app:demo:status --env=dev
DEMO_TEST_DATABASE_URL="$DATABASE_URL_TEST" php tests/verify-demo-commands.php
```

Le script de vérification utilise une transaction externe et l’annule. Ne
réutiliser ses seeds réservées si elles existent déjà dans cette base. Pour
consulter les volumes `small` et `complete` ainsi que l’idempotence, lancer
également ce script après chaque changement du générateur.

## 4. Préparer les comptes et tester les périmètres par rôle

La commande `app:demo:accounts --scenario=complete --seed=<seed>` crée déjà cinq
comptes actifs : un SUPER_ADMIN, un PATRON, un ADMIN_IMMOBILIER et deux
ADMIN_VILLE affectés chacun à une ville. Elle exige `DEMO_TEST_PASSWORD` (6
caractères minimum), n’affiche pas le mot de passe et refuse les bases hors
`dev`/`test` ou dont le nom n’a pas un suffixe sûr. Pour l’utiliser dans une
base dédiée, suivre les commandes de `demo-data.md`.

Le script `tests/verify-demo-commands.php` contrôle leur nombre, leurs rôles,
leurs périmètres, le hash du mot de passe, et l’idempotence sans laisser de
données après le rollback. Il ne vérifie pas encore le parcours de connexion
HTTP ni les réponses des endpoints métier pour chaque rôle ; c’est la suite de
cette étape.

Avant d’ajouter d’autres comptes ou de modifier leurs permissions, relire les
entités et services réels :

- `User`, `OrganizationUser` et `UserCity` ;
- `UserService`, `OrganizationUserService` et `UserCityService` ;
- `SecurityService` et les contrôles `checkOrganizationAccess()` /
  `checkCityAccess()`.

Puis procéder ainsi :

1. Définir un jeu comprenant deux organisations fictives et plusieurs villes
   dans chacune. Une seule organisation ne permet pas de prouver l’isolation
   inter-organisation.
2. Laisser les comptes créés par la commande dédiés à l’organisation sélectionnée.
   Ne pas réutiliser les comptes entre organisations pour les tests d’isolation.
3. Garder les mots de passe hors du code, des fixtures, de la documentation et
   des logs. La commande les reçoit par variable d’environnement et ne les
   affiche pas ; pour les tests HTTP, utiliser exclusivement une base dédiée.
4. Garantir que chaque compte de test est rattaché uniquement aux organisations
   fictives concernées. L’e-mail étant globalement unique, produire des adresses
   stables avec un domaine réservé aux tests (`example.invalid`).
5. Faire du chargement une transaction. Une même seed et un même scénario ne
   doivent ni créer de doublons ni écraser un compte ou un rôle existant.
6. Étendre `app:demo:check` pour signaler les membres sans organisation, les
   rôles incohérents et les administrateurs de ville sans périmètre.

## 5. Compléter les locataires, puis générer les occupations

La commande `app:demo:tenants --scenario=complete --seed=<seed> --count=<n>`
ajoute des locataires fictifs à l’organisation déjà créée. Elle est
idempotente, lie les locataires à des villes de cette organisation et limite
`--count` à 50. Le test transactionnel couvre l’ajout et la répétition de la
commande.

Pour les contrats et mouvements, relire `LeaseService`,
`ResidentialMovementService`, les DTOs et les entités `Lease`, `LeaseUnit`,
`LeaseOccupant` et `ResidentialMovement` avant d’écrire des fixtures.
Utiliser les services métier existants pour créer et faire
transitionner les baux ; ne pas dupliquer leurs règles dans la commande.

Construire ensuite, par seed, plusieurs cas distincts : unité vacante, bail
actif, bail terminé, entrée récente, départ et historique de mouvements. Les
dates d’essai doivent être déterministes et cohérentes entre elles. Éviter tout
chevauchement invalide de baux pour une même unité. Vérifier que l’état de
l’unité correspond au bail et à l’occupation courants.

## 6. Ajouter des échéances et transactions financières

Avant d’ajouter des paiements ou dépenses, lire `RentService`,
`PaymentService`, `ExpenseService`, `CashBalanceService`, les DTOs et les
rapports correspondants.

1. Générer les échéances à partir des baux valides en utilisant la commande
   existante `app:rents:generate` ou son service métier ; vérifier son
   idempotence.
2. Enregistrer les paiements avec `PaymentService`, jamais par insertion
   directe. Prévoir une échéance payée, une partiellement payée et une en
   retard. Vérifier qu’un paiement ne dépasse pas le solde dû.
3. Créer les dépenses par `ExpenseService` et conserver leur provenance et leur
   devise.
4. Garder USD et CDF dans des totaux séparés, sauf conversion explicite avec un
   taux présent en base et traçable. Ne jamais inventer un taux réel.
5. Comparer les montants, soldes et rapports aux opérations effectivement
   enregistrées. Les paiements sont des traces comptables immuables : ne pas
   les modifier ou les effacer pour nettoyer un test.

Si un service métier ne permet pas de créer le cas voulu de manière sûre,
arrêter et traiter d’abord cette lacune au niveau du service et de ses tests.

## 7. Tester réellement les permissions et l’isolation

Les vérifications doivent appeler le backend (services ou endpoints HTTP), pas
se limiter au menu React. Pour chaque opération de lecture et d’écriture,
tester les cas autorisés et refusés :

| Identité | Résultat attendu |
| --- | --- |
| Membre de l’organisation A | Accède aux ressources permises de A |
| Même identité avec UUID d’une ressource de B | Refus (403 ou 404 selon le risque d’énumération) |
| `ADMIN_VILLE` affecté à Goma | Accède à Goma, pas à Bukavu sans affectation |
| `ADMIN_VILLE` d’une autre organisation | N’accède ni aux villes ni aux ressources de A |
| `PATRON` / `ADMIN_IMMOBILIER` | Accès conforme aux `SecurityAction` du service |
| `SUPER_ADMIN` | Aucun droit métier implicite sur les locataires ou finances |

Exercer aussi les recherches, listes, filtres, statistiques, rapports et
exports. Pour une liste, confirmer que le périmètre est appliqué en base de
données, pas seulement après chargement côté client. Toute modification des
règles d’accès doit étendre les scripts de tests de sécurité du dépôt et utiliser
deux organisations distinctes.

## 8. Concevoir un nettoyage seulement après la traçabilité

Ne pas créer de commande `app:demo:reset` avant de pouvoir identifier de façon
fiable **chaque** ligne générée, y compris les comptes, baux, mouvements, audits,
loyers, paiements et dépenses. Un nom contenant « test » ou un slug seul ne
prouve pas qu’une ressource peut être supprimée.

Si une réinitialisation devient nécessaire :

1. définir le périmètre uniquement à partir du registre de génération et de ses
   relations vérifiées ;
2. refuser le nettoyage si une ressource réelle ou non marquée dépend du jeu ;
3. afficher les quantités par table et demander une confirmation explicite ;
4. exécuter dans une transaction, dans `dev`/`test` seulement, sans `TRUNCATE` ;
5. tenir compte des écritures financières immuables et préférer leur
   contre-écriture métier si une annulation est requise ;
6. tester le refus en production, le refus en cas de périmètre ambigu, le
   rollback sur erreur et l’absence de suppression hors registre.

Tant que ces conditions ne sont pas démontrées, conserver les jeux existants et
créer une nouvelle seed ou une nouvelle base de test dédiée.

## 9. Vérifier chaque étape avant de poursuivre

Après chaque ajout :

```bash
php -l src/Command/NomDeCommande.php
php bin/console lint:container --env=dev
php bin/console doctrine:schema:validate --env=dev
php tests/verify-mariadb.php
php tests/verify-demo-commands.php
```

Si le schéma change, créer une nouvelle migration MariaDB avec `up()` et
`down()`, mettre à jour la liste des tables attendues dans
`tests/verify-mariadb.php`, puis migrer seulement la base dédiée de test avant
la base de développement. Ne jamais modifier une migration déjà appliquée.

Les tests du projet sont des scripts PHP autonomes ; PHPUnit n’est pas installé.
Les scripts qui écrivent doivent se terminer par un rollback ou un nettoyage
strictement limité à leurs données de test. Ne pas lancer une suite de test sur
une base de production.

## Critères pour déclarer le travail terminé

- `small` et `complete` sont reproductibles, transactionnels et idempotents ;
- des comptes des quatre rôles existent et leurs parcours de connexion et
  permissions API sont testés sans secrets codés en dur ;
- les organisations et villes concurrentes prouvent les refus d’accès API ;
- les baux et mouvements sont cohérents avec les unités et leurs dates ;
- échéances, paiements partiels/complets, retards, dépenses et rapports sont
  générés via les services métier et leurs montants sont vérifiés ;
- le statut et le contrôle détectent les incohérences pertinentes sans révéler
  de données personnelles ;
- aucun nettoyage ne peut atteindre des données non marquées ou de production ;
- les tests automatisés et ce guide décrivent exactement le comportement livré.
