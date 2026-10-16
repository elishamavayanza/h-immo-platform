<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Affiche uniquement des agrégats des jeux portant le marqueur réservé. */
#[AsCommand(name: 'app:demo:status', description: 'Compte les données de démonstration sans exposer de données personnelles.')]
final class DemoDatasetStatusCommand extends Command
{
    public function __construct(private readonly Connection $connection, #[Autowire('%kernel.environment%')] private readonly string $environment)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $database = (string) $this->connection->fetchOne('SELECT DATABASE()');
            $counts = [
                'Organisations de démonstration' => $this->count('organization', 'id IN (SELECT organization_id FROM demo_dataset)'),
                'Villes' => $this->count('city', 'organization_id IN (SELECT organization_id FROM demo_dataset)'),
                'Parcelles' => $this->count('parcel', 'city_id IN (SELECT id FROM city WHERE organization_id IN (SELECT organization_id FROM demo_dataset))'),
                'Bâtiments' => $this->count('building', 'parcel_id IN (SELECT p.id FROM parcel p JOIN city c ON c.id = p.city_id WHERE c.organization_id IN (SELECT organization_id FROM demo_dataset))'),
                'Unités' => $this->count('unit', 'building_id IN (SELECT b.id FROM building b JOIN parcel p ON p.id = b.parcel_id JOIN city c ON c.id = p.city_id WHERE c.organization_id IN (SELECT organization_id FROM demo_dataset))'),
                'Locataires' => $this->count('tenant', 'organization_id IN (SELECT organization_id FROM demo_dataset)'),
                'Comptes rattachés aux organisations démo' => $this->count('organization_user', 'organization_id IN (SELECT organization_id FROM demo_dataset)'),
                'Périmètres ADMIN_VILLE' => $this->count('user_city', 'city_id IN (SELECT c.id FROM city c JOIN demo_dataset d ON d.organization_id = c.organization_id)'),
                'SUPER_ADMIN démo' => $this->count('user', "EXISTS (SELECT 1 FROM demo_dataset d WHERE BINARY `user`.email = BINARY CONCAT('demo-', d.seed, '-super-admin@example.invalid'))"),
                'Baux' => $this->count('lease', 'organization_id IN (SELECT organization_id FROM demo_dataset)'),
                'Loyers / paiements' => $this->count('rent', 'lease_id IN (SELECT id FROM lease WHERE organization_id IN (SELECT organization_id FROM demo_dataset))') + $this->count('payment', 'rent_id IN (SELECT r.id FROM rent r JOIN lease l ON l.id = r.lease_id WHERE l.organization_id IN (SELECT organization_id FROM demo_dataset))'),
                'Dépenses' => $this->count('expense', 'organization_id IN (SELECT organization_id FROM demo_dataset)'),
            ];
        } catch (\Throwable $exception) {
            $io->error('Lecture du statut impossible : ' . $exception->getMessage());
            return Command::FAILURE;
        }

        $io->definitionList(['Environnement' => $this->environment, 'Base sélectionnée' => $database]);
        $io->table(['Élément', 'Nombre'], array_map(static fn (string $label, int $count): array => [$label, $count], array_keys($counts), array_values($counts)));
        $io->note('Le statut agrège uniquement les organisations explicitement enregistrées par le générateur.');
        return Command::SUCCESS;
    }

    private function count(string $table, string $where): int
    {
        // Les noms de tables et filtres sont des constantes privées, jamais issus de l'entrée utilisateur.
        return (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM `%s` WHERE %s', $table, $where));
    }
}
