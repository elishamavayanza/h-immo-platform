# Tests de vérification

PHPUnit n'est pas encore installé sur le projet. Les contrôles ci-dessous sont des
scripts PHP autonomes, exécutés dans l'environnement `dev` sur la base de
développement. Ils compensent ce manque et couvrent les points exigés par la
section 15 de `CONTRIBUTING.md`, notamment les contrôles d'autorisation et
l'isolation entre organisations.

Chaque script :

- annule la transaction qu'il ouvre, afin de ne laisser aucune donnée de test ;
- retourne un code de sortie `0` en cas de succès et `1` sinon ;
- affiche le nombre de contrôles exécutés et le nombre d'échecs.

## Exécution

```bash
php tests/verify-tenant-isolation.php
php tests/verify-http-mapping.php
php tests/verify-auth.php
php tests/verify-api-doc.php
php tests/verify-super-admin.php
php tests/verify-password-reset.php
php tests/verify-api-token.php
php tests/verify-mariadb.php
php tests/verify-p0-security.php
php tests/verify-p0-7-list-endpoints.php
php tests/check-injected-dependencies.php
node tests/verify-reset-password-form.ts
```

Les onze premiers scripts s'exécutent avec PHP. Le dernier est un script
Node : il vérifie la logique pure du formulaire React
(`assets/app/password-form.ts`), que Node 24 exécute nativement sans
transpiler. Il exige l'API démarrée sur le port 8000 uniquement pour
confronter ses contraintes de longueur au schéma OpenAPI ; sans elle, ce
contrôle est ignoré et les autres restent exécutés.

## Contrôles

| Script | Contrôles |
| --- | --- |
| `verify-tenant-isolation.php` | Portée des données à l'Organization duPATRON pour les repositories, les services et les journaux d'audit, y compris un PATRON portant plusieurs Organizations. |
| `verify-http-mapping.php` | Cohérence des verbes HTTP, des codes de statut et des routes entre contrôleurs et services. |
| `verify-auth.php` | Authentification par jeton : login, refus des mauvais identifiants, absence d'énumération de comptes, absence de cookie émis, réutilisation du jeton, déconnexion avec révocation, perte d'accès immédiate à la désactivation du compte, 401 anonyme et limitation des tentatives. |
| `verify-api-doc.php` | Génération de la spécification OpenAPI : classes de modèles résolues, paramètres de requête, corps de requête et réponses référencées. |
| `verify-super-admin.php` | Amorçage de la plateforme : création du compte `SUPER_ADMIN` par défaut, hachage du mot de passe, connexion réelle via `POST /api/auth/login` puis accès authentifié via le jeton obtenu, réinitialisation du mot de passe et garde-fous de la commande. |
| `verify-password-reset.php` | Flux « mot de passe oublié » complet : création du jeton, condensat SHA-256, expiration, usage unique, anti-énumération, refus des jetons expirés/inconnus/consommés et connexion avec le nouveau mot de passe. |
| `verify-api-token.php` | Émission du jeton (`accessToken`, `tokenType`, `expiresIn`, structure JWT en trois segments, en-tête `alg: HS256`) et contenu de ses revendications (`sub`, `jti`, `email`, `platformRole`, `roles`, `cityScope`, `organizations`, `exp`), puis son refus quand la signature, la charge utile ou l'expiration sont falsifiées, quand il est en `alg: none`, quand le compte a disparu, et après une déconnexion. Également : en-tête `WWW-Authenticate` et absence de trace d'exécution sur un 401, charge utile de `POST /api/auth/login` et `GET /api/auth/me` (rôles Symfony, rôles métier par Organization triés, villes accessibles avec exclusion des villes inactives, `cityScope` `platform`/`assigned`/`none`, horodatage ISO-8601), absence de fuite de secret, et schéma de sécurité `bearer` sans résidu de cookie. |
| `verify-reset-password-form.ts` | Formulaire de réinitialisation côté client : extraction du jeton depuis l'URL, validation des deux champs (mot de passe + confirmation), cohérence de la longueur minimale avec le schéma OpenAPI de l'API. |
| `verify-mariadb.php` | Cible SGBD : plateforme DBAL et serveur réellement MariaDB, base en `utf8mb4`, absence de PostgreSQL dans `compose.yaml`, `compose.override.yaml`, `.env` et `config/packages/doctrine.yaml`, présence de toutes les tables attendues, absence de version de migration orpheline, et exécution des deux agrégats mensuels qui s'appuient sur `DATE_FORMAT()`. |
| `verify-p0-security.php` | Les quatre correctifs P0, rejoués de bout en bout à travers le noyau et sur deux organisations concurrentes : (P0-1) rapports exigeant l'organisation visée et le rôle réellement détenu, y compris le compte à double appartenance ; (P0-2) cumul des dépenses borné aux villes du lecteur, sans fuite du chiffre d'une organisation concurrente ; (P0-3) médias refusés hors de leur organisation et chemins de traversée rejetés ; (P0-4) statut d'un loyer dérivé des paiements et de la date, statut d'un bail non saisissable et transitions Activate/Terminate/Cancel respectées. |
| `verify-p0-7-list-endpoints.php` | Les trois endpoints de liste de location (`GET /api/v1/payments`, `GET /api/v1/leases`, `GET /api/v1/rents/overdue`) appelés réellement à travers le noyau avec un `PATRON` sur deux organisations concurrentes : réponse 200 et non 500, présence de la donnée attendue, liste vide et refus en 403/404 lorsque `organizationId` ou l'UUID désignent l'organisation concurrente. Couvre les régressions de repositories non injectés, de DTO de filtre non importé, de `MapRequestPayload` sur un GET, de route masquée et de filtre `organizationId` hors périmètre. |
| `check-injected-dependencies.php` | Contrôle statique par réflexion : chaque accès `$this->…` d'un service désigne une propriété injectée par le constructeur ou une méthode héritée. Détecte sans exécuter de requête un repository ou une dépendance oublié dans un constructeur. |


## Prérequis

La base de développement doit être à jour (`php bin/console doctrine:migrations:migrate`)
et le cache Symfony être à jour (`php bin/console cache:clear`).

## Variables d'environnement requises

L'authentification est par jeton JWT signé en HS256. Deux variables doivent
être définies dans `.env.local` (jamais dans un fichier versionné) :

```bash
JWT_SECRET=$(php -r 'echo bin2hex(random_bytes(32));')
JWT_TTL=3600
```

`TokenManager` refuse de démarrer si `JWT_SECRET` est vide ou fait moins de
32 caractères : une clé absente rendrait les jetons forables, et il vaut
mieux une API en panne qu'une API compromise. `JWT_TTL` doit valoir au
moins 60 secondes.

La table `revoked_token` porte les jetons révoqués. Elle est purgée
d'elle-même : une ligne dont l'échéance est dépassée est inutile, le
jeton étant déjà refusé à la vérification.
