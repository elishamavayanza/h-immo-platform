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
- Le montant généré est le loyer mensuel enregistré sur le bail. La date
  d’exigibilité actuellement générée est le 5 du mois. Les échéances dont la
  date d’exigibilité dépasse la fin effective du bail ne sont pas comptées dans
  les loyers exigibles ou les arriérés des rapports.
- Une génération répétée ne modifie pas et ne supprime pas une échéance déjà
  créée.

**Décision métier encore requise :** le dépôt ne définit pas de règle validée
pour proratiser un début ou une fin de bail en cours de mois, ni pour appliquer
une modification de loyer à partir d’une date d’effet. En l’état, la commande
génère le montant mensuel entier pour le mois concerné. Les parties payées ne
peuvent plus être modifiées rétroactivement par l’API ; une régularisation
traçable doit être définie avant d’automatiser le prorata.

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
