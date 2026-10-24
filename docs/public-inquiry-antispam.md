# Configuration anti-spam des demandes publiques

## Développement

La configuration locale utilise `LOCK_DSN=flock`, laisse Turnstile désactivé et autorise le secret HMAC à reprendre `APP_SECRET` uniquement en `dev`. Le navigateur affiche alors une note, et l’API garde le honeypot, le jeton signé, son expiration et les limiteurs.

## Production multi-instance

Chaque déploiement doit fournir ses propres variables hors du dépôt :

- `PUBLIC_INQUIRY_HMAC_SECRET` : secret aléatoire d’au moins 32 octets, identique sur les instances d’une même application.
- `TURNSTILE_ENABLED=1`, `TURNSTILE_SECRET_KEY` et `TURNSTILE_EXPECTED_HOSTNAMES` : secret Cloudflare privé et liste exacte des domaines publics autorisés.
- `REDIS_URL` : serveur Redis partagé par toutes les instances pour le stockage des limiteurs et des jetons consommés.
- `LOCK_DSN=redis://hote-redis:6379?dbindex=1` : stockage de verrous partagé qui rend atomiques les consommations concurrentes. PHP doit avoir l’extension Redis active.
- `TRUSTED_PROXIES` : adresses/CIDR exacts des reverse proxies qui définissent `X-Forwarded-For`; ne jamais accepter arbitrairement les en-têtes d’un client public.

L’API refuse de démarrer en environnement autre que `dev` si Turnstile ou le secret HMAC de production manque. Les limiteurs et la consommation de jetons échouent fermés si Redis/verrous ne sont pas disponibles. Ne basculez pas `LOCK_DSN` vers `flock` en production multi-instance : ce verrou ne coordonne que les processus du même hôte.

Les clés publiques Turnstile (`VITE_TURNSTILE_SITE_KEY`) sont configurées dans MaVitrine avant `yarn build`. La clé secrète ne doit jamais entrer dans les variables `VITE_*`.

## Vérification

Exécuter les tests unitaires `php tests/verify-public-inquiry-challenge.php` et `php tests/verify-public-inquiry-turnstile.php`. Le parcours HTTP/Doctrine complet `php tests/verify-listing-workflow.php` ne fonctionne qu’avec `LISTING_TEST_DATABASE_URL` pointant vers la base isolée `gestion_himmo_security_test`; son script vérifie cette base puis annule sa transaction.
