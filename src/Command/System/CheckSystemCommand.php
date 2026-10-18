<?php

declare(strict_types=1);

namespace App\Command\System;

use App\Service\System\SystemInitializationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:system:check', description: 'Contrôle la connexion système et la cohérence des mappings Doctrine.')]
final class CheckSystemCommand extends Command
{
    public function __construct(private readonly SystemInitializationService $initialization, private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $state = $this->initialization->inspect();
            $errors = (new SchemaValidator($this->entityManager))->validateMapping();
        } catch (\Throwable $exception) {
            $io->error('Contrôle impossible : ' . $exception->getMessage());
            return Command::FAILURE;
        }

        $io->table(['Vérification', 'Valeur'], [
            ['Environnement', $state['environment']],
            ['Base sélectionnée', $state['database']],
            ['Entités mappées', $state['entities']],
        ]);
        if ($errors !== []) {
            foreach ($errors as $class => $messages) {
                foreach ($messages as $message) $io->writeln(sprintf('<error>%s : %s</error>', $class, $message));
            }
            return Command::FAILURE;
        }

        $io->success('Mappings Doctrine valides. Les rôles sont fournis par les enums et ne nécessitent pas de référentiel en base.');
        return Command::SUCCESS;
    }
}
