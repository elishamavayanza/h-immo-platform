<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'CustomerInquiryRequest')]
final readonly class CustomerInquiryRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['information', 'visit'])]
        #[OA\Property(type: 'string', enum: ['information', 'visit'])]
        public string $type,
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        #[OA\Property(maxLength: 120)]
        public string $name,
        #[Assert\NotBlank]
        #[Assert\Length(min: 7, max: 30)]
        #[Assert\Regex(pattern: '/^[+0-9().\\s-]+$/')]
        #[OA\Property(maxLength: 30)]
        public string $phone,
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        #[OA\Property(type: 'string', format: 'email', nullable: true)]
        public ?string $email = null,
        #[Assert\Length(max: 2000)]
        #[OA\Property(type: 'string', maxLength: 2000, nullable: true)]
        public ?string $message = null,
        #[Assert\IsTrue(message: 'Votre accord est nécessaire pour être recontacté au sujet de cette demande.')]
        #[OA\Property(type: 'boolean', example: true)]
        public bool $privacyConsent = false,
        #[Assert\NotBlank(message: 'Rechargez le formulaire avant de réessayer.')]
        #[Assert\Length(max: 200)]
        #[OA\Property(type: 'string', maxLength: 200, description: 'Jeton temporaire signé par le serveur.')]
        public ?string $challengeToken = null,
        #[Assert\Blank(message: 'La demande n’a pas pu être vérifiée.')]
        #[Assert\Length(max: 200)]
        #[OA\Property(type: 'string', maxLength: 200, nullable: true, description: 'Champ piège anti-robot, doit rester vide.')]
        public ?string $website = null,
        #[Assert\Length(max: 2048)]
        #[OA\Property(type: 'string', maxLength: 2048, nullable: true, description: 'Jeton Turnstile vérifié côté serveur.')]
        public ?string $turnstileToken = null,
    ) {
    }
}
