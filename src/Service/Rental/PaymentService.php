<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\PaymentRequest;
use App\Entity\Identity\User;
use App\Entity\Rental\Payment;
use App\Entity\Rental\Rent;
use App\Mapper\Rental\PaymentMapper;
use App\Repository\Rental\PaymentRepository;
use App\Repository\Rental\RentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class PaymentService
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private RentRepository $rentRepository,
        private PaymentMapper $paymentMapper,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {
    }

    public function recordPayment(PaymentRequest $request, User $currentUser): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, null, ['create']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données du paiement sont invalides.')
                ->autoInitFlush();
        }

        /** @var Rent|null $rent */
        $rent = $this->rentRepository->findOneBy(['uuid' => $request->rentUuid]);
        if (!$rent) {
            return $feedback
                ->addError('rentUuid', 'Échéance de loyer introuvable.')
                ->setFlushDescriptionWithError('L\'échéance de loyer spécifiée n\'existe pas.')
                ->autoInitFlush();
        }

        $payment = $this->paymentMapper->toEntity($request, $rent, $currentUser);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $feedback
            ->setData($this->paymentMapper->toResponse($payment))
            ->setFlushDescription('Le paiement a été enregistré avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    public function getPaymentByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();

        /** @var Payment|null $payment */
        $payment = $this->paymentRepository->findOneBy(['uuid' => $uuid]);
        if (!$payment) {
            return $feedback
                ->setErrorFlushDescription('Paiement introuvable.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        return $feedback
            ->setData($this->paymentMapper->toResponse($payment))
            ->setFlushDescription('Paiement récupéré avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }
}
