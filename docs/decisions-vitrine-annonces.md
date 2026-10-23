# Décisions — Annonces de la vitrine immobilière

## D-VIT-1 — Une annonce décrit une unité du Patrimoine

Une annonce est liée à une `Unit` existante. La maison, la chambre, le studio,
l’appartement ou le local est représenté par le type de cette unité. La ville,
la parcelle et le bâtiment sont des éléments de contexte et ne deviennent pas
des annonces séparées.

Le modèle actuel ne permet pas de mettre un bâtiment entier en annonce. Si ce
besoin apparaît, il faudra définir une unité représentant l’ensemble et une
règle empêchant qu’une même surface soit proposée à la fois avec ses unités
filles.

## D-VIT-2 — Disponibilité et périmètre

La sélection des biens repose sur l’API et ne retourne que les unités actives,
opérationnellement disponibles, situées dans les villes accessibles à
l’appelant et sans bail actif. Un bail couvre son unité principale ainsi que
ses unités supplémentaires. Les annonces déjà en cours ne sont pas proposées
une seconde fois ; une annonce rejetée ou retirée peut être préparée à nouveau.

Pour `ADMIN_VILLE`, la file et la sélection des biens sont limitées à la ville
active dans le sélecteur d’espace. Le frontend transmet son UUID à l’API ; le
backend le recoupe avec les attributions `UserCity` avant de borner la requête.
Sans ville active, l’API refuse de fournir une file consolidée à ce rôle.

Le serveur répète les contrôles de disponibilité lors de la création du
brouillon, de la soumission, de la validation et de la publication. Une unité
qui devient occupée après la création du brouillon est donc bloquée à l’étape
suivante. Le filtrage frontend améliore la sélection ; il ne remplace jamais
les contrôles backend.

## D-VIT-3 — Galerie et couverture

Les photos appartiennent à la galerie de l’unité du Patrimoine. L’annonce
réutilise cette galerie et ne stocke pas une deuxième série de photos. La
première photo active (position 0) est la couverture. Une annonce doit avoir au
moins une photo active avant sa soumission ; cette condition est revérifiée au
contrôle et à la publication. L’ADMIN_VILLE gère les photos de ses biens et
l’ADMIN_IMMOBILIER peut les gérer dans son périmètre organisationnel.

## D-VIT-4 — Responsabilités et étapes

- `ADMIN_VILLE` crée et modifie les brouillons de ses villes, ajoute les photos
  à la galerie du bien et soumet l’annonce.
- `ADMIN_IMMOBILIER` contrôle l’annonce, demande des corrections ou la valide,
  puis la publie ou la retire. Il ne crée pas l’annonce à la place de la ville.
- `PATRON` consulte le circuit en lecture seule.
- Le SUPER_ADMIN active la capacité Vitrine pour l’organisation. L’ADMIN_VILLE
  doit aussi avoir au moins une ville active attribuée.

La route historique de publication directe est conservée pour répondre
explicitement aux anciens clients, mais refuse l’opération. Toute publication
passe par le workflow et son historique.
