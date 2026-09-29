<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Rental\Lease;
use App\Entity\Rental\Rent;
use App\Enum\LeaseStatus;
use App\Repository\Rental\LeaseRepository;
use App\Repository\Rental\RentRepository;
use App\Service\System\DateTimeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * GenerateRentsCommand
 *
 * Package : Rental Management — Commande de génération d'échéances
 *
 * Génère les échéances de loyer (Rent) pour tous les baux ACTIVE.
 * Idempotente grâce à la contrainte UNIQUE(lease_id, period) : une
 * échéance existante pour la même période n'est jamais recréée.
 *
 * La période couverte va du début du bail jusqu'au mois courant inclus
 * (ou jusqu'à --end-date si fourni).
 *
 * Usage :
 *   php bin/console app:rents:generate
 *   php bin/console app:rents:generate --end-date=2026-12-01
 *   php bin/console app:rents:generate --lease=uuid-du-bail
 *   php bin/console app:rents:generate --dry-run
 */
#[AsCommand(
    name: 'app:rents:generate',
    description: 'Génère les échéances de loyer pour les baux actifs (idempotent).'
)]
final class GenerateRentsCommand extends Command
{
    public function __construct(
        private LeaseRepository $leaseRepository,
        private RentRepository $rentRepository,
        private EntityManagerInterface $em,
        private DateTimeService $dateTime,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'end-date',
                null,
                InputOption::VALUE_REQUIRED,
                'Date de fin (premier jour du mois) au format Y-m-d. Défaut : mois courant.'
            )
            ->addOption(
                'lease',
                null,
                InputOption::VALUE_REQUIRED,
                'UUID d\'un bail spécifique à traiter (sinon tous les baux ACTIVE).'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Affiche ce qui serait fait sans persister.'
            )
            ->setHelp(<<<'HELP'
La commande <info>%command.name%</info> génère les échéances de loyer pour tous
les baux à l\'état ACTIVE, du début du bail jusqu\'au mois courant (ou
jusqu\'à <info>--end-date</info>).

Idempotence : la contrainte UNIQUE(lease_id, period) en base garantit qu\'une
échéance déjà existante pour une période donnée n\'est jamais recréée. La
commande peut donc être relancée sans risque (cron mensuel).

Options :
  <info>--end-date</info>   Mois de fin inclusif (ex: 2026-12-01). Défaut : mois courant.
  <info>--lease</info>       Traiter uniquement ce bail (UUID).
  <info>--dry-run</info>     Simule sans écrire en base.

Exemples :
  <info>php bin/console %command.name%</info>
  <info>php bin/console %command.name% --end-date=2026-12-01</info>
  <info>php bin/console %command.name% --lease=abc-123 --dry-run</info>
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        // Date de fin : mois courant par défaut
        $endDateStr = $input->getOption('end-date');
        if ($endDateStr !== null) {
            // Parsing strict : `--end-date=2026-13-45` ou `2026-3-5` est
            // refusé explicitement au lieu d'être normalisé silencieusement.
            $endDate = $this->dateTime->parseDate((string) $endDateStr);
            if ($endDate === null) {
                $io->error('Format de date invalide pour --end-date. Attendu : Y-m-d (ex: 2026-12-01).');
                return Command::FAILURE;
            }

            // Normaliser au 1er du mois
            $endDate = $endDate->modify('first day of this month');
        } else {
            $endDate = $this->dateTime->now()->modify('first day of this month');
        }

        $leaseUuid = $input->getOption('lease');

        // Récupérer les baux à traiter
        if ($leaseUuid !== null) {
            try {
                $parsed = \Symfony\Component\Uid\Uuid::fromString($leaseUuid);
            } catch (\InvalidArgumentException) {
                $io->error('UUID de bail invalide.');
                return Command::FAILURE;
            }
            $lease = $this->leaseRepository->findOneByUuid($parsed);
            if ($lease === null) {
                $io->error('Bail introuvable.');
                return Command::FAILURE;
            }
            $leases = [$lease];
        } else {
            $leases = $this->leaseRepository->findActiveLeases();
        }

        if ($leases === []) {
            $io->info('Aucun bail ACTIVE à traiter.');
            return Command::SUCCESS;
        }

        $io->title(sprintf('Génération des échéances pour %d bail(s) (jusqu\'au %s)', count($leases), $endDate->format('Y-m')));
        if ($dryRun) {
            $io->warning('Mode DRY-RUN : aucune écriture en base.');
        }

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($leases as $lease) {
            $created = $this->generateForLease($lease, $endDate, $dryRun, $io);
            $totalCreated += $created['created'];
            $totalSkipped += $created['skipped'];
        }

        $io->newLine();
        $io->definitionList([
            'Échéances créées' => (string) $totalCreated,
            'Échéances existantes (ignorées)' => (string) $totalSkipped,
            'Mode' => $dryRun ? 'DRY-RUN' : 'ÉCRITURE',
        ]);

        return Command::SUCCESS;
    }

    /**
     * Génère les échéances pour un bail donné.
     *
     * @return array{created: int, skipped: int}
     */
    private function generateForLease(Lease $lease, \DateTimeImmutable $endDate, bool $dryRun, SymfonyStyle $io): array
    {
        $startDate = $lease->getStartDate();
        $leaseEndDate = $lease->getEndDate();

        // La période effective de fin est le minimum entre la fin du bail et la date demandée
        $effectiveEndDate = $endDate;
        if ($leaseEndDate !== null && $leaseEndDate < $effectiveEndDate) {
            $effectiveEndDate = $leaseEndDate->modify('first day of this month');
        }

        // Si le bail commence après la date de fin effective, rien à faire
        if ($startDate > $effectiveEndDate) {
            $io->comment(sprintf('  Bail %s : début (%s) après fin effective (%s), ignoré.', $lease->getReference(), $startDate->format('Y-m'), $effectiveEndDate->format('Y-m')));
            return ['created' => 0, 'skipped' => 0];
        }

        $io->text(sprintf('  Bail %s (%s) : %s → %s', $lease->getReference(), $lease->getUuid()->toRfc4122(), $startDate->format('Y-m'), $effectiveEndDate->format('Y-m')));

        $created = 0;
        $skipped = 0;

        // Itérer mois par mois
        $current = $startDate->modify('first day of this month');
        while ($current <= $effectiveEndDate) {
            // Vérifier si l'échéance existe déjà
            $existing = $this->rentRepository->findOneByLeaseAndPeriod($lease, $current);

            if ($existing !== null) {
                ++$skipped;
                $current = $current->modify('+1 month');
                continue;
            }

            // Créer l'échéance
            // Date d'échéance = 5 du mois (configurable plus tard si besoin)
            $dueDate = $current->modify('+5 days');

            $rent = new Rent();
            $rent->setLease($lease);
            $rent->setPeriod($current);
            $rent->setDueDate($dueDate);
            $rent->setAmount($lease->getMonthlyRent());
            $rent->setCurrency($lease->getCurrency());
            // status = PENDING par défaut (entité)

            if (!$dryRun) {
                $this->em->persist($rent);
            }
            ++$created;

            $current = $current->modify('+1 month');
        }

        if (!$dryRun && $created > 0) {
            $this->em->flush();
        }

        $io->comment(sprintf('    Créées: %d, Ignorées (existantes): %d', $created, $skipped));

        return ['created' => $created, 'skipped' => $skipped];
    }
}