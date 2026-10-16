# Maintenance des manuels utilisateur

## Source de contenu

`catalog.json` est le catalogue commun aux quatre rôles réels : `super_admin`,
`patron`, `admin_immobilier` et `admin_ville`. Chaque entrée `modules` décrit
le chemin de menu, l’objectif, la procédure, les champs, le résultat attendu et
les limites d’accès. Le lecteur web et le générateur PDF lisent ce même
catalogue. Les captures utilisées par le PDF sont stockées sous
`output/pdf/screenshots/`.

Le contenu ne constitue jamais une autorisation. Pour décrire un nouveau bouton
ou une opération, vérifiez le menu du rôle dans
`assets/react/app/layout/MainLayout/sidebar/sidebar.config.ts`, la garde de
route, puis la règle backend dans `src/Security/SecurityService.php` et le
service concerné. N’écrivez pas une procédure pour une action non permise.

## Sélection du manuel et confidentialité

Le lecteur demande `GET /api/v1/user-manuals/current`. Le backend choisit le
catalogue à partir du compte authentifié et du contexte actif : `SUPER_ADMIN`
utilise son manuel plateforme ; les autres comptes doivent fournir un
`X-Organization-Context` valide et un rôle d’organisation actif vérifié par
`SecurityService::getOrganizationRole()`, qui tient également compte de
`X-Organization-Role`.

La réponse est privée et porte `Cache-Control: private, no-store`. À chaque
changement de compte, d’organisation ou de rôle, le client redemande le manuel.
La recherche et la navigation locale ne donnent aucun droit métier. Les routes
des fonctionnalités et les API continuent d'appliquer leurs propres gardes.
Le téléchargement appelle le même service de sélection et répond avec le PDF
du rôle actif en `Content-Disposition: attachment`.

## Ajouter ou modifier un article

1. Vérifier le libellé réel du menu, la route et l’icône du rôle concerné.
2. Modifier uniquement l’entrée correspondante dans `catalog.json`, conserver
   son identifiant stable `id`, et rédiger l’objectif, les étapes concrètes,
   les champs vérifiés, le résultat attendu et les restrictions confirmées dans
   le code.
3. Vérifier qu’aucune adresse, personne, donnée de test ou valeur secrète réelle
   n’est présente dans l’article ni dans l’image.
4. Mettre à jour la version et `updatedAt` du catalogue.
5. Exécuter les contrôles ci-dessous. Si le contenu PDF doit suivre la
   modification, vérifier les captures et lancer `python3
   tools/generate_user_manuals.py`; cette commande régénère les PDF et les pages
   JPEG de lecture de compatibilité.

## Vérifications

```bash
node tests/verify-user-manual-navigation.ts
php tests/verify-user-manuals.php
yarn type-check
yarn build
```

Le script Node contrôle les quatre rôles, les URL d’article, la recherche, les
groupes et la correspondance entre chaque article et le menu réellement
accessible. Le script PHP vérifie le catalogue, les articles, les PDF, les
captures et les pages de rendu conservées pour compatibilité.

Contrôle visuel du lecteur sur 320, 375, 768, 1024 et 1440 px, dans les thèmes
clair et sombre : navigation, sélection d’article, recherche, précédent/suivant,
ouverture du lien réel, téléchargement PDF, focus clavier et absence de
débordement horizontal. Tester séparément les quatre comptes de rôle et changer
de contexte sans recharger l’application.
