<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Property\City;
use App\Entity\Rental\Tenant;
use App\Entity\System\DemoDataset;
use App\Enum\TenantType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Ajoute des locataires fictifs à un jeu démo déjà enregistré. */
#[AsCommand(name: 'app:demo:tenants', description: 'Ajoute des locataires fictifs à une organisation démo existante.')]
final class LoadDemoTenantsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('scenario', null, InputOption::VALUE_REQUIRED, 'Scénario du jeu démo : small ou complete.')
            ->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Seed du jeu démo existant.')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'Nombre de locataires fictifs supplémentaires (1–50).', '1')
            ->setHelp('Les adresses example.invalid et les données ajoutées sont déterministes. Répéter la même commande ne crée pas de doublons.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!in_array($this->environment, ['dev', 'test'], true)) {
            $io->error('La création de locataires démo est interdite hors de dev/test.');
            return Command::FAILURE;
        }

        $scenario = (string) $input->getOption('scenario');
        $seed = (string) $input->getOption('seed');
        $rawCount = (string) $input->getOption('count');
        if (!in_array($scenario, ['small', 'complete'], true) || !preg_match('/^[1-9][0-9]{0,9}$/', $seed)
            || !preg_match('/^(?:[1-9]|[1-4][0-9]|50)$/', $rawCount)) {
            $io->error('--scenario doit valoir small ou complete, --seed être positif et --count être entre 1 et 50.');
            return Command::INVALID;
        }
        $count = (int) $rawCount;

        $connection = $this->entityManager->getConnection();
        $database = (string) $connection->fetchOne('SELECT DATABASE()');
        if (!preg_match('/(?:_dev|_test|_demo)$/i', $database)) {
            $io->error(sprintf('Base refusée : « %s » doit finir par _dev, _test ou _demo.', $database));
            return Command::FAILURE;
        }

        $dataset = $this->entityManager->getRepository(DemoDataset::class)->findOneBy(['seed' => $seed, 'scenario' => $scenario]);
        if (!$dataset instanceof DemoDataset) {
            $io->error('Aucun jeu démo ne correspond à cette seed et ce scénario.');
            return Command::FAILURE;
        }
        $cities = $this->entityManager->getRepository(City::class)->findBy(
            ['organization' => $dataset->getOrganization()],
            ['name' => 'ASC'],
        );
        if ($cities === []) {
            $io->error('Le jeu démo ne contient aucune ville pour rattacher les locataires.');
            return Command::FAILURE;
        }

        $tenantRepository = $this->entityManager->getRepository(Tenant::class);
        $pending = [];
        for ($index = 1; $index <= $count; ++$index) {
            $email = sprintf('demo-locataire-%s-%02d@example.invalid', $seed, $index);
            $existing = $tenantRepository->findOneBy(['email' => $email]);
            if ($existing instanceof Tenant) {
                if ($existing->getOrganization() !== $dataset->getOrganization()) {
                    $io->error('Une adresse réservée est déjà rattachée à une autre organisation. Aucun locataire n’a été modifié.');
                    return Command::FAILURE;
                }
                continue;
            }
            $pending[] = (new Tenant())
                ->setOrganization($dataset->getOrganization())
                ->setCity($cities[($index - 1) % count($cities)])
                ->setType(TenantType::INDIVIDUAL)
                ->setFullName(sprintf('Locataire Démo Supplémentaire %02d', $index))
                ->setPhone(sprintf('+000000%05d', 50000 + $index))
                ->setEmail($email)
                ->setAddress(sprintf('Adresse fictive %02d', $index))
                ->setNotes('Donnée fictive ajoutée par app:demo:tenants.');
        }

        if ($pending === []) {
            $io->success('Tous les locataires demandés existent déjà ; aucune donnée n’a été modifiée.');
            return Command::SUCCESS;
        }

        $connection->beginTransaction();
        try {
            foreach ($pending as $tenant) {
                $this->entityManager->persist($tenant);
            }
            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->entityManager->clear();
            $io->error('Ajout annulé intégralement : ' . $exception->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('%d locataire(s) fictif(s) ajouté(s) à l’organisation %s.', count($pending), $dataset->getOrganization()->getCode()));
        $io->note('Aucun bail, paiement ou dossier de personne réelle n’a été créé.');
        return Command::SUCCESS;
    }
}
