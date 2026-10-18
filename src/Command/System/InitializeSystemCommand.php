<?php

declare(strict_types=1);

namespace App\Command\System;

use App\Service\System\SystemInitializationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:system:init', description: 'Vérifie les prérequis système sans créer de données métier.')]
final class InitializeSystemCommand extends Command
{
    public function __construct(private readonly SystemInitializationService $initialization)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $state = $this->initialization->inspect();
        } catch (\Throwable $exception) {
            $io->error('Initialisation impossible : ' . $exception->getMessage());
            return Command::FAILURE;
        }

        $io->success('Le système est prêt. Aucun compte, organisation ou paramètre existant n’a été modifié.');
        $io->table(['Vérification', 'Valeur'], [
            ['Environnement', $state['environment']],
            ['Base sélectionnée', $state['database']],
            ['Entités Doctrine', $state['entities']],
            ['Rôles plateforme (définis dans le code)', implode(', ', $state['platformRoles'])],
            ['Rôles organisation (définis dans le code)', implode(', ', $state['organizationRoles'])],
        ]);

        return Command::SUCCESS;
    }
}
