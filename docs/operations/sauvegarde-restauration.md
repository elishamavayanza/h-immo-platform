# Sauvegarde et restauration

Les données MariaDB et les fichiers déposés dans `public/uploads/` forment un
ensemble : une sauvegarde ne doit pas inclure la base seule. Le script
`scripts/backup-soft-immo.sh` produit un dump cohérent avec les transactions,
une archive des médias, des métadonnées (dont le commit) et des sommes SHA-256.
Les fichiers sont créés avec des permissions privées sous `var/backups/`.

## Sauvegarder

Installer le client MariaDB sur l'hôte applicatif et fournir les variables à
partir du gestionnaire de secrets de l'environnement (ne pas les inscrire dans
Git ni les placer dans la ligne de commande) :

```bash
export BACKUP_DB_HOST=127.0.0.1
export BACKUP_DB_PORT=3306
export BACKUP_DB_NAME=gestion_himmo
export BACKUP_DB_USER=app
export BACKUP_DB_PASSWORD='secret depuis le coffre'
export BACKUP_MEDIA_DIR=/chemin/absolu/vers/public/uploads
export BACKUP_ROOT=/chemin/absolu/vers/sauvegardes
bash scripts/backup-soft-immo.sh
```

Copier ensuite chaque sauvegarde vers un stockage distinct de la machine et
chiffré au repos, avec accès restreint. Définir une rétention adaptée aux
obligations de l'organisation. Une sauvegarde uniquement sur le même disque
ne protège pas contre la perte de ce disque.

Planification indicative (quotidienne à 01:30 UTC, à ajuster selon le RPO du
service) :

```cron
30 1 * * * cd /chemin/vers/gestion-himmo-app && /usr/bin/bash scripts/backup-soft-immo.sh >> var/log/backup.log 2>&1
```

Le coffre de secrets doit injecter les variables de connexion et le répertoire
de destination du stockage hors site avant d'exécuter cette tâche. Surveiller
le code de sortie et l'âge de la dernière sauvegarde copiée hors site.

## Restaurer et éprouver la procédure

La restauration exige le drapeau `--confirm`, une base vide et un répertoire
média absent ou vide. Utiliser d'abord une base jetable dédiée à l'exercice,
jamais la base de production. Le compte de restauration doit pouvoir lire la
base cible et créer les tables. Créer au préalable cette base cible vide.

```bash
export RESTORE_DB_HOST=127.0.0.1
export RESTORE_DB_PORT=3306
export RESTORE_DB_NAME=gestion_himmo_restore
export RESTORE_DB_USER=restore_user
export RESTORE_DB_PASSWORD='secret depuis le coffre'
export RESTORE_MEDIA_DIR=/srv/soft-immo-restore/uploads
bash scripts/restore-soft-immo.sh /chemin/vers/sauvegardes/20261015T013000Z --confirm
```

Après une restauration d'exercice, vérifier `php bin/console doctrine:migrations:status`,
les contrôles `tests/verify-mariadb.php` et `tests/verify-tenant-isolation.php`,
puis ouvrir quelques photos et effectuer une connexion de test. Noter le temps
de restauration et les éventuels écarts. La procédure ne supprime pas de base
existante et refuse une cible qui contient déjà des tables ou des médias.

## Tâches planifiées

Les échéances mensuelles sont idempotentes et doivent être générées
quotidiennement afin qu'un bail créé ou activé en cours de mois soit pris en
compte :

```cron
10 0 * * * cd /chemin/vers/gestion-himmo-app && php bin/console app:rents:generate --env=prod --no-interaction >> var/log/rents-generate.log 2>&1
```

Les suspensions temporaires expirent à la minute près. Garder la commande
documentée dans [suspensions.md](suspensions.md), exécutée chaque minute. Ces
deux tâches doivent tourner sur un seul ordonnanceur actif par environnement ;
la génération des loyers est idempotente, mais éviter les doublons d'exécution
facilite l'exploitation et la lecture des journaux.
