<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Dto\Request\Property\CustomerInquiryFilterRequest;
use App\Dto\Request\Property\CustomerInquiryRequest;
use App\Dto\Request\Property\CustomerVisitRequest;
use App\Dto\Request\Property\CustomerVisitTransitionRequest;
use App\Dto\Response\Property\PublicInquiryReceiptResponse;
use App\Entity\Identity\Organization;
use App\Entity\Property\CustomerInquiry;
use App\Entity\Property\CustomerInquiryHistory;
use App\Entity\Property\CustomerInquiryNotification;
use App\Entity\Property\CustomerVisit;
use App\Entity\Rental\Lease;
use App\Enum\CustomerInquiryStatus;
use App\Enum\CustomerInquiryType;
use App\Enum\CustomerVisitStatus;
use App\Enum\LeaseStatus;
use App\Enum\ListingOfferType;
use App\Enum\OrganizationRole;
use App\Mapper\Property\CustomerInquiryMapper;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Property\CustomerInquiryRepository;
use App\Repository\Property\CustomerInquiryNotificationRepository;
use App\Repository\Property\CustomerVisitRepository;
use App\Repository\Rental\LeaseRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use App\Service\System\DateTimeService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Uid\Uuid;

/** Traitement des demandes publiques et de leur suivi organisationnel. */
final readonly class CustomerInquiryService
{
    public function __construct(
        private CustomerInquiryRepository $inquiries,
        private CustomerInquiryNotificationRepository $notifications,
        private CustomerVisitRepository $visits,
        private LeaseRepository $leases,
        private OrganizationRepository $organizations,
        private PublicShowcaseService $showcase,
        private CustomerInquiryMapper $mapper,
        private SecurityServiceInterface $security,
        private AuditLogService $auditLog,
        private EntityManagerInterface $entityManager,
        private DateTimeService $dateTime,
        private RequestStack $requestStack,
        #[Autowire(service: 'limiter.public_customer_inquiries')]
        private RateLimiterFactory $inquiryLimiter,
    ) {
    }

    /** @return array{receipt: PublicInquiryReceiptResponse, created: bool} */
    public function createPublic(
        string $organizationSlug,
        string $unitUuid,
        CustomerInquiryRequest $request,
        string $idempotencyKey,
    ): array {
        $listing = $this->showcase->findAvailableListing($organizationSlug, $unitUuid);
        if ($listing === null) {
            throw new NotFoundHttpException('Annonce introuvable ou non disponible.');
        }

        $key = $this->parseUuid($idempotencyKey, 'L’en-tête Idempotency-Key doit contenir un UUID valide.');
        $unit = $listing->getUnit();
        $organization = $unit->getBuilding()->getParcel()->getCity()->getOrganization();
        $clientIp = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';
        $rateKey = hash('sha256', (string) $organization->getUuid() . '|' . $clientIp);
        $limit = $this->inquiryLimiter->create($rateKey)->consume();
        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - $this->dateTime->now()->getTimestamp());
            throw new TooManyRequestsHttpException($retryAfter, 'Trop de demandes ont été envoyées. Réessayez plus tard.');
        }

        $keyHash = hash('sha256', strtolower($key->toRfc4122()));
        $payloadHash = hash('sha256', json_encode([
            'listing' => (string) $listing->getUuid(),
            'type' => $request->type,
            'name' => trim($request->name),
            'phone' => trim($request->phone),
            'email' => $request->email === null ? null : trim($request->email),
            'message' => $request->message === null ? null : trim($request->message),
            'consent' => $request->privacyConsent,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        $existing = $this->inquiries->findOneByOrganizationAndKey($organization, $keyHash);
        if ($existing !== null) {
            if (!hash_equals($existing->getPayloadHash(), $payloadHash)) {
                throw new ConflictHttpException('Cette clé a déjà été utilisée pour une autre demande.');
            }

            return ['receipt' => $this->receipt($existing), 'created' => false];
        }

        $inquiry = (new CustomerInquiry())
            ->setOrganization($organization)
            ->setListing($listing)
            ->setType(CustomerInquiryType::from($request->type))
            ->setName($request->name)
            ->setPhone($request->phone)
            ->setEmail($request->email)
            ->setMessage($request->message)
            ->setConsentedAt($this->dateTime->now())
            ->setIdempotencyKeyHash($keyHash)
            ->setPayloadHash($payloadHash);
        $history = (new CustomerInquiryHistory())
            ->setOrganization($organization)
            ->setInquiry($inquiry)
            ->setAction('CREATED_PUBLIC')
            ->setNewStatus(CustomerInquiryStatus::NEW);

        try {
            $city = $listing->getUnit()->getBuilding()->getParcel()->getCity();
            $recipients = $this->notifications->findActiveCityAdmins($organization, $city);
            $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($inquiry, $history, $organization, $recipients): void {
                $entityManager->persist($inquiry);
                $entityManager->persist($history);
                foreach ($recipients as $recipient) {
                    $entityManager->persist((new CustomerInquiryNotification())->setInquiry($inquiry)->setRecipient($recipient));
                }
                $entityManager->flush();
                $this->auditLog->log('CREATE_PUBLIC_CUSTOMER_INQUIRY', CustomerInquiry::class, $inquiry->getId(), $organization,
                    oldValues: null, newValues: ['listingUuid' => (string) $inquiry->getListing()->getUuid(), 'type' => $inquiry->getType()->value], flush: false);
                $entityManager->flush();
            });
        } catch (UniqueConstraintViolationException) {
            // La contrainte unique est l'arbitre final entre deux soumissions simultanées.
            throw new ConflictHttpException('Cette demande a déjà été transmise. Réessayez avec la même clé.');
        }

        return ['receipt' => $this->receipt($inquiry), 'created' => true];
    }

    /** @return array{items: list<array{uuid: string, inquiryUuid: string, listingTitle: string, city: string, createdAt: string, readAt: ?string}>, unread: int} */
    public function notificationsForCurrentUser(string $organizationUuid): array
    {
        $organization = $this->findOrganization($organizationUuid);
        $this->security->checkOrganizationAccess($organization, SecurityAction::VIEW_CUSTOMER_INQUIRY);
        $user = $this->security->getCurrentUser();
        $this->assertCityNotificationRole($organization, $user);
        $cities = $this->security->getAccessibleCitiesForOrganization($organization);
        $result = $this->notifications->findInbox($user, $organization, $cities);
        return [
            'unread' => $result['unread'],
            'items' => array_map(static function (CustomerInquiryNotification $notification): array {
                $inquiry = $notification->getInquiry();
                $city = $inquiry->getListing()->getUnit()->getBuilding()->getParcel()->getCity();
                return [
                    'uuid' => $notification->getUuid()->toRfc4122(),
                    'inquiryUuid' => $inquiry->getUuid()->toRfc4122(),
                    'listingTitle' => $inquiry->getListing()->getTitle(),
                    'city' => $city->getName(),
                    'createdAt' => $notification->getCreatedAt()->format(DATE_ATOM),
                    'readAt' => $notification->getReadAt()?->format(DATE_ATOM),
                ];
            }, $result['items']),
        ];
    }

    public function markNotificationRead(string $organizationUuid, string $notificationUuid): void
    {
        $organization = $this->findOrganization($organizationUuid);
        $this->security->checkOrganizationAccess($organization, SecurityAction::VIEW_CUSTOMER_INQUIRY);
        $user = $this->security->getCurrentUser();
        $this->assertCityNotificationRole($organization, $user);
        $notification = $this->notifications->findOneForRecipient(
            $organization,
            $user,
            $this->parseUuid($notificationUuid, 'Notification introuvable.'),
        ) ?? throw new NotFoundHttpException('Notification introuvable.');
        $this->checkInquiryCityAccess($notification->getInquiry(), SecurityAction::VIEW_CUSTOMER_INQUIRY);
        if ($notification->getReadAt() === null) {
            $notification->setReadAt($this->dateTime->now());
            $this->entityManager->flush();
        }
    }

    /** @return array{items: list<\App\Dto\Response\Property\CustomerInquiryResponse>, total: int, page: int, pages: int} */
    public function listForOrganization(string $organizationUuid, CustomerInquiryFilterRequest $filter): array
    {
        $organization = $this->findOrganization($organizationUuid);
        $this->security->checkOrganizationAccess($organization, SecurityAction::VIEW_CUSTOMER_INQUIRY);
        $cities = $this->security->getAccessibleCitiesForOrganization($organization);
        $status = $filter->status === null ? null : CustomerInquiryStatus::from($filter->status);
        $type = $filter->type === null ? null : CustomerInquiryType::from($filter->type);
        $result = $this->inquiries->findForOrganization($organization, $cities, $status, $type, $filter->page, $filter->limit);

        return [
            'items' => array_map($this->mapper->toResponse(...), $result['items']),
            'total' => $result['total'],
            'page' => $filter->page,
            'pages' => max(1, (int) ceil($result['total'] / $filter->limit)),
        ];
    }

    public function get(string $organizationUuid, string $inquiryUuid): object
    {
        $organization = $this->findOrganization($organizationUuid);
        $this->security->checkOrganizationAccess($organization, SecurityAction::VIEW_CUSTOMER_INQUIRY);
        $inquiry = $this->findInquiry($organization, $inquiryUuid);
        $this->checkInquiryCityAccess($inquiry, SecurityAction::VIEW_CUSTOMER_INQUIRY);
        return $this->mapper->toResponse($inquiry);
    }

    /** @return list<object> */
    public function history(string $organizationUuid, string $inquiryUuid): array
    {
        $organization = $this->findOrganization($organizationUuid);
        $this->security->checkOrganizationAccess($organization, SecurityAction::VIEW_CUSTOMER_INQUIRY);
        $inquiry = $this->findInquiry($organization, $inquiryUuid);
        $this->checkInquiryCityAccess($inquiry, SecurityAction::VIEW_CUSTOMER_INQUIRY);

        return array_map($this->mapper->toHistoryResponse(...), $this->inquiries->findHistory($inquiry));
    }

    public function takeOwnership(string $organizationUuid, string $inquiryUuid): object
    {
        $organization = $this->findOrganization($organizationUuid);
        $this->security->checkOrganizationAccess($organization, SecurityAction::MANAGE_CUSTOMER_INQUIRY);
        $inquiry = $this->findInquiry($organization, $inquiryUuid);
        $actor = $this->security->getCurrentUser();

        return $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($inquiry, $actor): object {
            $inquiry = $this->inquiries->lock($inquiry);
            if ($inquiry->getAssignedTo()?->getId() === $actor->getId() && $inquiry->getStatus() === CustomerInquiryStatus::IN_PROGRESS) {
                return $this->mapper->toResponse($inquiry);
            }
            if ($inquiry->getAssignedTo() !== null && $inquiry->getAssignedTo()->getId() !== $actor->getId()) {
                throw new ConflictHttpException('Cette demande est déjà prise en charge par un autre membre.');
            }
            if ($inquiry->getStatus() !== CustomerInquiryStatus::NEW) {
                throw new ConflictHttpException('Seule une nouvelle demande peut être prise en charge.');
            }

            $inquiry->setAssignedTo($actor)->setStatus(CustomerInquiryStatus::IN_PROGRESS)->setUpdatedAt($this->dateTime->now());
            $entityManager->persist((new CustomerInquiryHistory())
                ->setOrganization($inquiry->getOrganization())->setInquiry($inquiry)->setActor($actor)->setAction('TAKEN_OVER')
                ->setOldStatus(CustomerInquiryStatus::NEW)->setNewStatus(CustomerInquiryStatus::IN_PROGRESS));
            $entityManager->flush();
            $this->auditLog->log('TAKE_OWNERSHIP_CUSTOMER_INQUIRY', CustomerInquiry::class, $inquiry->getId(), $inquiry->getOrganization(), $actor,
                oldValues: ['status' => CustomerInquiryStatus::NEW->value], newValues: ['status' => CustomerInquiryStatus::IN_PROGRESS->value], flush: false);
            $entityManager->flush();

            return $this->mapper->toResponse($inquiry);
        });
    }

    public function transition(string $organizationUuid, string $inquiryUuid, string $targetStatus, ?string $reason): object
    {
        $organization = $this->findOrganization($organizationUuid);
        $this->security->checkOrganizationAccess($organization, SecurityAction::MANAGE_CUSTOMER_INQUIRY);
        $inquiry = $this->findInquiry($organization, $inquiryUuid);
        $target = CustomerInquiryStatus::from($targetStatus);
        $reason = $reason === null ? null : trim($reason);

        if ($target === CustomerInquiryStatus::CLOSED && ($reason === null || $reason === '')) {
            throw new BadRequestHttpException('Un motif est requis pour clôturer la demande.');
        }
        if ($reason !== null && mb_strlen($reason) > 1000) {
            throw new BadRequestHttpException('Le motif ne peut pas dépasser 1000 caractères.');
        }

        $actor = $this->security->getCurrentUser();

        return $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($inquiry, $target, $reason, $actor): object {
            $inquiry = $this->inquiries->lock($inquiry);
            $old = $inquiry->getStatus();
            if ($target === CustomerInquiryStatus::CONVERTED) {
                throw new ConflictHttpException('La conversion doit rattacher explicitement un bail actif existant.');
            }
            if ($old === $target) {
                return $this->mapper->toResponse($inquiry);
            }

            $allowedTransitions = [
                CustomerInquiryStatus::IN_PROGRESS->value => [CustomerInquiryStatus::CONTACTED, CustomerInquiryStatus::CLOSED],
                CustomerInquiryStatus::CONTACTED->value => [CustomerInquiryStatus::NEGOTIATION, CustomerInquiryStatus::CLOSED],
                CustomerInquiryStatus::VISIT_PROPOSED->value => [CustomerInquiryStatus::CONTACTED, CustomerInquiryStatus::CLOSED],
                CustomerInquiryStatus::VISIT_COMPLETED->value => [CustomerInquiryStatus::NEGOTIATION, CustomerInquiryStatus::CLOSED],
                CustomerInquiryStatus::NEGOTIATION->value => [CustomerInquiryStatus::CLOSED],
            ];
            if (in_array($old, [CustomerInquiryStatus::VISIT_PROPOSED, CustomerInquiryStatus::VISIT_COMPLETED], true)
                && in_array($target, [CustomerInquiryStatus::VISIT_PROPOSED, CustomerInquiryStatus::VISIT_COMPLETED], true)) {
                throw new ConflictHttpException('Le statut de visite est piloté par les rendez-vous enregistrés.');
            }
            if (!in_array($target, $allowedTransitions[$old->value] ?? [], true)) {
                throw new ConflictHttpException(sprintf('Transition impossible de « %s » vers « %s ».', $old->value, $target->value));
            }
            if ($this->visits->hasOpenVisits($inquiry) && in_array($target, [CustomerInquiryStatus::CONTACTED, CustomerInquiryStatus::CLOSED], true)) {
                throw new ConflictHttpException('Annulez d’abord les rendez-vous en attente avant de revenir au contact ou de clôturer la demande.');
            }

            $inquiry->setStatus($target)->setClosedReason($target === CustomerInquiryStatus::CLOSED ? $reason : null)->setUpdatedAt($this->dateTime->now());
            $entityManager->persist((new CustomerInquiryHistory())
                ->setOrganization($inquiry->getOrganization())->setInquiry($inquiry)->setActor($actor)->setAction('STATUS_CHANGED')
                ->setOldStatus($old)->setNewStatus($target)->setReason($reason));
            $entityManager->flush();
            $this->auditLog->log('TRANSITION_CUSTOMER_INQUIRY', CustomerInquiry::class, $inquiry->getId(), $inquiry->getOrganization(), $actor,
                oldValues: ['status' => $old->value], newValues: ['status' => $target->value], flush: false);
            $entityManager->flush();

            return $this->mapper->toResponse($inquiry);
        });
    }

    /** @return list<\App\Dto\Response\Property\CustomerVisitResponse> */
    public function listVisitsForInquiry(string $organizationUuid, string $inquiryUuid): array
    {
        $organization = $this->findOrganization($organizationUuid);
        $this->security->checkOrganizationAccess($organization, SecurityAction::VIEW_CUSTOMER_INQUIRY);
        $inquiry = $this->findInquiry($organization, $inquiryUuid);
        $this->checkInquiryCityAccess($inquiry, SecurityAction::VIEW_CUSTOMER_INQUIRY);

        return array_map($this->mapper->toVisitResponse(...), $this->visits->findForInquiry($organization, $inquiry));
    }

    public function proposeVisit(string $organizationUuid, string $inquiryUuid, CustomerVisitRequest $request): object
    {
        $organization = $this->findOrganization($organizationUuid);
        $this->security->checkOrganizationAccess($organization, SecurityAction::MANAGE_CUSTOMER_VISIT);
        $scheduledAt = $this->parseFutureVisitDate($request->scheduledAt);
        $actor = $this->security->getCurrentUser();
        $inquiry = $this->findInquiry($organization, $inquiryUuid);
        $this->checkInquiryCityAccess($inquiry, SecurityAction::MANAGE_CUSTOMER_VISIT);

        return $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($organization, $inquiry, $scheduledAt, $actor): object {
            $inquiry = $this->inquiries->lock($inquiry);
            $oldStatus = $inquiry->getStatus();
            if (!in_array($oldStatus, [CustomerInquiryStatus::CONTACTED, CustomerInquiryStatus::VISIT_PROPOSED, CustomerInquiryStatus::VISIT_COMPLETED], true)) {
                throw new ConflictHttpException('La demande doit être en contact établi ou avoir déjà un suivi de visite pour proposer un rendez-vous.');
            }

            $visit = (new CustomerVisit())->setOrganization($organization)->setInquiry($inquiry)
                ->setCreatedBy($actor)->setScheduledAt($scheduledAt)->setStatus(CustomerVisitStatus::PROPOSED);
            if ($oldStatus !== CustomerInquiryStatus::VISIT_PROPOSED) {
                $inquiry->setStatus(CustomerInquiryStatus::VISIT_PROPOSED)->setUpdatedAt($this->dateTime->now());
            }
            $entityManager->persist($visit);
            $entityManager->persist($this->visitHistory($inquiry, $actor, 'VISIT_PROPOSED', $oldStatus, $inquiry->getStatus(), $visit));
            $entityManager->flush();
            $this->auditLog->log('PROPOSE_CUSTOMER_VISIT', CustomerInquiry::class, $inquiry->getId(), $organization, $actor,
                oldValues: null, newValues: ['visitUuid' => (string) $visit->getUuid(), 'scheduledAt' => $scheduledAt->format(DATE_ATOM)], flush: false);
            $entityManager->flush();

            return $this->mapper->toVisitResponse($visit);
        });
    }

    public function rescheduleVisit(string $organizationUuid, string $inquiryUuid, string $visitUuid, CustomerVisitRequest $request): object
    {
        $organization = $this->findOrganization($organizationUuid);
        $this->security->checkOrganizationAccess($organization, SecurityAction::MANAGE_CUSTOMER_VISIT);
        $scheduledAt = $this->parseFutureVisitDate($request->scheduledAt);
        $actor = $this->security->getCurrentUser();
        $inquiry = $this->findInquiry($organization, $inquiryUuid);
        $this->checkInquiryCityAccess($inquiry, SecurityAction::MANAGE_CUSTOMER_VISIT);
        $uuid = $this->parseUuid($visitUuid, 'Rendez-vous introuvable.');

        return $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($organization, $inquiry, $uuid, $scheduledAt, $actor): object {
            $inquiry = $this->inquiries->lock($inquiry);
            $visit = $this->visits->findOneForInquiry($organization, $inquiry, $uuid)
                ?? throw new NotFoundHttpException('Rendez-vous introuvable.');
            $visit = $this->visits->lock($visit);
            if (!in_array($visit->getStatus(), [CustomerVisitStatus::PROPOSED, CustomerVisitStatus::CONFIRMED, CustomerVisitStatus::RESCHEDULED], true)) {
                throw new ConflictHttpException('Un rendez-vous terminé ou annulé ne peut pas être reporté.');
            }
            $oldDate = $visit->getScheduledAt()->format(DATE_ATOM);
            $visit->setScheduledAt($scheduledAt)->setStatus(CustomerVisitStatus::RESCHEDULED)->setUpdatedAt($this->dateTime->now());
            $entityManager->persist($this->visitHistory($inquiry, $actor, 'VISIT_RESCHEDULED', $inquiry->getStatus(), $inquiry->getStatus(), $visit, sprintf('Ancienne date : %s ; nouvelle date : %s.', $oldDate, $scheduledAt->format(DATE_ATOM))));
            $entityManager->flush();
            $this->auditLog->log('RESCHEDULE_CUSTOMER_VISIT', CustomerInquiry::class, $inquiry->getId(), $organization, $actor,
                oldValues: ['scheduledAt' => $oldDate], newValues: ['visitUuid' => (string) $visit->getUuid(), 'scheduledAt' => $scheduledAt->format(DATE_ATOM)], flush: false);
            $entityManager->flush();

            return $this->mapper->toVisitResponse($visit);
        });
    }

    public function transitionVisit(string $organizationUuid, string $inquiryUuid, string $visitUuid, CustomerVisitTransitionRequest $request): object
    {
        $organization = $this->findOrganization($organizationUuid);
        $this->security->checkOrganizationAccess($organization, SecurityAction::MANAGE_CUSTOMER_VISIT);
        $target = CustomerVisitStatus::from($request->status);
        $reason = $request->reason === null ? null : trim($request->reason);
        if (in_array($target, [CustomerVisitStatus::COMPLETED, CustomerVisitStatus::CANCELLED], true) && ($reason === null || $reason === '')) {
            throw new BadRequestHttpException($target === CustomerVisitStatus::COMPLETED ? 'Indiquez le résultat de la visite.' : 'Indiquez le motif d’annulation.');
        }
        $actor = $this->security->getCurrentUser();
        $inquiry = $this->findInquiry($organization, $inquiryUuid);
        $this->checkInquiryCityAccess($inquiry, SecurityAction::MANAGE_CUSTOMER_VISIT);
        $uuid = $this->parseUuid($visitUuid, 'Rendez-vous introuvable.');

        return $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($organization, $inquiry, $uuid, $target, $reason, $actor): object {
            $inquiry = $this->inquiries->lock($inquiry);
            $visit = $this->visits->findOneForInquiry($organization, $inquiry, $uuid)
                ?? throw new NotFoundHttpException('Rendez-vous introuvable.');
            $visit = $this->visits->lock($visit);
            $allowed = match ($visit->getStatus()) {
                CustomerVisitStatus::PROPOSED => [CustomerVisitStatus::CONFIRMED, CustomerVisitStatus::CANCELLED],
                CustomerVisitStatus::RESCHEDULED => [CustomerVisitStatus::CONFIRMED, CustomerVisitStatus::CANCELLED],
                CustomerVisitStatus::CONFIRMED => [CustomerVisitStatus::COMPLETED, CustomerVisitStatus::CANCELLED],
                default => [],
            };
            if (!in_array($target, $allowed, true)) {
                if ($visit->getStatus() === $target) return $this->mapper->toVisitResponse($visit);
                throw new ConflictHttpException(sprintf('Transition impossible du rendez-vous « %s » vers « %s ».', $visit->getStatus()->value, $target->value));
            }

            $oldInquiryStatus = $inquiry->getStatus();
            $oldVisitStatus = $visit->getStatus();
            $visit->setStatus($target)->setOutcome($target === CustomerVisitStatus::COMPLETED ? $reason : null)->setUpdatedAt($this->dateTime->now());
            if ($target === CustomerVisitStatus::COMPLETED && $inquiry->getStatus() === CustomerInquiryStatus::VISIT_PROPOSED) {
                $inquiry->setStatus(CustomerInquiryStatus::VISIT_COMPLETED)->setUpdatedAt($this->dateTime->now());
            }
            $action = match ($target) {
                CustomerVisitStatus::CONFIRMED => 'VISIT_CONFIRMED',
                CustomerVisitStatus::COMPLETED => 'VISIT_COMPLETED',
                CustomerVisitStatus::CANCELLED => 'VISIT_CANCELLED',
                default => throw new \LogicException('Transition de visite non prise en charge.'),
            };
            $entityManager->persist($this->visitHistory($inquiry, $actor, $action, $oldInquiryStatus, $inquiry->getStatus(), $visit, $reason));
            $entityManager->flush();
            $this->auditLog->log($action, CustomerInquiry::class, $inquiry->getId(), $organization, $actor,
                oldValues: ['visitStatus' => $oldVisitStatus->value],
                newValues: ['visitUuid' => (string) $visit->getUuid(), 'visitStatus' => $target->value, 'inquiryStatus' => $inquiry->getStatus()->value], flush: false);
            $entityManager->flush();

            return $this->mapper->toVisitResponse($visit);
        });
    }

    public function convertToActiveLease(string $organizationUuid, string $inquiryUuid): object
    {
        $organization = $this->findOrganization($organizationUuid);
        $this->security->checkOrganizationAccess($organization, SecurityAction::MANAGE_CUSTOMER_INQUIRY);
        $inquiry = $this->findInquiry($organization, $inquiryUuid);

        return $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($organization, $inquiry): object {
            $inquiry = $this->inquiries->lock($inquiry);
            if ($inquiry->getStatus() === CustomerInquiryStatus::CONVERTED) {
                $linkedLease = $inquiry->getConvertedLease();
                if ($linkedLease === null
                    || $linkedLease->getOrganization()->getId() !== $organization->getId()
                    || ($linkedLease->getUnit() !== $inquiry->getListing()->getUnit() && !$linkedLease->getAdditionalUnits()->contains($inquiry->getListing()->getUnit()))) {
                    throw new ConflictHttpException('Le lien vers le bail existant est incohérent.');
                }
                $this->security->checkLeaseAccess($linkedLease, SecurityAction::VIEW_LEASE);
                return $this->mapper->toResponse($inquiry);
            }
            if ($inquiry->getStatus() !== CustomerInquiryStatus::NEGOTIATION) {
                throw new ConflictHttpException('Seule une demande en négociation peut être liée à un bail existant.');
            }
            $listing = $inquiry->getListing();
            if ($listing->getOfferType() !== ListingOfferType::RENT) {
                throw new ConflictHttpException('La conversion en bail est réservée aux annonces de location. Aucune vente n’est créée par ce suivi.');
            }
            $unit = $listing->getUnit();
            if ($unit->getBuilding()->getParcel()->getCity()->getOrganization()->getId() !== $organization->getId()) {
                throw new NotFoundHttpException('Demande introuvable.');
            }
            $lease = $this->leases->findActiveLeaseForUnitInOrganization($organization, $unit);
            if ($lease === null || $lease->getStatus() !== LeaseStatus::ACTIVE
                || $lease->getStartDate() > $this->dateTime->today()
                || ($lease->getEndDate() !== null && $lease->getEndDate() < $this->dateTime->today())) {
                throw new ConflictHttpException('Aucun bail actif et en cours ne correspond à cette unité. Le suivi ne crée pas de bail automatiquement.');
            }
            $lease = $entityManager->find(Lease::class, $lease->getId(), LockMode::PESSIMISTIC_WRITE)
                ?? throw new ConflictHttpException('Le bail n’est plus disponible. Actualisez la demande.');
            $this->security->checkLeaseAccess($lease, SecurityAction::VIEW_LEASE);
            if ($lease->getOrganization()->getId() !== $organization->getId() || $lease->getStatus() !== LeaseStatus::ACTIVE
                || $lease->getStartDate() > $this->dateTime->today()
                || ($lease->getEndDate() !== null && $lease->getEndDate() < $this->dateTime->today())
                || $this->normalizePhone($lease->getTenant()->getPhone()) !== $this->normalizePhone($inquiry->getPhone())) {
                throw new ConflictHttpException('Le bail a changé ou ne correspond plus à cette organisation.');
            }

            $old = $inquiry->getStatus();
            $inquiry->setConvertedLease($lease)->setStatus(CustomerInquiryStatus::CONVERTED)->setUpdatedAt($this->dateTime->now());
            $entityManager->persist((new CustomerInquiryHistory())->setOrganization($organization)->setInquiry($inquiry)
                ->setActor($this->security->getCurrentUser())->setAction('CONVERTED_TO_LEASE')
                ->setOldStatus($old)->setNewStatus(CustomerInquiryStatus::CONVERTED)
                ->setReason('Rattachée au bail actif ' . $lease->getReference() . '.'));
            $entityManager->flush();
            $this->auditLog->log('CONVERT_CUSTOMER_INQUIRY_TO_LEASE', CustomerInquiry::class, $inquiry->getId(), $organization,
                $this->security->getCurrentUser(), oldValues: ['status' => $old->value],
                newValues: ['status' => CustomerInquiryStatus::CONVERTED->value, 'leaseUuid' => (string) $lease->getUuid()], flush: false);
            $entityManager->flush();

            return $this->mapper->toResponse($inquiry);
        });
    }

    private function parseFutureVisitDate(string $value): \DateTimeImmutable
    {
        $date = $this->dateTime->parseDateTime($value);
        if ($date === null) throw new BadRequestHttpException('La date de visite doit être un instant RFC3339 valide avec fuseau horaire.');
        if ($date <= $this->dateTime->now()) throw new BadRequestHttpException('La date de visite doit être dans le futur.');
        return $date;
    }

    /** Évite de rattacher par erreur une demande au bail actif d'un autre locataire de la même unité. */
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function visitHistory(CustomerInquiry $inquiry, \App\Entity\Identity\User $actor, string $action, CustomerInquiryStatus $oldStatus, CustomerInquiryStatus $newStatus, CustomerVisit $visit, ?string $reason = null): CustomerInquiryHistory
    {
        $detail = sprintf('Rendez-vous %s prévu le %s.', $visit->getUuid(), $visit->getScheduledAt()->format(DATE_ATOM));
        return (new CustomerInquiryHistory())->setOrganization($inquiry->getOrganization())->setInquiry($inquiry)
            ->setActor($actor)->setAction($action)->setOldStatus($oldStatus)->setNewStatus($newStatus)
            ->setReason($reason === null || $reason === '' ? $detail : $detail . ' ' . $reason);
    }

    private function findOrganization(string $uuid): \App\Entity\Identity\Organization
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('Organisation introuvable.');
        }
        return $this->organizations->findOneByUuid($parsed) ?? throw new NotFoundHttpException('Organisation introuvable.');
    }

    private function findInquiry(\App\Entity\Identity\Organization $organization, string $uuid): CustomerInquiry
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('Demande introuvable.');
        }
        return $this->inquiries->findOneByOrganizationAndUuid($organization, $parsed)
            ?? throw new NotFoundHttpException('Demande introuvable.');
    }

    private function checkInquiryCityAccess(CustomerInquiry $inquiry, SecurityAction $action): void
    {
        $city = $inquiry->getListing()->getUnit()->getBuilding()->getParcel()->getCity();
        $this->security->checkCityAccess($city, $action);
    }

    private function assertCityNotificationRole(Organization $organization, \App\Entity\Identity\User $user): void
    {
        if ($this->security->getOrganizationRole($user, $organization) !== OrganizationRole::ADMIN_VILLE) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Cette boîte est réservée aux administrateurs des villes attribuées.');
        }
    }

    private function parseUuid(string $value, string $message): Uuid
    {
        try {
            return Uuid::fromString($value);
        } catch (\InvalidArgumentException) {
            throw new BadRequestHttpException($message);
        }
    }

    private function receipt(CustomerInquiry $inquiry): PublicInquiryReceiptResponse
    {
        return new PublicInquiryReceiptResponse((string) $inquiry->getUuid(), $inquiry->getCreatedAt()->format(DATE_ATOM));
    }
}
