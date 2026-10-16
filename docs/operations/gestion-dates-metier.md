# Dates métier et dates d’enregistrement

## Convention

- Une date métier décrit quand l’événement s’est produit. Elle peut être passée si la saisie est tardive.
- `createdAt` est l’horodatage technique de l’enregistrement et ne doit pas être remplacé par la date métier.
- `updatedAt` décrit une modification technique. L’identité métier de l’utilisateur provient du compte authentifié, jamais du corps envoyé par le navigateur.
- Les dates civiles (paiement, dépense, échéance, début/fin) sont stockées comme `DATE`; les événements techniques et d’audit utilisent des horodatages.

## Cartographie vérifiée

| Domaine / opération | Date métier | Enregistrement / auteur | Décision |
| --- | --- | --- | --- |
| Paiement de loyer | `Payment.paymentDate` | `createdAt`, `createdBy`, journal `CREATE_PAYMENT` | Date saisissable et rétroactive; la période et l’échéance du loyer restent distinctes. Les paiements validés sont corrigés par contre-écriture. Les rapports et filtres financiers utilisent `paymentDate`. |
| Échéance de loyer | `Rent.period`, `Rent.dueDate` | `createdAt` | Période locative et exigibilité restent séparées d’un paiement. Statut dérivé des écritures et de l’échéance. |
| Bail | `startDate`, `endDate`, `terminationDate` | `createdAt`, `updatedAt`, auteur d’audit | La résiliation accepte une date effective passée entre le début du bail et aujourd’hui; aucune date future n’est admise. |
| Dépense | `Expense.expenseDate`, périodes facultatives | `createdAt`, `createdBy`, journal d’audit | Date réelle modifiable selon les droits; rapports et trésorerie utilisent la date de dépense. Une contre-écriture porte sa propre date d’enregistrement comptable. |
| Intervention | `reportedAt`, `dueDate`, `completedAt` | `createdAt`, `createdBy`, `updatedAt`, journal d’audit | Le signalement et l’achèvement acceptent une date passée cohérente; une échéance prévue reste future ou présente. La transition conserve son horodatage technique séparément. |
| Affectation du personnel | `startDate`, `endDate` | `createdAt`, journal d’audit | Dates d’effet distinctes de la saisie; les conflits de période sont contrôlés par le service. |
| Solde d’ouverture de caisse | `openingDate` | `createdAt`, auteur d’audit | Date civile obligatoire et non future; elle ne remplace pas l’horodatage de modification du solde. |
| Contrat / occupation | Dates du bail | `createdAt`, auteur d’audit | Les dates d’entrée et de fin d’occupation sont celles du bail; aucune entité locataire séparée ne porte ces événements. |
| Taux de change | `effectiveFrom`, `effectiveTo` | enregistrement dédié | Validité métier du taux; à ne pas confondre avec l’heure de création de sa trace. |
| Publication interne | `scheduledAt`, `publishedAt`, `endsAt` | `createdAt`, `updatedAt`, créateur | Dates de programmation et de diffusion, sans impact sur les dates financières. |
| Création d’un bien / compte / organisation | pas de date d’événement distincte dans le modèle courant | `createdAt`, parfois `updatedAt` et audit | La création est l’événement; aucun champ métier supplémentaire n’est justifié. |

## Limites de traçabilité historique

Les anciens enregistrements conservent leurs valeurs déjà persistées. Le système ne peut pas reconstituer une date de saisie ou un auteur historique qui n’a jamais été stocké; aucune date métier n’est copiée artificiellement dans `createdAt`.

Les transitions de statut qui ne possèdent qu’une date calendaire ne stockent pas l’heure exacte de l’événement. L’achèvement d’une intervention conserve la date réelle déclarée et l’audit garde l’horodatage de la saisie.
