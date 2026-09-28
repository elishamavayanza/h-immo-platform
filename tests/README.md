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
```

## Contrôles

| Script | Contrôles |
| --- | --- |
| `verify-tenant-isolation.php` | Portée des données à l'Organization duPATRON pour les repositories, les services et les journaux d'audit, y compris un PATRON portant plusieurs Organizations. |
| `verify-http-mapping.php` | Cohérence des verbes HTTP, des codes de statut et des routes entre contrôleurs et services. |
| `verify-auth.php` | Ouverture de session, cookie de session, accès authentifié, refus des comptes désactivés, 401 anonyme, limitation des tentatives, déconnexion et révocation de session. |
| `verify-api-doc.php` | Génération de la spécification OpenAPI : classes de modèles résolues, paramètres de requête, corps de requête et réponses référencées. |
| `verify-super-admin.php` | Amorçage de la plateforme : création du compte `SUPER_ADMIN` par défaut, hachage du mot de passe, connexion réelle via `POST /api/auth/login`, réinitialisation du mot de passe et garde-fous de la commande. |

## Prérequis

La base de développement doit être à jour (`php bin/console doctrine:migrations:migrate`)
et le cache Symfony être à jour (`php bin/console cache:clear`).
