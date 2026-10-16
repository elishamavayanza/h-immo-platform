<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Identity\Organization;
use App\Entity\Property\Building;
use App\Entity\Property\BuildingLevel;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Entity\Property\Unit;
use App\Entity\Rental\Tenant;
use App\Entity\System\DemoDataset;
use App\Enum\BuildingType;
use App\Enum\CityStatus;
use App\Enum\Currency;
use App\Enum\OrganizationStatus;
use App\Enum\TenantType;
use App\Enum\UnitOperationalStatus;
use App\Enum\UnitType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Charge un portefeuille fictif dans un espace de démonstration réservé. */
#[AsCommand(name: 'app:demo:load', description: 'Génère un portefeuille immobilier fictif en dev/test.')]
final class LoadDemoDataCommand extends Command
{
    private const MARKER = 'soft-immo-demo-v1-';

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
            ->addOption('scenario', null, InputOption::VALUE_REQUIRED, 'Volume du jeu : small ou complete.', 'small')
            ->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Entier positif identifiant de façon stable le jeu.', '20261016')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Valide la cible et affiche le volume prévu sans écrire.')
            ->setHelp(<<<'HELP'
Ne s’exécute qu’en dev/test. La seed détermine un slug technique réservé ; si
ce jeu existe déjà, la commande le laisse intact (aucune mise à jour implicite).
Les données financières ne sont pas fabriquées : loyers et paiements doivent
passer par leurs services métier dédiés.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!in_array($this->environment, ['dev', 'test'], true)) {
            $io->error('Les jeux de démonstration sont interdits hors des environnements dev et test.');
            return Command::FAILURE;
        }

        $scenario = (string) $input->getOption('scenario');
        $seed = (string) $input->getOption('seed');
        if (!in_array($scenario, ['small', 'complete'], true) || !preg_match('/^[1-9][0-9]{0,9}$/', $seed)) {
            $io->error('--scenario doit valoir small ou complete et --seed un entier positif (10 chiffres max).');
            return Command::INVALID;
        }

        $slug = self::MARKER . $scenario . '-' . $seed;
        $database = (string) $this->entityManager->getConnection()->fetchOne('SELECT DATABASE()');
        if (!preg_match('/(?:_dev|_test|_demo)$/i', $database)) {
            $io->error(sprintf('Base refusée : « %s » ne porte pas le suffixe de sécurité _dev, _test ou _demo.', $database));
            return Command::FAILURE;
        }
        $existing = $this->entityManager->getRepository(DemoDataset::class)->findOneBy(['seed' => $seed, 'scenario' => $scenario]);
        $reservedSlugCollision = $this->entityManager->getRepository(Organization::class)->findOneBy(['slug' => $slug]);
        $cityCount = $scenario === 'small' ? 2 : 4;
        $unitCount = $scenario === 'small' ? 4 : 16;
        $tenantCount = $scenario === 'small' ? 2 : 8;
        if ($input->getOption('dry-run')) {
            $io->table(['Cible', 'Scénario', 'Villes', 'Parcelles', 'Bâtiments', 'Unités', 'Locataires'], [[$database, $scenario, $cityCount, $cityCount, $cityCount, $unitCount, $tenantCount]]);
            $io->note($existing ? 'Ce jeu est déjà enregistré ; aucune donnée ne serait ajoutée.' : 'La transaction de génération créerait un jeu fictif isolé.');
            return Command::SUCCESS;
        }
        if ($existing instanceof DemoDataset) {
            $io->success('Ce jeu est déjà enregistré. Les données existantes ont été conservées sans modification.');
            return Command::SUCCESS;
        }
        if ($reservedSlugCollision instanceof Organization) {
            $io->error('Le slug technique réservé est occupé sans entrée de registre démo ; génération arrêtée sans modification.');
            return Command::FAILURE;
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $organization = (new Organization())
                ->setName('Organisation de démonstration ' . $seed)
                ->setCode('DEMO-' . strtoupper(substr($scenario, 0, 1)) . '-' . $seed)
                ->setSlug($slug)
                ->setEmail('demo-' . $seed . '@example.invalid')
                ->setPhone('+00000000000')
                ->setAddress('Adresse fictive')
                ->setCity('Goma')
                ->setCountry('CD')
                ->setPublicDescription('Données fictives générées pour le développement.')
                ->setIsPubliclyListed(false)
                ->setStatus(OrganizationStatus::ACTIVE)
                ->setCurrency(Currency::USD);
            $this->entityManager->persist($organization);
            $this->entityManager->persist((new DemoDataset())
                ->setOrganization($organization)
                ->setSeed($seed)
                ->setScenario($scenario));

            $cities = [];
            $cityNames = ['Goma', 'Bukavu', 'Kisangani', 'Lubumbashi'];
            for ($index = 0; $index < $cityCount; ++$index) {
                $city = (new City())
                    ->setName($cityNames[$index])
                    ->setCode(sprintf('D%s-%02d', $seed, $index + 1))
                    ->setCountry('CD')
                    ->setStatus(CityStatus::ACTIVE)
                    ->setOrganization($organization);
                $this->entityManager->persist($city);
                $cities[] = $city;

                $parcel = (new Parcel())
                    ->setCity($city)
                    ->setReference(sprintf('D%s-P%02d', $seed, $index + 1))
                    ->setName('Parcelle démonstration ' . ($index + 1))
                    ->setAddress('Avenue fictive ' . ($index + 1))
                    ->setQuarter('Quartier exemple')
                    ->setArea('1200.00')
                    ->setDescription('Donnée de démonstration générée automatiquement.');
                $this->entityManager->persist($parcel);

                $building = (new Building())
                    ->setParcel($parcel)
                    ->setReference('D-BAT-' . ($index + 1))
                    ->setName('Résidence démonstration ' . ($index + 1))
                    ->setType(BuildingType::APARTMENT)
                    ->setNumberOfFloors(2)
                    ->setDescription('Bâtiment fictif de démonstration.');
                $this->entityManager->persist($building);

                $level = (new BuildingLevel())
                    ->setBuilding($building)
                    ->setReference('RDC')
                    ->setLabel('Rez-de-chaussée')
                    ->setDisplayOrder(0);
                $this->entityManager->persist($level);

                $perCity = intdiv($unitCount, $cityCount);
                for ($unitIndex = 0; $unitIndex < $perCity; ++$unitIndex) {
                    $unit = (new Unit())
                        ->setBuilding($building)
                        ->setLevel($level)
                        ->setReference(sprintf('D%s-U%02d-%02d', $seed, $index + 1, $unitIndex + 1))
                        ->setType(UnitType::APARTMENT)
                        ->setOperationalStatus(UnitOperationalStatus::AVAILABLE)
                        ->setFloor(0)
                        ->setFloorLabel('Rez-de-chaussée')
                        ->setSurface('65.00')
                        ->setBedrooms(2)
                        ->setRooms(3)
                        ->setBathrooms(1)
                        ->setMonthlyRent('350.00')
                        ->setCurrency(Currency::USD)
                        ->setDescription('Unité fictive ; aucun loyer ni paiement réel.');
                    $this->entityManager->persist($unit);
                }
            }

            for ($index = 0; $index < $tenantCount; ++$index) {
                $tenant = (new Tenant())
                    ->setOrganization($organization)
                    ->setCity($cities[$index % count($cities)])
                    ->setType(TenantType::INDIVIDUAL)
                    ->setFullName(sprintf('Locataire Démo %02d', $index + 1))
                    ->setPhone(sprintf('+000000%05d', $index + 1))
                    ->setEmail(sprintf('locataire-%02d-%s@example.invalid', $index + 1, $seed))
                    ->setAddress('Adresse fictive');
                $this->entityManager->persist($tenant);
            }

            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) $connection->rollBack();
            $this->entityManager->clear();
            $io->error('Échec de la génération ; toutes les écritures ont été annulées : ' . $exception->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('Jeu %s créé pour la seed %s : %d villes, %d parcelles, %d bâtiments, %d unités et %d locataires.', $scenario, $seed, $cityCount, $cityCount, $cityCount, $unitCount, $tenantCount));
        $io->note('Aucun compte utilisable, bail actif, loyer, paiement ou dépense fictive n’a été créé.');
        return Command::SUCCESS;
    }
}
