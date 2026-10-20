# Règles financières réellement appliquées

Ce document décrit les calculs implémentés dans Soft-IMMO. Il distingue les
valeurs calculées par l’application des règles qui restent à valider par
l’entreprise ou son professionnel comptable. Il ne constitue pas une
certification comptable ou réglementaire.

## Échéances de loyer

- Une échéance est unique par bail et par période (`lease_id + period`). La
  contrainte MariaDB complète le contrôle du service ; relancer la génération
  ignore une période déjà créée.
- La commande générale traite les baux actifs. La commande ciblée refuse aussi
  un bail qui n’est pas actif.
- La première période est le mois civil du début du bail. La génération inclut
  le mois demandé et s’arrête au mois de fin contractuelle lorsqu’il existe.
- Les jours du bail sont comptés de façon inclusive. Un mois partiel est
  calculé au prorata du nombre réel de jours du mois civil :
  `loyer mensuel × jours couverts / jours du mois`. Les mois complets gardent
  le montant mensuel contractuel.
- Pour USD, le résultat est arrondi au centime selon la règle commerciale
  « demi au supérieur » (half-up), sans flottant. Exemple : 100 USD × 12 / 31
  donne 38,71 USD.
- Les échéances CDF de mois complet restent au montant contractuel. Le prorata
  CDF est bloqué jusqu’à validation de sa précision et de sa règle d’arrondi ;
  la commande refuse la génération avant toute écriture si son calendrier
  contient un mois partiel CDF.
- Le stockage et les formulaires actuels autorisent deux décimales pour CDF,
  mais cela ne prouve pas que les contrats ou la caisse utilisent des fractions
  de franc. Proposition à faire valider avant activation : conserver les deux
  décimales déjà stockées et arrondir half-up au centime CDF.
- Le jour d’exigibilité habituel reste le 5 du mois. S’il tombe avant le début
  couvert du bail, la date est reportée au début ; s’il tombe après la fin
  couverte, elle est avancée à cette fin. Ainsi, l’échéance reste dans la
  période contractuelle. Aucune clause de jour d’exigibilité différente n’est
  portée par les champs structurés du bail.
- Une génération répétée ne modifie pas et ne supprime pas une échéance déjà
  créée.

**Règles restant à confirmer :** le modèle permet de saisir des conditions
libres, mais n’expose pas de calendrier d’exigibilité ni de mécanisme de
révision de loyer avec date d’effet. Une clause écrite incompatible doit primer
et faire l’objet d’un traitement explicite avant génération. Les échéances
déjà créées ne sont jamais recalculées ; une correction après facturation doit
passer par une régularisation traçable.

## Paiements et soldes des échéances

- `amount` et `currency` représentent le montant reçu et sa devise réelle.
- Pour un paiement dans une devise différente de celle du loyer, le taux et le
  montant d’origine sont obligatoires. Le montant d’origine et sa devise
  représentent la part imputée sur le loyer ; c’est cette valeur qui réduit le
  solde de l’échéance.
- Le taux transmis est conservé sur l’écriture. Les paiements ne peuvent pas
  dépasser le solde restant, calculé en devise de l’échéance, avec des
  comparaisons décimales exactes.
- `solde restant = montant de l’échéance - somme des montants imputés`. Les
  paiements partiels sont cumulés. Une échéance est soldée lorsque le cumul
  atteint son montant ; son état est recalculé à chaque encaissement ou
  contre-écriture.
- Une annulation ajoute une écriture négative liée à l’originale. Elle conserve
  le montant reçu, sa devise, le taux et le montant d’origine avec leurs signes
  inversés. L’écriture originale reste inchangée.
- Les rapports de loyers encaissés regroupent les paiements dans la devise du
  loyer (montant d’origine lorsque la devise a été convertie). Le journal de
  paiements affiche le montant et la devise réellement reçus.
- Une conversion vers USD applique également l’arrondi half-up au centime.
  Les montants CDF convertis conservent le comportement existant à deux
  décimales jusqu’à validation de leur convention ; ce résultat ne doit pas
  être interprété comme une décision métier d’arrondi CDF.

## Retards et arriérés

- Une échéance est échue pour un rapport à la date de référence si
  `dueDate < date de référence`. Une échéance due le jour même n’est pas encore
  en retard.
- `arriéré = montant dû - paiements imputés à la date de référence`.
- Une échéance dont le solde est nul ou négatif ne contribue pas aux arriérés.
- Les paiements dont la date métier est postérieure à la date de référence du
  rapport ne réduisent pas l’arriéré historique.
- Les échéances liées à un bail expiré ou résilié restent prises en compte ;
  une clôture de bail ne supprime pas une créance déjà générée.

## Dépenses, recettes affichées et caisse

- Les dépenses et paiements sont agrégés séparément par date métier et devise.
- Les contre-écritures diminuent les agrégats dans la période de leur propre
  date métier.
- Le résumé de rapport distingue les loyers exigibles (échéances générées),
  les loyers encaissés (paiements nets) et les dépenses enregistrées.
- Le résultat affiché comme « revenus moins dépenses » est un indicateur
  opérationnel dérivé des données saisies. Il ne représente pas un résultat
  comptable certifié.
- Le solde de caisse est calculé à partir du solde d’ouverture et des mouvements
  en espèces, du jour d’ouverture à la date de référence incluse. La lecture
  courante utilise aujourd’hui ; `GET /api/v1/treasury/cash-balance` accepte
  aussi `asOfDate=AAAA-MM-JJ` pour reconstruire un solde passé ou futur à partir
  des dates métier. Une référence antérieure au solde d’ouverture est refusée.
  Ce n’est pas un solde bancaire.

## Paiements après clôture d’un bail

- Une échéance existante demeure payable après expiration ou résiliation si sa
  date d’exigibilité est comprise dans la période effective du bail.
- Le service refuse les paiements liés à un bail brouillon ou annulé, ainsi
  qu’une échéance dont la date dépasse la plus proche des dates de fin et de
  résiliation.
- La création manuelle d’une échéance est réservée à un bail actif. La commande
  de génération automatique conserve la même règle ; une clôture ne crée donc
  pas de nouvelle dette après la période effective.

## Devises et rapports

- Les montants de devises différentes ne sont pas additionnés directement.
  Les rapports qui consolident dans la devise de référence recherchent un taux
  applicable à la date métier et à la portée organisation/ville ; ils essaient
  ensuite le taux inverse. La date disponible pour les paiements et dépenses
  est une date civile, sans heure.
- Si aucun taux historique direct ou inverse n’est disponible, le rapport
  financier refuse la conversion au lieu de présenter le montant d’une devise
  comme s’il était exprimé dans l’autre.
- Pour un arriéré, le modèle ne fige pas de taux de change sur l’échéance. Sa
  conversion de présentation utilise le taux historique applicable à la période
  de l’échéance. Cette convention doit être validée pour l’usage comptable.

## Dépôts de garantie

Le bail conserve un montant de garantie **prévu au contrat**. Le modèle ne
dispose pas d’un cycle opérationnel dédié prouvant l’encaissement, les retenues,
le remboursement et le solde détenu. Ce montant ne doit donc pas être interprété
comme une somme effectivement reçue ou comme un revenu. L’ajout de ce suivi
nécessite une validation du processus attendu et des justificatifs requis.

## Rapports, exports et conformité

Les rapports d’API et leurs exports PDF exposent les indicateurs opérationnels
disponibles. Aucun export CSV comptable standardisé, grand livre, balance,
journaux débit/crédit ou clôture comptable complète n’est implémenté ici. Un
format d’export comptable spécifique devra être défini avec le logiciel cible
et le professionnel responsable. Ce document ne revendique pas de conformité
SYSCOHADA/AUDCIF, IFRS ou autre référentiel.
