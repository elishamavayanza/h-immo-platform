# Échéances de suspension

Les suspensions de comptes et d'organisations prennent effet immédiatement. Une échéance nulle signifie « indéterminée » ; une échéance fournie est stockée comme date-heure UTC.

La commande `php bin/console app:suspensions:expire` réactive les suspensions échues et trace chaque transition dans le journal d'audit. Pour une échéance appliquée à la minute, planifier la commande chaque minute sur chaque environnement applicatif connecté à la base :

```cron
* * * * * cd /chemin/vers/gestion-himmo-app && php bin/console app:suspensions:expire --env=prod --no-interaction >> var/log/suspensions.log 2>&1
```

La commande est idempotente : une seconde exécution ne réactive pas une organisation inactive ni un compte supprimé.
