<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:demo:check', description: 'Contrôle les relations de base des jeux de démonstration.')]
final class CheckDemoDatasetCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $checks = [
                'Chaque registre désigne exactement son organisation marquée' => $this->violations("SELECT COUNT(*) FROM demo_dataset d JOIN organization o ON o.id = d.organization_id WHERE BINARY o.slug <> BINARY CONCAT('soft-immo-demo-v1-', d.scenario, '-', d.seed) OR o.is_publicly_listed = 1") === 0,
                'Les villes de démonstration sont rattachées à l’organisation du registre' => $this->violations('SELECT COUNT(*) FROM city c JOIN demo_dataset d ON d.organization_id = c.organization_id WHERE c.deleted_at IS NOT NULL') === 0,
                'Les parcelles de démonstration appartiennent à leurs villes' => $this->violations('SELECT COUNT(*) FROM parcel p JOIN city c ON c.id = p.city_id JOIN demo_dataset d ON d.organization_id = c.organization_id WHERE p.deleted_at IS NOT NULL OR c.deleted_at IS NOT NULL') === 0,
                'Les bâtiments de démonstration appartiennent à des parcelles valides' => $this->violations('SELECT COUNT(*) FROM building b JOIN parcel p ON p.id = b.parcel_id JOIN city c ON c.id = p.city_id JOIN demo_dataset d ON d.organization_id = c.organization_id WHERE b.deleted_at IS NOT NULL OR p.deleted_at IS NOT NULL') === 0,
                'Les unités de démonstration appartiennent à des bâtiments valides' => $this->violations('SELECT COUNT(*) FROM `unit` u JOIN building b ON b.id = u.building_id JOIN parcel p ON p.id = b.parcel_id JOIN city c ON c.id = p.city_id JOIN demo_dataset d ON d.organization_id = c.organization_id WHERE u.deleted_at IS NOT NULL OR b.deleted_at IS NOT NULL') === 0,
                'Les locataires démo sont affectés à une ville de leur organisation' => $this->violations('SELECT COUNT(*) FROM tenant t JOIN demo_dataset d ON d.organization_id = t.organization_id LEFT JOIN city c ON c.id = t.city_id WHERE t.deleted_at IS NULL AND (t.city_id IS NULL OR c.organization_id <> t.organization_id)') === 0,
                'Les ADMIN_VILLE démo possèdent un périmètre dans leur organisation' => $this->violations("SELECT COUNT(*) FROM organization_user ou JOIN demo_dataset d ON d.organization_id = ou.organization_id LEFT JOIN user_city uc ON uc.user_id = ou.user_id LEFT JOIN city c ON c.id = uc.city_id AND c.organization_id = ou.organization_id WHERE ou.role = 'admin_ville' AND (uc.id IS NULL OR c.id IS NULL)") === 0,
                'Le SUPER_ADMIN démo reste distinct des membres métier' => $this->violations("SELECT COUNT(*) FROM demo_dataset d JOIN user u ON BINARY u.email = BINARY CONCAT('demo-', d.seed, '-super-admin@example.invalid') JOIN organization_user ou ON ou.user_id = u.id") === 0,
            ];
        } catch (\Throwable $exception) {
            $io->error('Contrôle des données de démonstration impossible : ' . $exception->getMessage());
            return Command::FAILURE;
        }

        foreach ($checks as $label => $ok) $io->writeln(sprintf('%s %s', $ok ? '<info>[OK]</info>' : '<error>[ÉCHEC]</error>', $label));
        if (in_array(false, $checks, true)) return Command::FAILURE;
        $io->success(sprintf('%d contrôles de cohérence passés.', count($checks)));
        $io->note('Aucun contrôle de contrat ou de finances n’est annoncé : aucune opération financière fictive n’est générée actuellement.');
        return Command::SUCCESS;
    }

    private function violations(string $sql): int
    {
        return (int) $this->connection->fetchOne($sql);
    }
}
