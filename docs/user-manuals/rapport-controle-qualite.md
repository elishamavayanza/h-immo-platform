# Rapport de contrôle qualité des manuels Soft-IMMO

**Révision :** 1.3.9 — 19 octobre 2026
**Sources :** `catalog.json` et `tools/generate_user_manuals.py`  
**PDF concernés :** SUPER_ADMIN, PATRON, ADMIN_IMMOBILIER et ADMIN_VILLE.

## Révision 1.3.9 — Procédures guidées par rôle

- Les articles des quatre rôles donnent maintenant un parcours plus concret : menu à ouvrir, onglet ou liste à utiliser, vérifications avant confirmation et contrôle du résultat.
- Les guides Patrimoine distinguent la vue d’ensemble (« Villes », « Tous les biens ») des onglets de gestion des parcelles, bâtiments et unités. Les responsabilités de lecture seule du PATRON et les périmètres de l’ADMIN_VILLE restent explicites.
- Les guides des opérations locatives, financières, du personnel et de la maintenance détaillent les étapes et les contrôles utiles sans élargir les droits du rôle.
- Le lecteur web met en évidence « Où trouver cet écran » et permet d’ouvrir la page concernée dans un nouvel onglet. Les étapes sont présentées sous « Comment procéder ».
- La création d’organisation SUPER_ADMIN est corrigée : le parcours comporte quatre étapes — Identité, Capacités, Compte PATRON et Logo facultatif.
- Les quatre PDF et leurs pages de lecture ont été régénérés depuis le catalogue. Pagination : 19 pages (SUPER_ADMIN), 21 (PATRON), 25 (ADMIN_IMMOBILIER), 21 (ADMIN_VILLE).
- À cette révision, la génération PDF a réussi avec le runtime Python du workspace ; `yarn type-check` et `yarn build` ont réussi. Le contrôle visuel manuel des pages n’a pas été exécuté.

## Révision 1.3.5 — Capacités configurables par organisation

- Ajout de plafonds manuels et indépendants pour les villes, parcelles, bâtiments, unités et membres actifs, avec « Sans limite » représenté par une valeur nulle explicite.
- Les compteurs sont bornés à l’organisation, et les créations sont contrôlées côté service sous verrou MariaDB afin que des demandes simultanées ne dépassent pas le plafond.
- Les organisations existantes conservent leurs ressources et reçoivent des plafonds sans limite jusqu’à leur configuration par le SUPER_ADMIN. Une réduction ne masque ni ne supprime les données ; elle bloque uniquement les nouvelles créations concernées.
- Le PATRON et l’ADMIN_IMMOBILIER consultent l’usage global de leur organisation dans Paramètres → Organisation. L’ADMIN_VILLE ne reçoit pas ces compteurs globaux. Aucun paiement en ligne n’est introduit.
- Le choix de première livraison est la configuration directe par organisation ; un catalogue de modèles réutilisables reste reporté et n’est pas présenté comme disponible.
- Mise à jour du manuel partagé, des questions FAQ par rôle et des quatre PDF/pages du lecteur. Pagination : 17 pages (SUPER_ADMIN), 20 (PATRON), 21 (ADMIN_IMMOBILIER), 19 (ADMIN_VILLE).
- Vérifications exécutées : `yarn type-check`, `yarn build`, lint PHP des fichiers modifiés, `lint:container`, `doctrine:schema:validate` (mapping et base synchronisés), statut des migrations, JSON valide et `git diff --check`. La migration `Version20261018130000OrganizationCapacityLimits` a été appliquée sur la base de développement `gestion_himmo`.
- Génération réussie des quatre PDF avec le runtime Python du workspace ; examen visuel des pages contenant les nouveaux articles, sans débordement ni défaut de pagination manifeste. Les parcours métiers complets et les scripts de tests autonomes n’ont pas été exécutés dans cette révision.

## Révision 1.3.5 — Circuit des annonces par rôle

- La Vitrine est disponible à ADMIN_VILLE lorsque la capacité organisationnelle est activée et qu’une ville active lui est attribuée. Il prépare les annonces pour ses unités disponibles ; ADMIN_IMMOBILIER les contrôle et les publie ; PATRON consulte en lecture seule.
- Une annonce référence une unité du Patrimoine et réutilise sa galerie photo. Au moins une photo est exigée avant la soumission. La publication directe d’une unité a été remplacée par les transitions du workflow.

## Révision 1.3.4 — Autorisation de la vitrine par organisation

- Ajout d’un réglage d’autorisation de la vitrine à la fiche Organisation du SUPER_ADMIN. L’accès est désactivé par défaut et chaque changement est protégé par le rôle plateforme et enregistré dans l’audit d’organisation.
- Les comptes PATRON et ADMIN_IMMOBILIER ne voient le menu Vitrine que lorsque l’organisation active a reçu cette autorisation. L’ADMIN_VILLE ne reçoit pas ce menu.
- Les API de gestion des annonces, de configuration de la présentation et les routes publiques refusent ou masquent les données d’une organisation dont l’option est désactivée.
- Le manuel des rôles concernés précise que l’activation doit être accordée par le SUPER_ADMIN. Les quatre PDF et les images du lecteur web ont été régénérés depuis le catalogue.
- Vérifications exécutées : `yarn type-check`, lint PHP des fichiers modifiés, migration comprise, et contrôle des espaces dans les fichiers texte.

## Révision 1.3.3 — Couverture des menus et réglages à jour

- L’article « Publications » manquait au manuel SUPER_ADMIN alors que le menu est disponible dans l’administration plateforme. Il a été ajouté au catalogue partagé avec les étapes de rédaction, audience, catégorie, programmation, pièces jointes, publication et retrait.
- L’article précise que les comptes SUPER_ADMIN ne sont pas destinataires des publications ; les audiences disponibles concernent les rôles métier des organisations.
- Les articles « Profil et paramètres » des quatre rôles ont été réconciliés avec les réglages réellement disponibles : photo de profil, préférences d’affichage, notifications, raccourci installable et règles propres au rôle.
- Le contrôle de navigation vérifie désormais que chaque entrée de menu de la barre latérale possède un article de manuel pour son rôle. Vérification : 39 contrôles de navigation réussis.
- Régénération du manuel web, des quatre PDF et des images de pages du lecteur depuis le catalogue partagé, sans ajout de captures d’écran de l’interface.
- Vérifications exécutées : 216 contrôles PHP des contenus/PDF/images, 39 contrôles de navigation, JSON valide et `git diff --check`.
- Le FAQ contextuel a également été comparé aux routes et menus par rôle : 200 contrôles couvrent la présence de réponses, leur visibilité, les identifiants et la recherche. La réponse sur la photo de profil précise désormais que l’avatar du menu latéral est actualisé sans rechargement.
- Pagination actualisée : 17 pages (SUPER_ADMIN), 18 (PATRON), 20 (ADMIN_IMMOBILIER) et 19 (ADMIN_VILLE).

## Révision 1.3.2 — Alignement des PDF sur le manuel web

- Régénération des quatre guides depuis le catalogue partagé avec le lecteur web : mêmes écrans, étapes, champs à vérifier, résultats attendus et limites d’accès.
- Ajout d’une entrée de table des matières pour chaque action décrite sur les écrans, afin de retrouver directement une procédure.
- Ajout d’encadrés textuels par écran pour la préparation, la réussite et les erreurs, avec des pictogrammes vectoriels nets.
- Retrait des captures d’écran des quatre PDF et des références aux captures dans le catalogue. Seul le logo de couverture reste une image ; le contenu opérationnel est du texte sélectionnable.
- Conservation des images de pages générées depuis le PDF pour le lecteur web : elles sont des rendus du document, et non des captures de l’interface.
- Contrôles exécutés : génération des quatre PDF, extraction du texte, vérification de chaque écran et de chaque étape par rôle, 199 contrôles PHP sans échec, rendu de toutes les pages et revue visuelle des quatre documents.
- Pagination après enrichissement : 11 pages (SUPER_ADMIN), 17 (PATRON), 19 (ADMIN_IMMOBILIER) et 17 (ADMIN_VILLE).

## Modifications effectuées lors des révisions précédentes

- Remplacement de DejaVu Sans par Times New Roman dans les quatre documents, avec polices normale, grasse et italique intégrées.
- Mise à jour de la hiérarchie : couverture 27 pt, chapitres 18 pt, sous-titres 14 pt, texte 11,5 pt, légendes 9,5 pt, en-têtes et pieds de page 9 pt.
- Suppression des sauts de page forcés entre tous les modules afin d’éviter les grands blancs et de laisser le texte s’écouler naturellement.
- Reformulation des légendes pour ne pas garantir à tort que chaque capture masque les données personnelles.
- Conservation des instructions liées au rôle, du périmètre d’accès, des résultats attendus et des conseils de dépannage déjà présents dans le catalogue.
- Régénération des quatre PDF et des rendus de pages utilisés par le lecteur intégré.

## Vérifications exécutées

### Évolution du patrimoine (16 octobre 2026)

- Le catalogue a été actualisé pour décrire les niveaux configurables, les espaces imbriqués, les unités commerciales et résidentielles, les accès de parcelle/bâtiment partagés, les baux couvrant plusieurs espaces, les cotitulaires/occupants datés et l’historique résidentiel.
- Les réponses d’aide contextuelle ont été ajoutées aux pages Patrimoine et Locataires. Un départ effectif clôt le bail source; un transfert effectif clôt le bail source et active un bail brouillon de destination préparé au préalable. Le départ d’un cotitulaire clôt seulement sa période d’occupation.
- Les changements de modèle ont été migrés sur MariaDB de développement jusqu’à `Version20261016124000ResidentialMovementCorrectionReason` ; `doctrine:schema:validate` confirme que le schéma correspond aux entités.
- Vérifications exécutées : type-check et build Vite ; scripts Patrimoine (65), filtres locataires (6), manuels (145), mapping HTTP (8), documentation OpenAPI (8), schéma MariaDB (20), isolation inter-organisations (89, incluant transferts, départ effectif et individuel, correction/annulation d’un mouvement non réalisé, refus de modifier un événement réalisé, champs de logement obligatoires et isolation des mouvements entre locataires).
- Le générateur a régénéré les quatre PDF et les images des pages du lecteur depuis le catalogue. Les captures d’interface des modules proviennent des captures déjà présentes dans le dépôt ; aucune nouvelle session authentifiée n’a été disponible pour les refaire sur les écrans actuels.
- Le parcours authentifié Patrimoine → Goma → Parcelle de démonstration a été parcouru dans le navigateur. La parcelle, le bâtiment et les unités chargent depuis l’API. Ce parcours n’a pas modifié les données.

### Limites restantes avant validation complète des deux boucles

- Les mouvements non réalisés peuvent être modifiés ou annulés avec un motif obligatoire ; les valeurs précédentes et nouvelles sont enregistrées dans l’audit. Un mouvement déjà réalisé reste verrouillé contre une réécriture rétroactive. Une opération compensatrice complète pour corriger un transfert ou un départ déjà exécuté n’est pas encore disponible.
- Le patrimoine expose désormais l’activité commerciale et sépare l’occupation calculée (libre, occupée, partiellement occupée) de l’état opérationnel manuel (disponible, réservé, attente de remise, travaux, indisponible temporairement, libération, non destiné à la location). L’occupation n’est pas encore agrégée dans les lignes des bâtiments et niveaux. Son calcul par unité fait plusieurs requêtes relationnelles ; une agrégation par lot sera nécessaire si les catalogues deviennent volumineux.
- Les parcours existants permettent niveaux, espaces imbriqués, baux multi-espaces et accès partagés, mais il n’existe pas encore d’assistant guidé complet pour créer un bâtiment, ses niveaux, espaces et accès en une seule séquence. La restructuration contrôlée d’espaces déjà loués et le changement d’usage historique ne sont pas encore pris en charge comme opérations métier dédiées.
- Les occupants sont représentés individuellement par bail avec rôle et période, sans modèle de groupe/famille ni changement guidé de titulaire. La page Locataires filtre maintenant l’occupation actuelle, l’absence de bail actif, les logements multiples et les échéances à 30 jours ; restent les départs à confirmer et les mouvements récents ou saisis tardivement. Ces filtres restent partiels lorsque l’interface affiche son avertissement de liste tronquée.
- Les contrôles automatisés couvrent les cas principaux et l’isolation, mais pas encore toute la matrice de scénarios demandée pour chaque boucle, notamment les restructurations complexes, les occupations temporaires avec retour et les conflits concurrents de double soumission sur tous les types de mouvement.
- La page Locataires n’a pas été parcourue dans une session authentifiée pour les quatre rôles et aux largeurs mobile/tablette/ordinateur. Les captures incluses dans les manuels sont celles déjà présentes dans le dépôt ; elles n’ont pas été refaites à partir des écrans courants.

### Limites de validation documentaire

- Les quatre PDF et leurs images intégrées n’ont pas été régénérés à partir de nouvelles captures dans cette révision. Les captures précédentes ne peuvent donc pas être considérées comme une preuve visuelle des nouveaux formulaires, niveaux ou accès.
- Les actions de création et de modification n’ont pas été soumises dans le navigateur afin de ne pas ajouter de données permanentes à l’organisation de développement. Les scénarios backend utilisent les fixtures transactionnelles de `verify-tenant-isolation.php`, puis annulent leur transaction.
- La page Patrimoine a été contrôlée dans l’interface à 375, 768 et 1280 px, sans débordement horizontal. Le formulaire de mouvement locataire et les autres étapes complètes ne sont pas encore validés dans le navigateur à ces largeurs.

- Génération réussie des quatre PDF avec le Python et les bibliothèques PDF du runtime de l’espace de travail.
- Ouverture des PDF par `pypdf` lors de la génération ; les comptes de pages obtenus après la dernière génération sont : 10 (SUPER_ADMIN), 14 (PATRON), 16 (ADMIN_IMMOBILIER) et 14 (ADMIN_VILLE).
- Rendu initial de toutes les pages en images à 110 dpi, puis nouvel export du lecteur à 150 dpi (limité à 1800 px) avec une meilleure qualité JPEG. L’examen visuel des rendus a montré une pagination correcte, sans page entièrement blanche ni débordement manifeste.
- Extraction du texte et contrôle des polices : le contenu reste du texte PDF sélectionnable et Times New Roman est incorporée. Les accents et les caractères français sont lisibles dans les rendus examinés.
- Contrôle visuel des couvertures, tables des matières, pages de modules, légendes, pieds de page et pagination. La table des matières est générée à partir des titres et numéros de page du PDF.
- Connexion réellement vérifiée dans le navigateur pour les quatre rôles avec des comptes et des données synthétiques sur `gestion_himmo_security_test` : SUPER_ADMIN, PATRON, ADMIN_IMMOBILIER et ADMIN_VILLE. Les quatre tableaux de bord se sont ouverts en thème clair ; le tableau ADMIN_VILLE affichait le périmètre Bukavu.
- Contrôle de la console navigateur après ces parcours : aucune erreur console relevée. Ce contrôle ne couvre pas chaque action de chaque module.

## Constats des révisions antérieures à 1.3.2

Les points ci-dessous décrivent l’état antérieur à la présente révision. Les remarques sur les captures ne s’appliquent plus aux PDF régénérés en version 1.3.2.

## Points restant à traiter avant une validation complète des parcours métier

- Les 36 captures conservées dans les PDF n’ont pas toutes été reprises depuis l’application pendant cette révision. Les captures de SUPER_ADMIN, PATRON et ADMIN_VILLE sont encore en thème sombre ; celles d’ADMIN_IMMOBILIER sont en thème clair. Le critère « toutes les captures en thème clair » n’est donc pas satisfait.
- Les captures ne comportent pas encore de flèches, cercles ou numéros d’étapes vérifiés sur les vrais contrôles. Les légendes ne remplacent pas ces annotations.
- Les quatre connexions de démonstration fonctionnent désormais. Les rôles ont été vérifiés visuellement sur leur tableau de bord, mais tous les modules de chaque manuel n’ont pas été parcourus dans l’interface.
- Les captures préexistantes montrent parfois des listes vides, des données masquées ou des états de chargement. Les instructions textuelles ne doivent pas être interprétées comme une vérification récente de chaque bouton ou scénario.
- Les captures sources sont déjà compressées et floues ; l’agrandissement dans le PDF et l’augmentation de définition du lecteur ne recréent pas les détails perdus. Le contrôle détaillé à plusieurs niveaux de zoom de chaque capture source n’est pas attesté.
- Les captures SUPER_ADMIN, PATRON et ADMIN_VILLE restent en thème sombre (25 sur 36). Le navigateur de test permet de les afficher en thème clair, mais ne fournit pas de mécanisme autorisé pour enregistrer ses images de capture dans `output/pdf/screenshots/`. Les remplacer par des captures fabriquées ou simplement éclaircies ne représenterait pas des captures réelles de l’application.

En conséquence, cette révision améliore la typographie et la pagination et confirme la connexion des quatre rôles, mais ne satisfait pas encore le critère des captures réelles en thème clair pour tous les manuels. Il reste à exporter les captures des rôles concernés dans le dossier source, à annoter uniquement les éléments vérifiés, puis à régénérer et contrôler les quatre PDF.
