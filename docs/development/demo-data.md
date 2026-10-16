# Données système et jeu de démonstration

## Audit du modèle

L’application utilise Symfony 7.4, Doctrine ORM 3 et MariaDB. Les rôles sont
des enums (`PlatformRole` et `OrganizationRole`) : aucune table de référence ni
commande de fixtures n’existait. Le modèle inclut notamment `Organization`,
`OrganizationUser`, `UserCity`, `City`, `Parcel`, `Building`, `BuildingLevel`,
`Unit`, `Tenant`, `Lease`, `Rent`, `Payment`, `Expense` et les journaux métier.
Les échéances de baux actifs sont déjà générées par `app:rents:generate`.

Les jeux créés ici disposent d’une ligne dédiée dans `demo_dataset`, liée à
l’organisation par une contrainte unique ; son scénario et sa seed sont
également uniques. Le slug exact `soft-immo-demo-v1-<scenario>-<seed>` sert de
garde de collision, tandis que les vérifications et agrégats s’appuient sur le
registre. Le chargement ne met jamais à jour un jeu déjà enregistré. La
suppression complète n’est pas fournie : sans traçabilité des auteurs sur toutes les tables
comptables immuables et sans procédure métier de contre-écriture, une purge
automatique ne serait pas sûre.

## Préconditions et environnement

- MariaDB doit être démarré et les migrations déjà appliquées.
- Pour charger un jeu, Symfony doit être en `dev` ou `test` et la base doit se
  terminer par `_dev`, `_test` ou `_demo`. Le chargeur refuse les autres noms de
  base et refuse toujours l’environnement `prod`.
- Les commandes n’affichent aucun mot de passe ni secret. Aucun compte
  authentifiable n’est créé.
- Le slug réservé est une étiquette machine explicite, pas une détection par
  nom de personne ou par texte « test/demo/exemple ».

## Commandes disponibles

```bash
php bin/console app:system:init
php bin/console app:system:check

php bin/console app:demo:load --scenario=small --seed=20261016 --dry-run
php bin/console app:demo:load --scenario=complete --seed=20261016 --dry-run
php bin/console app:demo:load --scenario=complete --seed=20261016
php bin/console app:demo:status
php bin/console app:demo:check
```

Sur une base dédiée dont le nom se termine par `_test`, `_dev` ou `_demo`, le
script `php tests/verify-demo-commands.php` vérifie les scénarios `small` et
`complete`, leurs volumes, l'idempotence et le `dry-run`. Il annule toutes ses
écritures avec une transaction externe. Par exemple, si `DATABASE_URL_TEST`
désigne cette base :

```bash
DEMO_TEST_DATABASE_URL="$DATABASE_URL_TEST" php tests/verify-demo-commands.php
```

Le script refuse de démarrer si la base ne porte pas l'un de ces suffixes ou si
une de ses seeds réservées est déjà présente.

`app:system:init` vérifie la connexion, les mappings et les rôles disponibles
dans le code. Il ne crée aucune organisation, aucun compte ni réglage.
`app:system:check` contrôle la connexion et la validité des mappings Doctrine.
`app:demo:load` crée l’organisation fictive non publiée, des villes, parcelles,
bâtiments, niveaux, unités disponibles et locataires fictifs. Le scénario
`small` produit 2 villes, 2 parcelles, 2 bâtiments, 4 unités et 2 locataires ;
`complete` en produit respectivement 4, 4, 4, 16 et 8. Le chargement est
transactionnel. Une seed déjà présente est laissée intacte ; les mêmes
paramètres n’ajoutent donc pas de doublons ni ne modifient les données.
`--dry-run` vérifie l’environnement et le nom de base, puis affiche les
volumes prévus sans écrire.

`app:demo:status` présente des agrégats uniquement, sans e-mails, noms ou autres
données personnelles. `app:demo:check` vérifie le rattachement des éléments
immobiliers au jeu marqué et l’absence de publication de la vitrine.

## Limites connues

Cette première version ne génère pas d’utilisateurs PATRON/ADMIN_IMMOBILIER/
ADMIN_VILLE, de baux, de mouvements résidentiels, de loyers, de paiements,
dépenses, opérations de trésorerie ni rapports financiers. Elle ne prétend donc
pas valider les calculs monétaires ou l’isolation API par rôle. Ces écritures
doivent emprunter les services métier existants (notamment les transitions de
bail et l’enregistrement immuable des paiements), et nécessitent un jeu de tests
spécifique avant d’être ajoutées au générateur. Les comptes de test et mots de
passe n’existent pas.

La réinitialisation est volontairement absente : `app:demo:reset` ne doit être
ajoutée qu’avec une traçabilité fiable de toutes les données créées, un plan de
suppression respectant les FKs et les écritures financières immuables, un
aperçu du périmètre, une confirmation explicite et des tests transactionnels.

## Ajouter un scénario

Étendre les entités réellement présentes, rattacher chaque donnée au jeu
marqué, conserver l’isolation par organisation/ville, utiliser les services
métier pour les transitions et montants, et mettre à jour `app:demo:check` ainsi
que les tests transactionnels. Ne jamais ajouter une purge par `TRUNCATE`, ni
identifier des données fictives par le seul contenu de leurs noms.
