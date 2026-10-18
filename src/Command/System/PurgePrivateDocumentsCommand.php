<?php

declare(strict_types=1);

namespace App\Command\System;

use App\Repository\System\PrivateDocumentRepository;
use App\Service\System\AuditLogService;
use App\Service\System\DateTimeService;
use App\Service\System\PrivateDocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Purge uniquement si une durée de conservation a été explicitement configurée. */
#[AsCommand(name: 'app:documents:purge-expired', description: 'Purge les documents privés au-delà de la durée de conservation configurée.')]
final class PurgePrivateDocumentsCommand extends Command
{
    public function __construct(
        private readonly PrivateDocumentRepository $documents,
        private readonly PrivateDocumentStorage $storage,
        private readonly AuditLogService $auditLog,
        private readonly DateTimeService $dateTime,
        private readonly EntityManagerInterface $entityManager,
        private readonly int $retentionDays,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compte les documents expirés sans les supprimer.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->retentionDays < 1) {
            $io->warning('Purge désactivée : définissez PRIVATE_DOCUMENT_RETENTION_DAYS à un nombre supérieur à zéro après validation de la politique de conservation.');
            return Command::SUCCESS;
        }

        $cutoff = $this->dateTime->now()->modify(sprintf('-%d days', $this->retentionDays));
        $expired = $this->documents->findOlderThan($cutoff, 500);
        if ((bool) $input->getOption('dry-run')) {
            $io->success(sprintf('%d document(s) seraient purgés avant le %s.', count($expired), $cutoff->format('Y-m-d')));
            return Command::SUCCESS;
        }

        $purged = 0;
        foreach ($expired as $document) {
            if (!$this->storage->delete($document->getStorageName())) {
                $io->warning(sprintf('Document %s ignoré : le fichier privé est absent ou inaccessible.', $document->getUuid()->toRfc4122()));
                continue;
            }
            $id = $document->getId();
            if ($id === null) {
                continue;
            }
            $this->auditLog->log('PURGE_EXPIRED_PRIVATE_DOCUMENT', 'private_document', $id, $document->getOrganization(), null, [
                'category' => $document->getCategory()->value,
                'relatedType' => $document->getRelatedType()->value,
                'createdAt' => $document->getCreatedAt()->format(DATE_ATOM),
            ], null);
            $this->entityManager->remove($document);
            $this->entityManager->flush();
            ++$purged;
        }

        $io->success(sprintf('%d document(s) purgés avec journalisation.', $purged));
        return Command::SUCCESS;
    }
}
