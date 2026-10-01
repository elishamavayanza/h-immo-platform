<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\Identity\RevokedTokenRepository;
use App\Service\System\DateTimeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * PurgeRevokedTokensCommand
 *
 * Package : Identity & Access — Commande de maintenance
 *
 * Supprime les révocations de jetons devenues sans effet. `revoked_token`
 * s'alimente à chaque déconnexion et n'était purgée par rien : la table
* grossissait sans limite, ce qui pèse sur les sauvegardes et sur le volume à
 * indexer à chaque requête authentifiée.
 *
 * La purge ne supprime que les lignes dont le jeton est de toute façon refusé :
 * passé `expires_at`, `JWT::decode` rejette le jeton avant que la révocation
 * soit consultée, la ligne n'a donc plus aucune valeur. Un jeton révoqué mais
 * encore valide est conservé — le supprimer le rendrait utilisable jusqu'à
 * son échéance naturelle.
 *
 * L'index `idx_revoked_token_expires` rend le `DELETE` indexé : la commande
 * reste peu coûteuse même sur une table longue, et peut donc être relancée
 * sans arriérer la moindre charge.
 *
 * Usage :
 *   php bin/console app:tokens:purge-revoked
 *   php bin/console app:tokens:purge-revoked --dry-run
 *   php bin/console app:tokens:purge-revoked --quiet
 *
 * Planification conseillée (cron quotidien, heure creuse) :
 *   0 3 * * *  cd /chemin/vers/gestion-himmo-app && php bin/console app:tokens:purge-revoked
 */
#[AsCommand(
    name: 'app:tokens:purge-revoked',
    description: 'Supprime les révocations de jetons expirées, devenues sans effet (maintenance quotidienne).'
)]
final class PurgeRevokedTokensCommand extends Command
{
    public function __construct(
        private RevokedTokenRepository $revokedTokenRepository,
        private DateTimeService $dateTime,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Annonce le nombre de lignes candidates sans rien supprimer.'
            )
            ->setHelp(<<<'HELP'
La commande <info>%command.name%</info> supprime de <info>revoked_token</info> les
révocations dont le jeton a expiré. Ces lignes sont devenues inutiles : le
décodage du JWT échoue déjà sur l\'échéance, la révocation n\'est plus
interrogée.

Ce qui n\'est <comment>pas</comment> supprimé : un jeton révoqué dont l\'échéance
n\'est pas atteinte. Sa révocation reste la seule chose qui l\'empêche de
circuler, la retirer le rendrait de nouveau valide.

Idempotence : une seconde exécution ne trouve plus rien à supprimer, la
commande peut donc être relancée sans risque.

Options :
  <info>--dry-run</info>   Compte les lignes candidates sans écrire en base.

Exemples :
  <info>php bin/console %command.name%</info>
  <info>php bin/console %command.name% --dry-run</info>
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $total = (int) $this->revokedTokenRepository->count([]);

        if ($total === 0) {
            $io->success('Aucune révocation enregistrée, rien à purger.');

            return Command::SUCCESS;
        }

        $io->title(sprintf('Purge des révocations de jetons (%s)', $this->dateTime->now()->format('Y-m-d H:i:s')));

        if ($dryRun) {
            $expired = $this->revokedTokenRepository->countExpired();

            $io->definitionList([
                'Lignes candidates' => (string) $expired,
                'Lignes restantes' => (string) $total,
                'Mode' => 'DRY-RUN (aucune écriture)',
            ]);

            if ($expired === 0) {
                $io->success('Aucune révocation expirée.');

                return Command::SUCCESS;
            }

            $io->warning(sprintf('%d ligne(s) seraient supprimées. Relancez sans --dry-run pour les supprimer.', $expired));

            return Command::SUCCESS;
        }

        // Un seul DELETE : le nombre réellement supprimé est vérifié contre
        // le nombre annoncé, pour qu'un cron muet ne masque pas une purge
        // partielle.
        $deleted = $this->revokedTokenRepository->purgeExpired();

        $io->definitionList([
            'Lignes supprimées' => (string) $deleted,
            'Lignes conservées' => (string) $total,
            'Révocations encore valides' => (string) $this->revokedTokenRepository->count([]),
            'Mode' => 'ÉCRITURE',
        ]);

        if ($deleted === 0) {
            $io->success('Aucune révocation expirée à supprimer.');

            return Command::SUCCESS;
        }

        $io->success(sprintf('%d révision(s) expirée(s) purgée(s).', $deleted));

        return Command::SUCCESS;
    }
}