# Rapport de contrôle qualité des manuels Soft-IMMO

**Révision :** 1.1.0 — 15 octobre 2026  
**Sources :** `catalog.json` et `tools/generate_user_manuals.py`  
**PDF concernés :** SUPER_ADMIN, PATRON, ADMIN_IMMOBILIER et ADMIN_VILLE.

## Modifications effectuées

- Remplacement de DejaVu Sans par Times New Roman dans les quatre documents, avec polices normale, grasse et italique intégrées.
- Mise à jour de la hiérarchie : couverture 27 pt, chapitres 18 pt, sous-titres 14 pt, texte 11,5 pt, légendes 9,5 pt, en-têtes et pieds de page 9 pt.
- Suppression des sauts de page forcés entre tous les modules afin d’éviter les grands blancs et de laisser le texte s’écouler naturellement.
- Reformulation des légendes pour ne pas garantir à tort que chaque capture masque les données personnelles.
- Conservation des instructions liées au rôle, du périmètre d’accès, des résultats attendus et des conseils de dépannage déjà présents dans le catalogue.
- Régénération des quatre PDF et des rendus de pages utilisés par le lecteur intégré.

## Vérifications exécutées

- Génération réussie des quatre PDF avec le Python et les bibliothèques PDF du runtime de l’espace de travail.
- Ouverture des PDF par `pypdf` lors de la génération ; les comptes de pages obtenus sont : 10 (SUPER_ADMIN), 14 (PATRON), 15 (ADMIN_IMMOBILIER) et 13 (ADMIN_VILLE).
- Rendu initial de toutes les pages en images à 110 dpi, puis nouvel export du lecteur à 150 dpi (limité à 1800 px) avec une meilleure qualité JPEG. L’examen visuel des rendus a montré une pagination correcte, sans page entièrement blanche ni débordement manifeste.
- Extraction du texte et contrôle des polices : le contenu reste du texte PDF sélectionnable et Times New Roman est incorporée. Les accents et les caractères français sont lisibles dans les rendus examinés.
- Contrôle visuel des couvertures, tables des matières, pages de modules, légendes, pieds de page et pagination. La table des matières est générée à partir des titres et numéros de page du PDF.
- Connexion réellement vérifiée dans le navigateur pour les quatre rôles avec des comptes et des données synthétiques sur `gestion_himmo_security_test` : SUPER_ADMIN, PATRON, ADMIN_IMMOBILIER et ADMIN_VILLE. Les quatre tableaux de bord se sont ouverts en thème clair ; le tableau ADMIN_VILLE affichait le périmètre Bukavu.
- Contrôle de la console navigateur après ces parcours : aucune erreur console relevée. Ce contrôle ne couvre pas chaque action de chaque module.

## Points restant à traiter avant une validation complète

- Les 36 captures conservées dans les PDF n’ont pas toutes été reprises depuis l’application pendant cette révision. Les captures de SUPER_ADMIN, PATRON et ADMIN_VILLE sont encore en thème sombre ; celles d’ADMIN_IMMOBILIER sont en thème clair. Le critère « toutes les captures en thème clair » n’est donc pas satisfait.
- Les captures ne comportent pas encore de flèches, cercles ou numéros d’étapes vérifiés sur les vrais contrôles. Les légendes ne remplacent pas ces annotations.
- Les quatre connexions de démonstration fonctionnent désormais. Les rôles ont été vérifiés visuellement sur leur tableau de bord, mais tous les modules de chaque manuel n’ont pas été parcourus dans l’interface.
- Les captures préexistantes montrent parfois des listes vides, des données masquées ou des états de chargement. Les instructions textuelles ne doivent pas être interprétées comme une vérification récente de chaque bouton ou scénario.
- Les captures sources sont déjà compressées et floues ; l’agrandissement dans le PDF et l’augmentation de définition du lecteur ne recréent pas les détails perdus. Le contrôle détaillé à plusieurs niveaux de zoom de chaque capture source n’est pas attesté.
- Les captures SUPER_ADMIN, PATRON et ADMIN_VILLE restent en thème sombre (25 sur 36). Le navigateur de test permet de les afficher en thème clair, mais ne fournit pas de mécanisme autorisé pour enregistrer ses images de capture dans `output/pdf/screenshots/`. Les remplacer par des captures fabriquées ou simplement éclaircies ne représenterait pas des captures réelles de l’application.

En conséquence, cette révision améliore la typographie et la pagination et confirme la connexion des quatre rôles, mais ne satisfait pas encore le critère des captures réelles en thème clair pour tous les manuels. Il reste à exporter les captures des rôles concernés dans le dossier source, à annoter uniquement les éléments vérifiés, puis à régénérer et contrôler les quatre PDF.
