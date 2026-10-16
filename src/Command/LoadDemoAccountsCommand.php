<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Entity\Identity\UserCity;
use App\Entity\Property\City;
use App\Entity\System\DemoDataset;
use App\Enum\OrganizationRole;
use App\Enum\PlatformRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Crée des comptes fictifs pour essayer les rôles sur un jeu démo isolé. */
#[AsCommand(name: 'app:demo:accounts', description: 'Ajoute les comptes de test des rôles à une organisation démo.')]
final class LoadDemoAccountsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
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
            ->setHelp(<<<'HELP'
Ajoute un SUPER_ADMIN et les quatre comptes d’organisation (PATRON,
ADMIN_IMMOBILIER et deux ADMIN_VILLE, chacun limité à une ville). Un mot de
passe temporaire doit être fourni dans DEMO_TEST_PASSWORD ; il n’est jamais
affiché. La commande est réservée à dev/test et aux bases dont le nom finit
par _dev, _test ou _demo.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!in_array($this->environment, ['dev', 'test'], true)) {
            $io->error('La création de comptes démo est interdite hors de dev/test.');
            return Command::FAILURE;
        }

        $scenario = (string) $input->getOption('scenario');
        $seed = (string) $input->getOption('seed');
        if (!in_array($scenario, ['small', 'complete'], true) || !preg_match('/^[1-9][0-9]{0,9}$/', $seed)) {
            $io->error('Fournissez --scenario=small|complete et une --seed positive.');
            return Command::INVALID;
        }

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
        if (count($cities) < 2) {
            $io->error('Le jeu démo doit contenir au moins deux villes pour séparer les ADMIN_VILLE.');
            return Command::FAILURE;
        }

        $definitions = [
            'super_admin' => ['email' => sprintf('demo-%s-super-admin@example.invalid', $seed), 'name' => 'Super Admin Démo', 'platform' => PlatformRole::SUPER_ADMIN, 'role' => null, 'city' => null],
            'patron' => ['email' => sprintf('demo-%s-patron@example.invalid', $seed), 'name' => 'Patron Démo', 'platform' => null, 'role' => OrganizationRole::PATRON, 'city' => null],
            'admin_immobilier' => ['email' => sprintf('demo-%s-admin-immo@example.invalid', $seed), 'name' => 'Admin Immobilier Démo', 'platform' => null, 'role' => OrganizationRole::ADMIN_IMMOBILIER, 'city' => null],
            'admin_ville_1' => ['email' => sprintf('demo-%s-admin-ville-1@example.invalid', $seed), 'name' => 'Admin Ville Démo 1', 'platform' => null, 'role' => OrganizationRole::ADMIN_VILLE, 'city' => $cities[0]],
            'admin_ville_2' => ['email' => sprintf('demo-%s-admin-ville-2@example.invalid', $seed), 'name' => 'Admin Ville Démo 2', 'platform' => null, 'role' => OrganizationRole::ADMIN_VILLE, 'city' => $cities[1]],
        ];

        $userRepository = $this->entityManager->getRepository(User::class);
        $existingUsers = [];
        foreach ($definitions as $key => $definition) {
            $existingUsers[$key] = $userRepository->findOneBy(['email' => $definition['email']]);
        }
        $existingCount = count(array_filter($existingUsers, static fn (mixed $user): bool => $user instanceof User));
        if ($existingCount === count($definitions)) {
            $io->success('Les cinq comptes de test existent déjà ; aucun compte ni mot de passe n’a été modifié.');
            $io->note('Le mot de passe initial n’est pas récupérable. Choisissez une nouvelle seed pour générer un autre jeu de comptes.');
            return Command::SUCCESS;
        }
        if ($existingCount > 0) {
            $io->error('Une partie des adresses réservées existe déjà. Aucun compte n’a été créé ni modifié.');
            return Command::FAILURE;
        }

        $plainPassword = getenv('DEMO_TEST_PASSWORD');
        if (!is_string($plainPassword) || strlen($plainPassword) < 6) {
            $io->error('Définissez localement DEMO_TEST_PASSWORD avec au moins 6 caractères. Le mot de passe ne sera pas affiché.');
            return Command::FAILURE;
        }

        $connection->beginTransaction();
        try {
            foreach ($definitions as $key => $definition) {
                $user = (new User())
                    ->setEmail($definition['email'])
                    ->setFullName($definition['name'])
                    ->setPlatformRole($definition['platform'])
                    ->setIsActive(true);
                $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
                $this->entityManager->persist($user);

                if ($definition['role'] instanceof OrganizationRole) {
                    $membership = (new OrganizationUser())
                        ->setOrganization($dataset->getOrganization())
                        ->setUser($user)
                        ->setRole($definition['role']);
                    $this->entityManager->persist($membership);
                    if ($definition['city'] instanceof City) {
                        $this->entityManager->persist((new UserCity())->setUser($user)->setCity($definition['city']));
                    }
                }
            }
            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->entityManager->clear();
            $io->error('Création annulée intégralement : ' . $exception->getMessage());
            return Command::FAILURE;
        }

        $io->success('Les cinq comptes de test sont créés. Ils ne sont rattachés qu’à cette organisation démo.');
        $io->table(['Rôle', 'Adresse de connexion', 'Ville attribuée'], array_map(
            static fn (string $key, array $definition): array => [$key, $definition['email'], $definition['city'] instanceof City ? $definition['city']->getName() : '—'],
            array_keys($definitions),
            array_values($definitions),
        ));
        $io->note('Le même mot de passe temporaire a été appliqué aux cinq comptes sans être affiché. Les comptes sont réservés à cette base de test.');
        return Command::SUCCESS;
    }
}
