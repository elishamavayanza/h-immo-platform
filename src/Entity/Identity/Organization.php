<?php

declare(strict_types=1);

namespace App\Entity\Identity;

use App\Entity\Shared\SoftDeletableEntity;
use App\Enum\OrganizationStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Organization
 *
 * Package  : Identity & Access
 * Table    : organization
 *
 * Entité racine de l'isolation multi-entreprise (multi-tenant) de
 * Soft-IMMO. Toute donnée métier (City, Parcel, Building, Unit,
 * Tenant, Lease, Rent, Payment, AuditLog) est rattachée, directement
 * ou indirectement, à une Organization, et aucune requête applicative
 * ne doit permettre de franchir cette frontière.
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization')]
#[ORM\UniqueConstraint(name: 'uniq_organization_code', columns: ['code'])]
#[ORM\UniqueConstraint(name: 'uniq_organization_slug', columns: ['slug'])]
class Organization extends SoftDeletableEntity
{

    /**
     * Nom officiel de l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 150)]
    private string $name;

    /**
     * Code unique utilisé pour identifier l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 30, unique: true)]
    private string $code;

    /**
     * Référence vers le fichier du logo de l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $logo = null;

    /**
     * Adresse e-mail officielle de l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $email;

    /**
     * Numéro de téléphone principal de l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 30)]
    private string $phone;

    /**
     * Adresse physique de l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $address = null;

    /**
     * Ville où se situe le siège de l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $city = null;

    /**
     * Pays où se situe le siège de l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $country = null;

    /**
     * Identifiant lisible utilisé dans l'URL de la vitrine publique
     * (`/api/public/organizations/{slug}`).
     *
     * Volontairement distinct de `code` : `code` est un identifiant interne
     * propre à l'organization et n'a aucune vocation à être présenté à un
     * visiteur. Nullable pour que les Organizations existantes — créées sans
     * vitrine — restent exploitables ; une Organization sans slug n'a pas de
     * page publique, ce qui n'est pas la même chose qu'une page dépubliée.
     */
    #[ORM\Column(type: Types::STRING, length: 60, nullable: true, unique: true)]
    private ?string $slug = null;

    /**
     * Texte de présentation affiché sur la page publique.
     *
     * Distinct de `name` (identité administrative) et de `address` (coordonnée
     * postale) : c'est le seul champ fait pour être lu par un visiteur.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $publicDescription = null;

    /**
     * Interrupteur de mise en ligne de la vitrine.
     *
     * `false` renvoie un 404 sur les routes publiques sans rien supprimer :
     * un PATRON doit pouvoir retirer sa page de la circulation tout en gardant
     * son texte et ses annonces. La disponibilité des unités, elle, ne dépend
     * pas de ce champ mais du bail actif, recalculé à chaque lecture.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isPubliclyListed = true;

    /**
     * État actuel de l'organisation dans la plateforme.
     */
    #[ORM\Column(type: Types::STRING, enumType: OrganizationStatus::class)]
    private OrganizationStatus $status = OrganizationStatus::ACTIVE;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug === null ? null : mb_strtolower(trim($slug));

        return $this;
    }

    public function getPublicDescription(): ?string
    {
        return $this->publicDescription;
    }

    public function setPublicDescription(?string $publicDescription): static
    {
        $this->publicDescription = $publicDescription;

        return $this;
    }

    public function isPubliclyListed(): bool
    {
        return $this->isPubliclyListed;
    }

    public function setIsPubliclyListed(bool $isPubliclyListed): static
    {
        $this->isPubliclyListed = $isPubliclyListed;

        return $this;
    }

    /**
     * La vitrine n'est-elle pas accessible au public ?
     *
     * Nomé par rapport à ce qui est demandé partout ailleurs dans l'entité
     * (`isActive()`, `isSuspended()`) pour qu'un appelant puisse lire la
     * condition sans connaître le sens du drapeau.
     */
    public function isPubliclyHidden(): bool
    {
        return !$this->isPubliclyListed;
    }

    public function getStatus(): OrganizationStatus
    {
        return $this->status;
    }

    public function setStatus(OrganizationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}
