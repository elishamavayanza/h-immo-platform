# Tâches planifiées

Les commandes ci-dessous s'exécutent dans le même environnement et avec la
même configuration Symfony que l'application. Elles doivent être planifiées
sur un seul hôte applicatif par base de données afin d'éviter le travail
redondant ; la génération des échéances reste toutefois idempotente grâce à
l'unicité `(lease_id, period)`.

## Génération des échéances de loyer

`app:rents:generate` génère les échéances manquantes des baux actifs jusqu'au
mois courant inclus. La commande peut être relancée sans créer de doublons.
Après une première simulation en préproduction, exécuter une fois par jour
permet de rattraper une interruption et de prendre en compte un bail activé
pendant le mois :

```cron
15 2 * * * cd /chemin/vers/gestion-himmo-app && php bin/console app:rents:generate --env=prod --no-interaction >> var/log/rents-generate.log 2>&1
```

Pour valider le résultat sans écriture :

```bash
php bin/console app:rents:generate --env=prod --dry-run
```

La commande utilise le mois courant par défaut. `--end-date=YYYY-MM-01` permet
un rattrapage ciblé jusqu'au mois indiqué ; `--lease=<UUID>` limite l'opération
à un bail. Ne planifier aucun `--end-date` futur : une échéance ne doit être
créée que dans la période courante ou passée.

## Expiration des suspensions

La commande `app:suspensions:expire` réactive les comptes et organisations
dont l'échéance est atteinte. Une suspension sans date de fin reste active.
Pour une réactivation à la minute, planifier chaque minute :

```cron
* * * * * cd /chemin/vers/gestion-himmo-app && php bin/console app:suspensions:expire --env=prod --no-interaction >> var/log/suspensions.log 2>&1
```

Cette commande est idempotente et journalise les transitions dans l'audit.
Vérifier les journaux après déploiement et configurer leur rotation pour éviter
une croissance illimitée.

## Conservation des documents privés

La purge est désactivée par défaut (`PRIVATE_DOCUMENT_RETENTION_DAYS=0`). La
durée doit être validée au regard des obligations légales et des règles
comptables avant de la modifier. Une fois la durée approuvée, définir un nombre
de jours strictement positif dans `.env.local` ou les variables du déploiement.
La commande ne traite que 500 fichiers par exécution et peut donc être
planifiée quotidiennement :

```cron
40 3 * * * cd /chemin/vers/gestion-himmo-app && php bin/console app:documents:purge-expired --env=prod --no-interaction >> var/log/documents-purge.log 2>&1
```

La première exécution doit se faire en simulation :

```bash
php bin/console app:documents:purge-expired --env=prod --dry-run
```

Chaque suppression définitive est inscrite dans l'audit. Ne configurer aucune
purge avant validation de la durée de conservation.

## Exploitation

- Utiliser le chemin absolu de l'installation et le compte système dédié à
  l'application ; ne pas exécuter ces commandes comme `root`.
- Garder les journaux hors du répertoire public et avec des permissions
  restreintes.
- Superviser le code de sortie et l'espace disque des journaux.
- En environnement multi-instance, un seul ordonnanceur par base suffit ;
  éviter de programmer la même tâche sur chaque réplique.
