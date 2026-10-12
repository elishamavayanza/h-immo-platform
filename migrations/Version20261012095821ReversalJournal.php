<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Journal d'écritures : contre-écritures négatives de paiement et de dépense.
 *
 * Ajoute `reversal_of_id` (auto-référence UNIQUE, ON DELETE RESTRICT) sur
 * `payment` et `expense`, plus une contrainte CHECK de signe : une écriture
 * normale est `>= 0`, une contre-écriture est `< 0`. Ainsi tous les agrégats
 * `SUM(amount)` restent justes sans filtrer les annulations.
 *
 * Les annulations créées par l'ancien code (montant POSITIF, référence
 * `ANNUL-<réf>` et aucun lien) sont converties en vraies contre-écritures.
 * Le contrôle d'appariement est fait AVANT tout DDL : MariaDB provoque un
 * commit implicite sur DDL, donc un `throw` après un `ALTER` laisserait la
 * migration à moitié appliquée. Si une annulation n'a pas d'écriture
 * d'origine univoque, on s'arrête sans rien modifier (ne rien deviner).
 *
 * Note : la branche « écriture normale » utilise `amount >= 0` et non `> 0`,
 * car `PaymentRequest`/`ExpenseRequest` acceptent aujourd'hui un montant nul
 * (regex `^\d{1,10}(\.\d{1,2})?$`). La garantie utile est la discipline de
 * signe, pas l'interdiction du zéro.
 */
final class Version20261012095821ReversalJournal extends AbstractMigration
{
    private const ANNUL_PREFIX = 'ANNUL-';

    public function getDescription(): string
    {
        return 'Ajoute reversal_of_id (contre-écritures négatives) sur payment et expense';
    }

    public function up(Schema $schema): void
    {
        // 1) Conversion des contre-écritures héritées : SELECT + validation
        //    d'unicité AVANT tout DDL. Les UPDATE sont seulement mis en file
        //    (addSql) et s'exécuteront après le retour de up().
        $this->prepareLegacyPaymentReversals();
        $this->prepareLegacyExpenseReversals();

        // 2) Schéma : colonne, FK, index unique, contrainte de signe.
        $this->addSql('ALTER TABLE expense ADD reversal_of_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA629A0BB4E FOREIGN KEY (reversal_of_id) REFERENCES expense (id) ON DELETE RESTRICT');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2D3A8DA629A0BB4E ON expense (reversal_of_id)');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT chk_expense_reversal_amount CHECK ((reversal_of_id IS NULL AND amount >= 0) OR (reversal_of_id IS NOT NULL AND amount < 0))');

        $this->addSql('ALTER TABLE payment ADD reversal_of_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D29A0BB4E FOREIGN KEY (reversal_of_id) REFERENCES payment (id) ON DELETE RESTRICT');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6D28840D29A0BB4E ON payment (reversal_of_id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT chk_payment_reversal_amount CHECK ((reversal_of_id IS NULL AND amount >= 0) OR (reversal_of_id IS NOT NULL AND amount < 0))');
    }

    public function down(Schema $schema): void
    {
        // Retire les CHECK d'abord, puis restaure les contre-écritures sous
        // leur ancienne forme (montant positif) avant de supprimer la colonne.
        $this->addSql('ALTER TABLE payment DROP CONSTRAINT chk_payment_reversal_amount');
        $this->addSql('ALTER TABLE expense DROP CONSTRAINT chk_expense_reversal_amount');

        $this->addSql('UPDATE payment SET amount = -amount WHERE reversal_of_id IS NOT NULL');
        $this->addSql('UPDATE expense SET amount = -amount WHERE reversal_of_id IS NOT NULL');

        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D29A0BB4E');
        $this->addSql('DROP INDEX UNIQ_6D28840D29A0BB4E ON payment');
        $this->addSql('ALTER TABLE payment DROP reversal_of_id');

        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA629A0BB4E');
        $this->addSql('DROP INDEX UNIQ_2D3A8DA629A0BB4E ON expense');
        $this->addSql('ALTER TABLE expense DROP reversal_of_id');
    }

    /**
     * Repère les contre-écritures de paiement de l'ancien code et prépare leur
     * conversion. Abandonne, sans avoir exécuté aucun DDL, dès qu'un cas n'est
     * pas parfaitement univoque.
     */
    private function prepareLegacyPaymentReversals(): void
    {
        $reversals = $this->connection->fetchAllAssociative(
            "SELECT id, rent_id, currency, reference FROM payment WHERE reference LIKE 'ANNUL-%'"
        );

        $claimed = [];
        $updates = [];
        foreach ($reversals as $reversal) {
            $originalReference = substr((string) $reversal['reference'], strlen(self::ANNUL_PREFIX));

            $candidates = $this->connection->fetchAllAssociative(
                "SELECT id FROM payment
                  WHERE rent_id = ? AND currency = ? AND reference = ?
                    AND reference NOT LIKE 'ANNUL-%' AND id < ?",
                [$reversal['rent_id'], $reversal['currency'], $originalReference, $reversal['id']]
            );

            $this->assertUnambiguous('paiement', $reversal['id'], $candidates);

            $originalId = $candidates[0]['id'];
            $this->claimOriginal('paiement', $originalId, $reversal['id'], $claimed);

            $updates[$reversal['id']] = $originalId;
        }

        foreach ($updates as $reversalId => $originalId) {
            $this->addSql(
                'UPDATE payment SET reversal_of_id = ?, amount = -amount WHERE id = ?',
                [$originalId, $reversalId]
            );
        }
    }

    /**
     * Idem pour les dépenses : l'appariement se fait sur organisation, ville,
     * catégorie, devise et référence d'origine.
     */
    private function prepareLegacyExpenseReversals(): void
    {
        $reversals = $this->connection->fetchAllAssociative(
            "SELECT id, organization_id, city_id, category, currency, reference FROM expense WHERE reference LIKE 'ANNUL-%'"
        );

        $claimed = [];
        $updates = [];
        foreach ($reversals as $reversal) {
            $originalReference = substr((string) $reversal['reference'], strlen(self::ANNUL_PREFIX));

            $candidates = $this->connection->fetchAllAssociative(
                "SELECT id FROM expense
                  WHERE organization_id = ? AND city_id = ? AND category = ? AND currency = ?
                    AND reference = ? AND reference NOT LIKE 'ANNUL-%' AND id < ?",
                [
                    $reversal['organization_id'],
                    $reversal['city_id'],
                    $reversal['category'],
                    $reversal['currency'],
                    $originalReference,
                    $reversal['id'],
                ]
            );

            $this->assertUnambiguous('dépense', $reversal['id'], $candidates);

            $originalId = $candidates[0]['id'];
            $this->claimOriginal('dépense', $originalId, $reversal['id'], $claimed);

            $updates[$reversal['id']] = $originalId;
        }

        foreach ($updates as $reversalId => $originalId) {
            $this->addSql(
                'UPDATE expense SET reversal_of_id = ?, amount = -amount WHERE id = ?',
                [$originalId, $reversalId]
            );
        }
    }

    /**
     * @param array<string, mixed> $claimed
     */
    private function claimOriginal(string $label, mixed $originalId, mixed $reversalId, array &$claimed): void
    {
        if (isset($claimed[$originalId])) {
            throw new \RuntimeException(sprintf(
                'Migration interrompue : la %s #%s est annulée par plusieurs contre-écritures (#%s et #%s), '
                . 'ce que la contrainte d\'unicité ne peut pas représenter. Aucune donnée n\'a été modifiée.',
                $label,
                $originalId,
                $claimed[$originalId],
                $reversalId
            ));
        }

        $claimed[$originalId] = $reversalId;
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     */
    private function assertUnambiguous(string $label, mixed $reversalId, array $candidates): void
    {
        if (count($candidates) === 1) {
            return;
        }

        if ($candidates === []) {
            throw new \RuntimeException(sprintf(
                'Migration interrompue : la contre-écriture %s #%s n\'a pas d\'écriture d\'origine identifiable. '
                . 'Aucune donnée n\'a été modifiée ; corrigez la donnée puis relancez.',
                $label,
                $reversalId
            ));
        }

        throw new \RuntimeException(sprintf(
            'Migration interrompue : la contre-écriture %s #%s correspond à %d écritures d\'origine. '
            . 'Aucune donnée n\'a été modifiée ; corrigez la donnée puis relancez.',
            $label,
            $reversalId,
            count($candidates)
        ));
    }
}
