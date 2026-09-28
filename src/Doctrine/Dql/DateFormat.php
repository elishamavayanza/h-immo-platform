<?php

declare(strict_types=1);

namespace App\Doctrine\Dql;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Rend `DATE_FORMAT()` utilisable en DQL.
 *
 * Le DQL n'expose que les fonctions déclarées par Doctrine : une fonction
 * SQL écrite directement dans un `SELECT` fait échouer le parsing avec
 * « Expected known function ». Les agrégats de rapport
 * (`ExpenseRepository::getFinancialSummary()` et
 * `PaymentRepository::getFinancialSummary()`) ont besoin de grouper par
 * mois, ce que seul le SGBD sait faire.
 *
 * Cette fonction ne traduit donc pas l'appel : elle le transmet tel quel,
 * en renvoyant les arguments déjà rendus en SQL par `SqlWalker`. C'est un
 * choix assumé — le DQL devient dépendant de MariaDB pour cette
 * expression. L'alternative portable serait de ramener une date brute et de
 * formater le mois en PHP, au prix d'un regroupement à reconstruire à la
 * main, ce qui déplace l'erreur plutôt que de la résoudre.
 */
final class DateFormat extends FunctionNode
{
    private Node|string|null $dateExpression = null;

    private Node|string|null $formatExpression = null;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->dateExpression = $parser->StringPrimary();

        $parser->match(TokenType::T_COMMA);

        $this->formatExpression = $parser->StringPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf(
            'DATE_FORMAT(%s, %s)',
            $this->dateExpression->dispatch($sqlWalker),
            $this->formatExpression->dispatch($sqlWalker),
        );
    }
}
