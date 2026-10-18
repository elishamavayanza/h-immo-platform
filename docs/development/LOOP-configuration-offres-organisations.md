# LOOP — Configuration flexible des offres et limites par organisation

## Mission

Faire évoluer Soft-IMMO pour que le SUPER_ADMIN puisse configurer, pour chaque
organisation, une capacité adaptée à sa taille et à son budget : nombre de
villes, parcelles, bâtiments, unités et comptes métier, ainsi que les options
fonctionnelles autorisées. Le PATRON et les administrateurs de l’organisation
doivent comprendre leur capacité, leur consommation et les limites qui
s’appliquent à leur périmètre.

Le but est de rendre la plateforme configurable pour des petites et grandes
entreprises, dans différents marchés. Les organisations qui ont une seule
ville ne doivent pas être obligées d’adopter une configuration conçue pour un
grand portefeuille. Les limites et options sont décidées par la plateforme et
attribuées par le SUPER_ADMIN, après échange direct avec le client.

## Règles de portée

- Implémenter uniquement la **configuration des offres, capacités et options**.
- La configuration des capacités et options est faite manuellement par le
  SUPER_ADMIN après échange avec le client.
- Ne pas intégrer de paiement en ligne, passerelle, facture automatique,
  prélèvement, panier, checkout, renouvellement ou gestion de moyens de paiement.
- Ne pas inventer des tarifs, devises commerciales, noms définitifs d’offres ni
  limites contractuelles. Les valeurs sont fixées par le SUPER_ADMIN.
- Ne pas supprimer, archiver ou rendre inaccessibles des données existantes
  lorsque la configuration est réduite.
- Le backend reste l’autorité pour les permissions et les limites. Masquer un
  bouton dans React ne constitue pas un contrôle.
- Le comportement métier existant doit être préservé hors des contrôles de
  capacité explicitement définis dans cette mission.

## État du projet à prendre en compte

Avant toute modification, relire `AGENTS.md` et vérifier le code réel. Le
projet utilise Symfony 7.4, Doctrine ORM 3, MariaDB 11, React 19 et TypeScript
strict. L’isolation multi-organisation est critique et `SecurityService` est
le point d’entrée des décisions d’autorisation.

Les rôles réels sont `SUPER_ADMIN` (plateforme), puis `PATRON`,
`ADMIN_IMMOBILIER` et `ADMIN_VILLE` (organisation). `ADMIN_VILLE` est en plus
borné par ses affectations `UserCity`. Il n’existe pas de paiement en ligne à
réutiliser ni de module commercial à supposer. L’organisation a déjà une
autorisation de vitrine `showcaseEnabled` : intégrer cette option dans la
configuration des capacités sans casser les autorisations et routes existantes.

Inspecter au minimum les entités, DTOs, services, repositories, contrôleurs,
`SecurityService`, formulaires et pages React d’organisation, les migrations,
le menu latéral, l’aide contextuelle, `docs/user-manuals/catalog.json`, le
générateur PDF et les scripts autonomes pertinents avant de choisir la forme
finale de la configuration.

## Modèle retenu pour cette première livraison

Configurer directement les capacités effectives sur chaque organisation. Le
SUPER_ADMIN fixe manuellement les plafonds et options à la création ou à la
modification de la fiche. Cette forme répond au besoin d’ajustement individuel
après échange avec le client, sans imposer un catalogue de forfaits ni figer un
nom commercial qui n’a pas encore été défini.

- Les cinq plafonds et l’autorisation de vitrine sont stockés sur
  l’organisation et audités avec les modifications de sa fiche.
- `null` signifie « sans limite ». Un plafond configuré est un entier positif ;
  zéro n’est pas utilisé comme valeur implicite.
- Une future évolution pourra ajouter des modèles réutilisables si la gestion
  d’un catalogue devient nécessaire. Elle devra conserver la configuration
  effective par organisation et demander confirmation avant de propager un
  changement de modèle.
- La vitrine réutilise `showcaseEnabled`, sa source de vérité existante. Les
  capacités ne donnent aucun rôle ni accès métier supplémentaire.

Ce choix est un écart volontaire par rapport au catalogue de modèles évoqué
initialement : il réduit le schéma et l’interface à la configuration manuelle
demandée, sans paiement en ligne ni tarification inventée.

## Capacités à configurer

Commencer par les quantités vérifiables directement dans le modèle de données :

- villes de l’organisation ;
- parcelles ;
- bâtiments ;
- unités ;
- comptes membres rattachés à l’organisation, tous rôles métier confondus.

Le compteur des comptes doit être défini sans ambiguïté : compter les
appartenances actives de l’organisation, et non tous les comptes globaux de la
plateforme. Documenter et appliquer une seule définition partout.

Les limites doivent accepter une valeur entière positive ou `sans limite` si
cette notion est utile. Les valeurs zéro ne sont permises que lorsqu’elles
signifient explicitement que la ressource ou l’option est désactivée. Valider
les bornes et les incohérences avant l’enregistrement.

Le stockage de médias ne doit pas devenir une limite commerciale dans cette
étape si l’application ne sait pas le mesurer de manière fiable. Ne pas
approximer le poids à partir du nombre de fichiers. Si cette métrique est
nécessaire, la traiter dans une évolution séparée après définition de son
périmètre et de sa méthode de calcul.

## Options fonctionnelles

Modéliser les options par capacités explicites, typées et contrôlées côté
serveur. Inclure au minimum l’autorisation de la vitrine existante, si celle-ci
peut être intégrée sans doublonner `showcaseEnabled` ni créer deux sources de
vérité. Examiner les autres fonctionnalités réellement présentes dans le dépôt
et proposer uniquement celles dont l’activation/désactivation est cohérente et
testable.

Ne pas créer des interrupteurs décoratifs. Pour chaque option, préciser les
routes, actions backend et éléments UI affectés. Garder les contrôles
d’appartenance organisationnelle indépendants des options commerciales : une
option active n’accorde jamais un rôle ou un accès à une autre organisation.

## Règles d’application des limites

- Refuser en backend la création qui ferait dépasser la capacité effective,
  avec une réponse métier explicite et un message lisible. Utiliser le code
  HTTP cohérent avec les conventions du projet et un format `Feedback`.
- Vérifier les limites dans le service métier transactionnel, en protégeant les
  créations concurrentes contre le dépassement par deux requêtes simultanées.
- Toutes les créations doivent utiliser les mêmes règles, quelle que soit
  l’origine de la requête (UI, API ou commande), sauf commande d’initialisation
  explicitement prévue pour les données système.
- Ne pas limiter les lectures existantes lorsqu’une organisation dépasse sa
  nouvelle limite. Ne pas empêcher la consultation ni les modifications
  ordinaires de ses ressources déjà enregistrées.
- Après réduction sous la consommation actuelle, afficher le dépassement,
  bloquer uniquement les nouvelles créations de la ressource concernée et
  expliquer comment contacter le SUPER_ADMIN. Ne pas choisir ni supprimer des
  données pour revenir sous la limite.
- Une suppression réelle libère une capacité seulement si le type de ressource
  et son statut de suppression sont définis ainsi de manière cohérente. Les
  lignes avec suppression logique doivent suivre explicitement la règle métier
  convenue ; ne pas compter différemment dans l’écran et le contrôle serveur.
- Une configuration manquante ou invalide ne doit pas accorder implicitement
  une capacité illimitée. Définir une règle de démarrage et de migration qui
  préserve l’activité des organisations existantes sans leur appliquer une
  réduction surprise.

## Expérience SUPER_ADMIN

Ajouter une interface plateforme pour :

- rechercher, créer et modifier une organisation avec ses capacités effectives ;
- régler ou retirer les cinq plafonds directement sur la fiche de l’organisation ;
- autoriser ou retirer la Vitrine existante ;
- consulter l’usage avant de réduire un plafond et expliquer les conséquences ;
- consulter l’usage courant et le dépassement éventuel avant de confirmer ;
- prévisualiser les conséquences d’une réduction sans effacer de données ;
- présenter explicitement les plafonds positifs et l’option « Sans limite » ;
- afficher une confirmation récapitulative avant d’enregistrer ;
- consulter les changements dans la traçabilité existante.

Seul le SUPER_ADMIN peut gérer les capacités effectives et les options d’une organisation. Le backend vérifie cette permission à chaque
mutation. Le SUPER_ADMIN ne reçoit pas pour autant un accès métier général aux
données de cette organisation.

## Expérience organisation

Afficher un espace « Offre et capacités » ou une section équivalente dans la
configuration de l’organisation, cohérente avec les pages et droits déjà
présents :

- nom de la configuration attribuée ;
- limites et consommation actuelle pour chaque ressource ;
- indication claire d’une capacité illimitée ou atteinte ;
- options activées et désactivées ;
- explication des conséquences d’une limite atteinte ;
- moyen de contacter la plateforme ou de demander un changement, sans paiement
  ni commande en ligne.

Le PATRON peut consulter la configuration effective et l’utilisation de son
organisation. L’ADMIN_IMMOBILIER peut les consulter si cela correspond au
périmètre actuel de ses paramètres. L’ADMIN_VILLE ne voit que les informations
nécessaires à son travail local et ne peut ni consulter les usages globaux
inutiles ni gérer des limites. Aucun rôle organisationnel ne peut modifier le
forfait ou les options attribuées.

L’usage global de villes, parcelles, bâtiments et unités peut être montré au
PATRON/ADMIN_IMMOBILIER ; l’ADMIN_VILLE ne doit pas voir de données de villes
hors de son affectation. Le comptage doit être calculé côté serveur, borné à
l’organisation et ne pas exposer les données d’une autre organisation.

## Migration et données existantes

- Ajouter une migration MariaDB séquentielle avec `up()` et `down()` ; ne jamais
  réécrire une migration déjà appliquée.
- Définir comment les organisations existantes reçoivent une configuration
  compatible avec leur usage actuel. Le déploiement ne doit ni bloquer leur
  activité ni supprimer/modifier leurs données.
- Vérifier les contraintes, les index et les associations Doctrine.
- Garder `showcaseEnabled` et les options équivalentes cohérents durant la
  migration ; ne pas maintenir deux interrupteurs indépendants pour la même
  fonctionnalité.
- Prévoir une stratégie de rollback qui ne supprime pas les données métier.

## Autorisation, isolation et audit

- Résoudre l’organisation depuis la ressource ciblée ou le contexte serveur
  validé, jamais depuis une valeur libre fournie par le client.
- Utiliser `SecurityService` et des actions explicites ; ne pas dupliquer la
  matrice de rôles dans un service secondaire.
- Borner les listes et agrégats par `organization_id`. Pour ADMIN_VILLE,
  respecter en SQL son périmètre `UserCity` quand des données individuelles
  sont nécessaires.
- Ne jamais faire confiance au plan, aux limites ou aux options reçus dans le
  corps d’une requête du navigateur.
- Auditer les créations/modifications de capacités et options sur une
  organisation ; expurger toute donnée personnelle ou secrète.
- Tester les accès avec deux organisations concurrentes et des comptes de
  plusieurs rôles. Changer un UUID, un paramètre ou le contexte ne doit jamais
  permettre de lire ou modifier la configuration d’un autre tenant.

## Documentation obligatoire dans la même livraison

Le chantier n’est pas terminé tant que l’aide n’est pas synchronisée avec le
comportement réellement livré.

1. Mettre à jour `docs/user-manuals/catalog.json`, source partagée du lecteur
   web et des PDF générés :
   - SUPER_ADMIN : configurer les plafonds et la vitrine sur la fiche d’organisation,
     vérifier les usages, comprendre les confirmations et l’audit ;
   - PATRON : consulter la configuration et l’usage, comprendre un blocage et
     contacter la plateforme ;
   - ADMIN_IMMOBILIER : expliquer précisément ce qui est visible selon ses
     droits ;
   - ADMIN_VILLE : expliquer ses limites de périmètre et les actions qu’il ne
     peut pas effectuer.
2. Mettre à jour `assets/react/features/shared/help/services/contextualHelpRegistry.ts`
   et les métadonnées d’aide contextuelle liées aux réglages, à la création de
   villes/parcelles/bâtiments/unités/comptes et aux erreurs de limite. Ajouter
   des questions sur la limite atteinte, la consultation d’usage, les demandes
   de changement et les conséquences d’une réduction.
3. Régénérer les quatre manuels PDF et leurs pages de contrôle selon le
   processus existant. Vérifier que le web et les PDF disent la même chose et
   ne décrivent aucun paiement en ligne.
4. Mettre à jour le rapport de contrôle qualité et la version du catalogue
   selon la convention en place.
5. Vérifier les liens, la visibilité par rôle, la recherche du FAQ et la
   cohérence des étapes/champs/résultats attendus avec l’interface réellement
   implémentée.

Le catalogue de modèles réutilisables est reporté à une évolution ultérieure ;
la première livraison n’en affiche pas et configure directement chaque
organisation. Ne pas documenter cette fonction comme livrée.

## Stratégie de réalisation

Réaliser le travail dans cet ordre, sans s’arrêter après une maquette ou un
simple masquage d’interface :

1. Cartographier les ressources, leurs statuts actifs/supprimés, les créations
   et les permissions réelles. Établir une matrice « limite → source de comptage
   → points de création → rôles → messages ».
2. Utiliser une configuration effective directement sur l’organisation ; garder
   les plafonds nullable, la vitrine existante et l’audit rétrocompatibles.
3. Implémenter entités, migration, DTOs validés, repositories de comptage,
   mapper(s), services et réponses Feedback.
4. Appliquer les contrôles de capacité dans chaque service de création concerné
   et appliquer les options dans les routes/actions concernées. Garantir le
   comportement concurrent et l’isolation multi-tenant.
5. Implémenter les écrans SUPER_ADMIN et le résumé de consultation par rôle,
   avec états chargement, succès, erreur, dépassement et absence d’attribution.
6. Couvrir règles et permissions avec les scripts PHP/TypeScript autonomes
   existants ou de nouveaux scripts suivant leurs conventions. Les tests ne
   doivent pas laisser de données et doivent couvrir le rollback.
7. Mettre à jour le manuel web, FAQ, les PDF, les pages générées et le rapport
   qualité dans le même lot.
8. Faire une vérification de bout en bout : type-check/build frontend, lint
   PHP/conteneur, validation du schéma MariaDB, tests des limites et de
   l’isolation, et comparaison visuelle du manuel/PDF.

## Cas de vérification exigés

- Une organisation peut recevoir une configuration petite, par exemple une
  ville, et une autre une configuration plus grande ou sur mesure.
- Le SUPER_ADMIN peut régler les capacités d’une organisation, mais ne peut pas
  atteindre ses ressources métier ordinaires par ce privilège.
- Un rôle métier ne peut ni altérer les capacités ni falsifier une limite via l’API.
- La dernière création disponible réussit ; la suivante échoue avec une
  explication claire et sans donnée partiellement créée.
- Deux créations concurrentes ne font pas dépasser la capacité effective.
- La réduction sous l’usage actuel conserve toutes les données, affiche le
  dépassement et bloque uniquement les nouvelles créations concernées.
- Une organisation A ne lit, ne modifie ni ne compte les ressources ou
  paramètres de B, y compris en changeant un UUID ou un filtre.
- Les compteurs et blocages respectent la définition choisie des ressources
  supprimées/inactives et des appartenances de comptes.
- Une option désactivée est refusée par l’API même si le client appelle la
  route directement ; une option activée n’accorde pas de permission de rôle.
- Les ressources organisationnelles déjà existantes restent disponibles après
  migration et attribution initiale.
- Le FAQ et le manuel décrivent fidèlement les cas ci-dessus pour chaque rôle.

## Critères de fin

Le LOOP est achevé uniquement si :

- les limites et options sont configurables par le SUPER_ADMIN ;
- les services backend appliquent réellement chaque limite et chaque option ;
- l’usage affiché est calculé au bon périmètre et concorde avec les refus API ;
- les réductions ne suppriment ni ne cachent les données existantes ;
- l’isolation, les autorisations et l’audit sont vérifiés ;
- les migrations et le schéma sont validés sur MariaDB ;
- le lecteur web, le FAQ, les PDF et le rapport qualité sont mis à jour dans le
  même changement ;
- aucun parcours ni texte ne laisse entendre qu’un paiement en ligne existe.

Ne confirmer la fin qu’après vérification de chaque critère. Si une décision
commerciale manque, conserver une configuration manuelle par le SUPER_ADMIN,
documenter la valeur provisoire et ne pas inventer un prix ou un contrat.
