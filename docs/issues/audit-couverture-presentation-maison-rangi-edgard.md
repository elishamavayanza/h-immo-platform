# Issue — Écarts entre le concept Maison Rangi-Edgard et Soft-IMMO

**Type :** audit fonctionnel / backlog produit  
**Priorité proposée :** P2 (compléter les besoins documentés après validation métier)  
**Source :** présentation « Gestion numérique du patrimoine immobilier — Concept proposé pour la Maison Rangi-Edgard », diapositives 1 à 17.  
**État :** écarts applicatifs prioritaires complétés ; vérification MariaDB/API en attente d’un environnement de base accessible.

## Constat

Le dépôt couvre déjà l’essentiel du parcours immobilier et financier de la présentation : organisation multi-villes, patrimoine hiérarchique, locations, paiements, dépenses, rapports, rôles et isolation. Le document est toutefois une proposition initiale, pas une spécification métier à appliquer sans arbitrage. Plusieurs éléments y sont absents ou moins détaillés dans l’application, et deux règles de rôles doivent être lues selon les décisions projet plus récentes.

## Comparaison des besoins

| Besoin de la présentation | Couverture observée dans le dépôt | État |
| --- | --- | --- |
| Villes, parcelles, immeubles et unités reliés en hiérarchie (diapos 3–7) | Entités et services Property dédiés, écrans de patrimoine, navigation et carte des parcelles ; fiches et opérations CRUD disponibles selon rôle. | Couvert |
| Informations de parcelle, superficie, adresse, observations, photos (diapo 5) | Parcelle avec données de localisation et médias photo. Le service d’upload accepte des images ; aucun dépôt de documents génériques/PDF n’a été trouvé. | Partiel : photos oui, documents non |
| Catégories d’immeubles et types d’unités (diapos 6–7) | `BuildingType` et `UnitType`, formulaires et vues de patrimoine. | Couvert ; vérifier la correspondance métier des catégories de la présentation lors de la recette |
| Locataire, unité, bail, dates, loyer, caution, historique (diapo 8) | Entités Tenant/Lease/Rent/Payment et écrans locataires/baux ; historique de locations et paiements rattachés aux données locatives. | Couvert pour les flux présents |
| Loyers attendus, payés, soldes, retards, avances, historique des paiements (diapo 9) | Échéances et paiements, liste dédiée des retards, annulation par contre-écriture ; les états d’impayé sont calculés à la lecture. | Couvert, avec règles comptables du dépôt |
| Dépenses : salaires, sentinelles, propriétés, taxes (diapos 2, 10–11) | Dépenses catégorisées, personnel/affectations et rapports de dépenses. | Couvert fonctionnellement, à valider pour chaque catégorie métier et devise |
| Tableau de bord du Patron : patrimoine, occupation, loyers, impayés, dépenses (diapo 11) | Dashboard Patron construit à partir du rapport d’organisation. | Couvert ; les indicateurs d’alerte « contrats à échéance » ne sont pas établis comme bloc dédié |
| Alertes de loyers en retard, contrats à échéance et unités disponibles (diapo 11) | Impayés et occupation restent visibles ; les baux actifs qui prennent fin dans les 30 prochains jours apparaissent dans les dashboards et rapports des trois rôles organisationnels. | Couvert pour les échéances proches ; unités disponibles déjà reflétées par l’occupation |
| Rapports PDF listés un par un (diapo 12) | Exports PDF de rapports consolidés par rôle (Patron, Admin immobilier, Admin ville) avec périodes et certains filtres. | Partiel : catalogue spécifique (locataire, unités libres/occupées, historique d’un locataire, etc.) non confirmé comme exports autonomes |
| Recherche transversale ville → parcelle → immeuble → unité → locataire (diapo 13) | Recherche globale dans l’espace connecté, composée des endpoints de listes existants et ouvrant les niveaux du patrimoine ou les locataires. Les permissions et filtres backend existants restent appliqués. | Couvert |
| Comptes, droits par profil et traçabilité (diapo 14) | Authentification JWT, permissions backend, journal d’audit et tests d’isolation existent. L’audit métier est limité aux rôles définis dans la politique : ADMIN_VILLE n’a pas accès au journal d’audit. | Couvert selon la politique actuelle, qui diffère du souhait générique de la présentation |
| Sauvegardes régulières / disponibilité à distance (diapo 14) | Pas de preuve suffisante dans le code applicatif pour confirmer la politique de sauvegarde ou la disponibilité en production. | À vérifier côté infrastructure/exploitation |
| Photos, documents, maintenance et statistiques avancées comme évolution (diapo 16) | Photos de parcelles/bâtiments/unités présentes ; catégorie de dépenses maintenance existe. Aucun workflow de maintenance ni dépôt documentaire générique identifié. | Partiel ; les autres éléments sont explicitement des évolutions proposées |

## Arbitrages métier à préserver

- La présentation parle d’un profil « Administrateur » qui ajoute des villes. Le modèle actuel distingue `ADMIN_IMMOBILIER` et `ADMIN_VILLE` : l’ADMIN_VILLE agit uniquement sur les villes qui lui sont attribuées, et ne crée pas de villes. Ne pas élargir son périmètre pour reproduire le vocabulaire ancien du document.
- Le Patron consulte et pilote les écrans opérationnels en lecture seule. Les écritures qui lui sont propres concernent l’administration de l’organisation et la vitrine ; ne pas ajouter d’actions CRUD de patrimoine ou de paiements pour le Patron sans décision métier formelle.
- Les affectations du personnel sont distinctes de la création de comptes utilisateurs. Respecter les permissions et matrices de rôle actuelles ; ne pas inventer un rôle « employé » ou des tables supplémentaires pour satisfaire le vocabulaire de la présentation.
- Les paiements sont des traces comptables non modifiables/supprimables ; toute annulation passe par une contre-écriture.

## Travail proposé

1. Définir avec le métier le catalogue exact d’exports PDF autonomes ; les exports consolidés existants restent disponibles.
2. Valider le seuil d’alerte de 30 jours et les éventuels destinataires ; l’alerte est actuellement informative dans les dashboards et rapports.
3. Si les documents génériques restent requis, définir formats, droits, rétention et limites avant une extension privée des photos. Aucun document ne doit être placé dans le répertoire public des médias.
4. Vérifier la sauvegarde et la restauration dans l’environnement de déploiement ; cette exigence relève de l’exploitation.
5. Exécuter les recettes API et d’isolation sur une base MariaDB de test accessible.

## Compléments réalisés

- Recherche globale ajoutée au layout connecté : villes, parcelles, bâtiments, unités et locataires, avec attente, résultats vides et erreurs réseau. Elle compose uniquement les endpoints filtrés existants ; les résultats peuvent être ouverts directement.
- Alertes de fin de bail ajoutées aux dashboards et rapports Patron, ADMIN_IMMOBILIER et ADMIN_VILLE. La requête SQL/DQL borne l’organisation et les villes avant chargement. Le seuil actuel est de 30 jours.
- Le script `tests/verify-admin-ville.php` vérifie maintenant l’inclusion d’un bail de la ville attribuée et l’exclusion des baux d’une autre ville et organisation.
- L’export PDF existant n’a pas été remplacé ni élargi en export de documents ; les nouveaux champs sont disponibles dans les données de rapport.

## Critères d’acceptation de l’audit

- Chaque fonctionnalité de la présentation est classée comme couverte, partielle, absente ou dépendante de l’infrastructure.
- Les écarts sont validés par le métier avant de devenir des exigences de développement.
- Toute évolution conserve l’isolation `Organization` / `UserCity`, les rôles et les invariants financiers du projet.
- La couverture validée dispose de tests backend/frontend adaptés et d’une recette des parcours par rôle.

## Références du dépôt consultées

- Backend : `src/Entity/{Property,Rental,Expense,Staff}`, `src/Controller/Api/{Property,Rental,Expense,Staff,Report,System}`, `src/Service/{Property,Rental,Expense,Staff,Report,System}`, `src/Security/SecurityService.php`.
- Frontend : `assets/react/features/{admin_immobilier,admin_ville,patron}` et leurs pages Patrimoine, Locataires, Loyers, Dépenses, Rapports, dashboards et Opérations/Personnel.
- Politique projet : `docs/decisions-patron.md`, `docs/decisions-admin-ville.md`, `tests/verify-tenant-isolation.php`, `tests/verify-admin-ville.php`, `tests/verify-admin-immobilier.php`.
- Présentation source : diapositives 2–16, notamment hiérarchie (3), patrimoine (5–8), finances (9), rôles (10), tableau de bord (11), rapports (12), recherche (13), sécurité et sauvegardes (14), feuille de route (16).
