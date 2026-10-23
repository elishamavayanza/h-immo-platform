# Cycle de suivi des demandes de la vitrine

## Périmètre et rôles

Une demande publique est rattachée à une annonce publiée, donc à l’unité source du Patrimoine et à l’organisation qui possède cette unité. Le serveur relit ces relations pour chaque requête. `ADMIN_IMMOBILIER` gère la file et ses rendez-vous ; `PATRON` consulte les demandes et l’historique en lecture seule. `ADMIN_VILLE` n’accède pas aux coordonnées des prospects par ce parcours. La vitrine publique ne reçoit jamais le nom, le téléphone, l’e-mail ni le message du client dans son accusé de réception.

## Cycle d’une demande

`nouvelle → prise en charge → contact établi → visite proposée → visite réalisée → négociation → convertie`

Une demande peut aussi être clôturée avec un motif à partir des états de suivi autorisés. La date, les changements d’état, le responsable, les rendez-vous et leurs résultats sont conservés dans l’historique. Les rendez-vous sont des enregistrements distincts : proposition, report, confirmation, réalisation et annulation sont audités. Une demande ne peut pas être clôturée ni revenir au contact pendant qu’un rendez-vous reste ouvert.

## Rattachement au bail et disponibilité

Le suivi ne crée ni locataire, ni bail, ni échéance financière. La création et l’activation d’un bail restent dans le parcours Rental existant, avec ses validations, ses permissions et son contrôle d’exclusivité. Une demande en négociation peut ensuite être rattachée à un bail actif de la même organisation et de la même unité, uniquement si le téléphone du locataire correspond au téléphone de la demande. Ce rapprochement explicite évite d’associer le prospect au bail d’un autre occupant. Le lien est unique et idempotent.

À l’activation d’un bail, toute annonce publiée portant sur l’unité concernée est retirée dans la même transaction que l’activation, y compris lorsque l’unité est une unité supplémentaire du bail. Elle disparaît alors du catalogue public. La fin ou la résiliation du bail ne republie jamais l’annonce automatiquement : un rôle autorisé doit vérifier la disponibilité puis reprendre le circuit éditorial.

Les demandes de vente suivent les étapes de contact, visite et négociation, mais aucune transaction de vente ni aucun transfert de propriété n’est créé par ce module. Le terme « convertie » désigne uniquement le rattachement vérifié à un bail de location existant.

## Données et déploiement

Les entités `CustomerInquiry`, `CustomerInquiryHistory` et `CustomerVisit` sont isolées par organisation. Les coordonnées ne sont pas recopiées dans les journaux d’audit ; ceux-ci ne contiennent que des identifiants de ressource, des états et des horodatages. Les changements de schéma sont définis dans `Version20261023160000CustomerInquiries`. Cette migration doit être appliquée séparément après sauvegarde et validation de l’environnement ; elle n’a pas été exécutée pendant le travail.

Le parcours complet est couvert par `tests/verify-listing-workflow.php`, qui refuse toute base autre que `gestion_himmo_security_test` et annule sa transaction. Il doit être lancé uniquement lorsque cette base est dédiée et initialisée avec les migrations requises.
