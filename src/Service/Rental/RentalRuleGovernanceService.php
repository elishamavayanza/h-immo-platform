<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\RentalRuleDraftRequest;
use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationRentalRuleDraft;
use App\Entity\Identity\OrganizationRentalRuleVersion;
use App\Entity\Identity\RentalRulePublicationDelegation;
use App\Entity\Identity\User;
use App\Enum\OrganizationRole;
use App\Exception\AccessDeniedException;
use App\Repository\Identity\OrganizationRentalRuleDraftRepository;
use App\Repository\Identity\OrganizationRentalRuleVersionRepository;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Identity\RentalRulePublicationDelegationRepository;
use App\Repository\Identity\UserRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use App\Service\System\DateTimeService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Orchestration transactionnelle des brouillons, publications et délégations locatives. */
final readonly class RentalRuleGovernanceService
{
    public function __construct(
        private EntityManagerInterface $em,
        private OrganizationRepository $organizations,
        private UserRepository $users,
        private OrganizationRentalRuleDraftRepository $drafts,
        private OrganizationRentalRuleVersionRepository $versions,
        private RentalRulePublicationDelegationRepository $delegations,
        private SecurityServiceInterface $security,
        private AuditLogService $audit,
        private DateTimeService $clock,
        private ValidatorInterface $validator,
    ) {
    }

    public function listVersions(string $organizationUuid): Feedback
    {
        $organization = $this->organization($organizationUuid);
        $this->security->checkRentalRuleAccess($organization, SecurityAction::VIEW_RENTAL_RULE);
        $data = array_map(fn (OrganizationRentalRuleVersion $version): array => $this->versionData($version), $this->versions->findAllForOrganization($organization));

        return (new Feedback())->setData($data)->setFlushDescription('Versions publiées récupérées.')->setStatus(200);
    }

    /** Capacités de l'appelant courant uniquement; aucune délégation tierce n'est exposée. */
    public function currentCapabilities(string $organizationUuid): Feedback
    {
        $organization = $this->organization($organizationUuid);
        $this->security->checkOrganizationAccess($organization, SecurityAction::VIEW);
        $role = $this->security->getOrganizationRole($this->security->getCurrentUser(), $organization);
        $can = function (SecurityAction $action) use ($organization): bool {
            try {
                $this->security->checkRentalRuleAccess($organization, $action);
                return true;
            } catch (AccessDeniedException) {
                return false;
            }
        };

        return (new Feedback())->setData([
            'organizationRole' => $role?->value,
            'canView' => $can(SecurityAction::VIEW_RENTAL_RULE),
            'canPrepare' => $can(SecurityAction::PREPARE_RENTAL_RULE),
            'canPublish' => $can(SecurityAction::PUBLISH_RENTAL_RULE),
            'canManageDelegations' => $can(SecurityAction::DELEGATE_RENTAL_RULE_PUBLICATION),
        ])->setFlushDescription('Vos autorisations sur les règles locatives ont été vérifiées.')->setStatus(200);
    }

    public function listDrafts(string $organizationUuid): Feedback
    {
        $organization = $this->organization($organizationUuid);
        $this->security->checkRentalRuleAccess($organization, SecurityAction::PREPARE_RENTAL_RULE);
        $data = array_map(fn (OrganizationRentalRuleDraft $draft): array => $this->draftData($draft), $this->drafts->findOpenForOrganization($organization));

        return (new Feedback())->setData($data)->setFlushDescription('Brouillons récupérés.')->setStatus(200);
    }

    public function listDelegations(string $organizationUuid): Feedback
    {
        $organization = $this->organization($organizationUuid);
        $this->security->checkRentalRuleAccess($organization, SecurityAction::DELEGATE_RENTAL_RULE_PUBLICATION);
        $data = array_map(fn (RentalRulePublicationDelegation $delegation): array => $this->delegationData($delegation), $this->delegations->findForOrganization($organization));

        return (new Feedback())->setData($data)->setFlushDescription('Délégations récupérées.')->setStatus(200);
    }

    public function createDraft(string $organizationUuid, RentalRuleDraftRequest $request): Feedback
    {
        $organization = $this->organization($organizationUuid);
        $this->security->checkRentalRuleAccess($organization, SecurityAction::PREPARE_RENTAL_RULE);
        $actor = $this->security->getCurrentUser();
        $draft = $this->fromRequest($organization, $actor, $request);
        $this->assertValid($draft);

        return $this->em->wrapInTransaction(function () use ($draft, $organization, $actor): Feedback {
            $this->em->lock($organization, LockMode::PESSIMISTIC_WRITE);
            $this->security->checkRentalRuleAccess($organization, SecurityAction::PREPARE_RENTAL_RULE);
            $this->em->persist($draft);
            $this->em->flush();
            $this->audit->log('RENTAL_RULE_DRAFT_CREATED', 'OrganizationRentalRuleDraft', (int) $draft->getId(), $organization, $actor, null, $this->draftData($draft));

            return (new Feedback())->setData($this->draftData($draft))->setFlushDescription('Brouillon de règles créé.')->setStatus(201);
        });
    }

    public function updateDraft(string $organizationUuid, string $draftUuid, RentalRuleDraftRequest $request): Feedback
    {
        $organization = $this->organization($organizationUuid);
        $this->security->checkRentalRuleAccess($organization, SecurityAction::PREPARE_RENTAL_RULE);
        $draft = $this->drafts->findOneByUuidAndOrganization(Uuid::fromString($draftUuid), $organization)
            ?? throw new NotFoundHttpException('Brouillon introuvable.');
        if ($draft->getPublishedVersion() !== null) {
            $this->audit->log('RENTAL_RULE_DRAFT_UPDATE_REFUSED', 'OrganizationRentalRuleDraft', (int) $draft->getId(), $organization, $this->security->getCurrentUser(), null, ['result' => 'refused', 'reason' => 'published_draft_immutable']);
            throw new ConflictHttpException('Un brouillon déjà publié est verrouillé. Créez un nouveau brouillon.');
        }
        $actor = $this->security->getCurrentUser();

        $result = $this->em->wrapInTransaction(function () use ($organization, $draft, $request, $actor): ?Feedback {
            $this->em->lock($organization, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($draft);
            $this->security->checkRentalRuleAccess($organization, SecurityAction::PREPARE_RENTAL_RULE);
            if ($draft->getPublishedVersion() !== null) {
                $this->audit->log('RENTAL_RULE_DRAFT_UPDATE_REFUSED', 'OrganizationRentalRuleDraft', (int) $draft->getId(), $organization, $actor, null, ['result' => 'refused', 'reason' => 'published_draft_immutable']);

                return null;
            }
            $before = $this->draftData($draft);
            $this->copyRequest($draft, $request);
            $this->assertValid($draft);
            $draft->setUpdatedAt($this->clock->now());
            $this->em->flush();
            $this->audit->log('RENTAL_RULE_DRAFT_UPDATED', 'OrganizationRentalRuleDraft', (int) $draft->getId(), $organization, $actor, $before, $this->draftData($draft));

            return (new Feedback())->setData($this->draftData($draft))->setFlushDescription('Brouillon mis à jour.')->setStatus(200);
        });

        if ($result === null) {
            throw new ConflictHttpException('Un brouillon déjà publié est verrouillé. Créez un nouveau brouillon.');
        }

        return $result;
    }

    public function publishDraft(string $organizationUuid, string $draftUuid): Feedback
    {
        $organization = $this->organization($organizationUuid);
        $draft = $this->drafts->findOneByUuidAndOrganization(Uuid::fromString($draftUuid), $organization)
            ?? throw new NotFoundHttpException('Brouillon introuvable.');
        $actor = $this->security->getCurrentUser();

        $result = $this->em->wrapInTransaction(function () use ($organization, $draft, $actor): ?Feedback {
            $this->em->lock($organization, LockMode::PESSIMISTIC_WRITE);
            try {
                $this->security->checkRentalRuleAccess($organization, SecurityAction::PUBLISH_RENTAL_RULE);
            } catch (AccessDeniedException) {
                $this->audit->log('RENTAL_RULE_PUBLICATION_REFUSED', 'OrganizationRentalRuleDraft', (int) $draft->getId(), $organization, $actor, null, ['result' => 'refused', 'reason' => 'permission_denied']);

                return null;
            }
            $this->em->refresh($draft);
            if ($draft->getPublishedVersion() !== null) {
                throw new ConflictHttpException('Ce brouillon a déjà été publié.');
            }
            if ($draft->getOrganization() !== $organization) {
                throw new NotFoundHttpException('Brouillon introuvable.');
            }
            $this->assertValid($draft);
            $effectiveFrom = $draft->getEffectiveFrom();
            if ($effectiveFrom === null || $effectiveFrom->format('d') !== '01') {
                throw new ConflictHttpException('La date d’effet doit être le premier jour d’un mois.');
            }
            foreach ($this->versions->findAllForOrganization($organization) as $existing) {
                if ($existing->getEffectiveFrom()?->format('Y-m-d') === $effectiveFrom->format('Y-m-d')) {
                    throw new ConflictHttpException('Une version est déjà publiée à cette date d’effet.');
                }
            }
            $number = 1;
            foreach ($this->versions->findAllForOrganization($organization) as $existing) {
                $number = max($number, $existing->getVersionNumber() + 1);
            }
            $version = (new OrganizationRentalRuleVersion())
                ->setOrganization($organization)->setVersionNumber($number)->setEffectiveFrom($effectiveFrom)
                ->setDueMode($draft->getDueMode())->setDueDay($draft->getDueDay())
                ->setFirstPartialPolicy($draft->getFirstPartialPolicy())->setLastPartialPolicy($draft->getLastPartialPolicy())
                ->setProrationMethod($draft->getProrationMethod())->setRoundingRule($draft->getRoundingRule())
                ->setPublishedBy($actor)->setPublicationReason($draft->getChangeReason());
            $this->em->persist($version);
            $this->em->flush();
            $draft->setPublishedVersion($version)->setUpdatedAt($this->clock->now());
            $this->em->flush();
            $this->audit->log('RENTAL_RULE_PUBLISHED', 'OrganizationRentalRuleVersion', (int) $version->getId(), $organization, $actor, null, $this->versionData($version));

            return (new Feedback())->setData($this->versionData($version))->setFlushDescription('Nouvelle version de règles publiée.')->setStatus(201);
        });

        if ($result === null) {
            throw new AccessDeniedException('Accès refusé pour publier cette règle.');
        }

        return $result;
    }

    public function grantPublication(string $organizationUuid, string $userUuid, ?\DateTimeImmutable $expiresAt): Feedback
    {
        $organization = $this->organization($organizationUuid);
        $this->security->checkRentalRuleAccess($organization, SecurityAction::DELEGATE_RENTAL_RULE_PUBLICATION);
        $recipient = $this->users->findOneByUuid(Uuid::fromString($userUuid));
        if ($recipient === null || !$this->security->isEligibleRentalRuleDelegate($organization, $recipient)) {
            throw new NotFoundHttpException('Utilisateur admissible introuvable dans cette organisation.');
        }
        $actor = $this->security->getCurrentUser();
        if ($actor->getId() === $recipient->getId()) {
            throw new ConflictHttpException('Une délégation ne peut pas être accordée à soi-même.');
        }
        if ($expiresAt !== null && $expiresAt <= $this->clock->now()) {
            throw new ConflictHttpException('La date d’expiration doit être future.');
        }
        $expiresAt = $expiresAt?->setTimezone(new \DateTimeZone(DateTimeService::STORAGE_TIMEZONE));

        return $this->em->wrapInTransaction(function () use ($organization, $recipient, $actor, $expiresAt): Feedback {
            $this->em->lock($organization, LockMode::PESSIMISTIC_WRITE);
            $this->security->checkRentalRuleAccess($organization, SecurityAction::DELEGATE_RENTAL_RULE_PUBLICATION);
            if (!$this->security->isEligibleRentalRuleDelegate($organization, $recipient)) {
                throw new NotFoundHttpException('Utilisateur admissible introuvable dans cette organisation.');
            }
            if ($this->delegations->hasValidPublicationDelegation($organization, $recipient, $this->clock->now())) {
                throw new ConflictHttpException('Une délégation active existe déjà pour cet utilisateur.');
            }
            $delegation = (new RentalRulePublicationDelegation())->setOrganization($organization)->setUser($recipient)
                ->setGrantedBy($actor)->setRecipientRole(OrganizationRole::ADMIN_IMMOBILIER)
                ->setGrantorRole(OrganizationRole::PATRON)->setExpiresAt($expiresAt);
            $this->em->persist($delegation);
            $this->em->flush();
            $data = $this->delegationData($delegation);
            $this->audit->log('RENTAL_RULE_PUBLICATION_DELEGATED', 'RentalRulePublicationDelegation', (int) $delegation->getId(), $organization, $actor, null, $data);

            return (new Feedback())->setData($data)->setFlushDescription('Délégation de publication accordée.')->setStatus(201);
        });
    }

    public function revokePublication(string $organizationUuid, string $delegationUuid, ?string $reason = null): Feedback
    {
        if ($reason !== null && mb_strlen($reason) > 500) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Le motif de révocation ne peut dépasser 500 caractères.');
        }
        $organization = $this->organization($organizationUuid);
        $this->security->checkRentalRuleAccess($organization, SecurityAction::DELEGATE_RENTAL_RULE_PUBLICATION);
        $delegation = $this->delegations->findOneBy(['uuid' => Uuid::fromString($delegationUuid), 'organization' => $organization])
            ?? throw new NotFoundHttpException('Délégation introuvable.');
        if ($delegation->getRevokedAt() !== null) {
            throw new ConflictHttpException('Cette délégation est déjà révoquée.');
        }
        $actor = $this->security->getCurrentUser();
        return $this->em->wrapInTransaction(function () use ($organization, $delegation, $actor, $reason): Feedback {
            $this->em->lock($organization, LockMode::PESSIMISTIC_WRITE);
            $this->security->checkRentalRuleAccess($organization, SecurityAction::DELEGATE_RENTAL_RULE_PUBLICATION);
            $this->em->refresh($delegation);
            if ($delegation->getRevokedAt() !== null) {
                throw new ConflictHttpException('Cette délégation est déjà révoquée.');
            }
            $before = $this->delegationData($delegation);
            $delegation->setRevokedAt($this->clock->now())->setRevokedBy($actor)->setRevocationReason($reason)->setUpdatedAt($this->clock->now());
            $this->em->flush();
            $this->audit->log('RENTAL_RULE_PUBLICATION_REVOKED', 'RentalRulePublicationDelegation', (int) $delegation->getId(), $organization, $actor, $before, $this->delegationData($delegation));

            return (new Feedback())->setData($this->delegationData($delegation))->setFlushDescription('Délégation révoquée.')->setStatus(200);
        });
    }

    private function organization(string $uuid): Organization
    {
        return $this->organizations->findOneByUuid(Uuid::fromString($uuid)) ?? throw new NotFoundHttpException('Organisation introuvable.');
    }

    private function fromRequest(Organization $organization, User $actor, RentalRuleDraftRequest $request): OrganizationRentalRuleDraft
    {
        return $this->copyRequest((new OrganizationRentalRuleDraft())->setOrganization($organization)->setCreatedBy($actor), $request);
    }

    private function copyRequest(OrganizationRentalRuleDraft $draft, RentalRuleDraftRequest $request): OrganizationRentalRuleDraft
    {
        return $draft->setEffectiveFrom($request->effectiveFrom)->setDueMode($request->dueMode)->setDueDay($request->dueDay)
            ->setFirstPartialPolicy($request->firstPartialPolicy)->setLastPartialPolicy($request->lastPartialPolicy)
            ->setProrationMethod($request->prorationMethod)->setRoundingRule($request->roundingRule)->setChangeReason($request->changeReason);
    }

    private function assertValid(object $entity): void
    {
        $violations = $this->validator->validate($entity);
        if (count($violations) > 0) {
            throw new \Symfony\Component\Validator\Exception\ValidationFailedException($entity, $violations);
        }
    }

    private function draftData(OrganizationRentalRuleDraft $draft): array
    {
        return ['uuid' => $draft->getUuid()->toRfc4122(), 'effectiveFrom' => $draft->getEffectiveFrom()?->format('Y-m-d'), 'dueMode' => $draft->getDueMode()->value, 'dueDay' => $draft->getDueDay(), 'firstPartialPolicy' => $draft->getFirstPartialPolicy()->value, 'lastPartialPolicy' => $draft->getLastPartialPolicy()->value, 'prorationMethod' => $draft->getProrationMethod()->value, 'roundingRule' => $draft->getRoundingRule()->value, 'changeReason' => $draft->getChangeReason(), 'published' => $draft->getPublishedVersion() !== null];
    }

    private function versionData(OrganizationRentalRuleVersion $version): array
    {
        return ['uuid' => $version->getUuid()->toRfc4122(), 'version' => $version->getVersionNumber(), 'effectiveFrom' => $version->getEffectiveFrom()?->format('Y-m-d'), 'dueMode' => $version->getDueMode()->value, 'dueDay' => $version->getDueDay(), 'firstPartialPolicy' => $version->getFirstPartialPolicy()->value, 'lastPartialPolicy' => $version->getLastPartialPolicy()->value, 'prorationMethod' => $version->getProrationMethod()->value, 'roundingRule' => $version->getRoundingRule()->value, 'publicationReason' => $version->getPublicationReason(), 'publishedAt' => $version->getCreatedAt()->format(DATE_ATOM)];
    }

    private function delegationData(RentalRulePublicationDelegation $delegation): array
    {
        $expiresAt = $delegation->getExpiresAt();
        $active = $delegation->getRevokedAt() === null && ($expiresAt === null || $expiresAt > $this->clock->now());

        return ['uuid' => $delegation->getUuid()->toRfc4122(), 'userUuid' => $delegation->getUser()->getUuid()->toRfc4122(), 'permission' => 'rental_rule_publish', 'active' => $active, 'expiresAt' => $expiresAt?->format(DATE_ATOM), 'revokedAt' => $delegation->getRevokedAt()?->format(DATE_ATOM), 'revocationReason' => $delegation->getRevocationReason()];
    }
}
