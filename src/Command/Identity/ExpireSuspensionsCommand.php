<?php

declare(strict_types=1);

namespace App\Command\Identity;

use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Enum\OrganizationStatus;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Identity\UserRepository;
use App\Service\System\AuditLogService;
use App\Service\System\DateTimeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Applique et audite les fins d'échéance des suspensions temporaires. */
#[AsCommand(name: 'app:suspensions:expire', description: 'Réactive les organisations et comptes dont la suspension temporaire est arrivée à échéance.')]
final class ExpireSuspensionsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrganizationRepository $organizations,
        private readonly UserRepository $users,
        private readonly AuditLogService $audit,
        private readonly DateTimeService $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = $this->clock->now();
        $organizations = $this->organizations->findExpiredSuspensions($now);
        $users = $this->users->findExpiredSuspensions($now);

        foreach ($organizations as $organization) {
            $endsAt = $organization->getSuspensionEndsAt();
            $organization->setStatus(OrganizationStatus::ACTIVE)->setSuspensionEndsAt(null)->setUpdatedAt($now);
            $this->em->flush();
            $this->audit->log('EXPIRE_ORGANIZATION_SUSPENSION', Organization::class, $organization->getId(), $organization, null,
                ['status' => OrganizationStatus::SUSPENDED->value, 'suspensionEndsAt' => $endsAt?->format(DATE_ATOM)],
                ['status' => OrganizationStatus::ACTIVE->value, 'reason' => 'Échéance de suspension atteinte']);
        }

        foreach ($users as $user) {
            $endsAt = $user->getSuspensionEndsAt();
            $user->setIsActive(true)->setSuspensionEndsAt(null)->setUpdatedAt($now);
            $this->em->flush();
            $this->audit->log('EXPIRE_USER_SUSPENSION', User::class, $user->getId(), null, null,
                ['isActive' => false, 'suspensionEndsAt' => $endsAt?->format(DATE_ATOM)],
                ['isActive' => true, 'reason' => 'Échéance de suspension atteinte']);
        }

        $io->success(sprintf('%d organisation(s) et %d compte(s) réactivé(s).', count($organizations), count($users)));

        return Command::SUCCESS;
    }
}
