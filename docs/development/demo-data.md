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
- Les comptes de test ne sont créés que sur demande, dans une organisation démo
  d’une base autorisée. Le mot de passe choisi reste masqué dans le terminal.
- Le slug réservé est une étiquette machine explicite, pas une détection par
  nom de personne ou par texte « test/demo/exemple ».

## Commandes disponibles

```bash
php bin/console app:system:init
php bin/console app:system:check

php bin/console app:demo:load --scenario=small --seed=20261016 --dry-run
php bin/console app:demo:load --scenario=complete --seed=20261016 --dry-run
php bin/console app:demo:load --scenario=complete --seed=20261016
php bin/console app:demo:accounts --scenario=complete --seed=20261016
php bin/console app:demo:tenants --scenario=complete --seed=20261016 --count=3
php bin/console app:demo:status
php bin/console app:demo:check
```

Pour activer les comptes de test sur le jeu `complete` déjà créé avec la seed
`20261021`, demande un mot de passe temporaire masqué dans le terminal, puis
lance la commande :

```bash
read -rsp 'Mot de passe temporaire (6 caractères minimum) : ' DEMO_TEST_PASSWORD
printf '\n'
export DEMO_TEST_PASSWORD
DATABASE_URL="$DATABASE_URL_TEST" php bin/console app:demo:accounts --scenario=complete --seed=20261021 --env=dev
unset DEMO_TEST_PASSWORD
```

La commande crée un SUPER_ADMIN et quatre membres de l’organisation : un
PATRON, un ADMIN_IMMOBILIER et deux ADMIN_VILLE affectés chacun à une ville
différente. Le même mot de passe temporaire est haché pour ces cinq comptes ;
la commande ne l’affiche jamais. Elle refuse une création partielle si des
adresses réservées sont déjà utilisées. Si les comptes existent déjà, elle ne
change ni les rôles ni les mots de passe.

Pour ajouter trois locataires fictifs au même jeu (et de façon idempotente) :

```bash
DATABASE_URL="$DATABASE_URL_TEST" php bin/console app:demo:tenants --scenario=complete --seed=20261021 --count=3 --env=dev
DATABASE_URL="$DATABASE_URL_TEST" php bin/console app:demo:check --env=dev
DATABASE_URL="$DATABASE_URL_TEST" php bin/console app:demo:status --env=dev
```

Les adresses des locataires utilisent le domaine réservé `example.invalid`.
`--count` accepte de 1 à 50 ; répéter la commande avec la même seed et le même
nombre ne duplique pas les locataires. Un nombre supérieur ajoute uniquement
les locataires manquants.

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
données personnelles. `app:demo:check` vérifie les relations immobilières et
de locataires, le périmètre des ADMIN_VILLE et la séparation du SUPER_ADMIN.

## Limites connues

Les commandes créent maintenant les comptes de test et les locataires
supplémentaires. Elles ne génèrent pas encore de baux, de mouvements
résidentiels, de loyers, de paiements, de dépenses, d’opérations de trésorerie
ni de rapports financiers. Les comptes facilitent les essais d’interface, mais
ils ne remplacent pas des tests d’isolation API automatisés pour chaque rôle.
Les opérations financières devront emprunter les services métier existants
(notamment les transitions de bail et l’enregistrement immuable des paiements)
et être vérifiées par des scripts de test dédiés.

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
