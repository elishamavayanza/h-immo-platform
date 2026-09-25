<?php

declare(strict_types=1);

namespace App\Entity\Shared;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * BaseEntity
 *
 * Classe mère abstraite (MappedSuperclass) de toute la hiérarchie d'entités
 * de Soft-IMMO. Elle porte les deux attributs communs à 100% des tables :
 *   - id   : clé primaire technique auto-incrémentée (usage interne / FK)
 *   - uuid : identifiant public unique, exposé aux clients (API, URLs...)
 *
 * Choix de conception :
 *   - Utilise #[ORM\MappedSuperclass] et non l'héritage d'entités Doctrine
 *     (SINGLE_TABLE / JOINED), car les classes filles ne partagent aucune
 *     polymorphie métier : il s'agit uniquement d'une factorisation de
 *     colonnes, chaque classe fille restant une table indépendante.
 *   - Aucune méthode métier n'est placée ici : uniquement des accesseurs.
 *   - L'UUID est généré une seule fois, à la construction de l'objet.
 */
#[ORM\MappedSuperclass]
abstract class BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    /**
     * Identifiant interne auto-incrémenté utilisé pour la base de données et les relations.
     */
    protected ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    /**
     * Identifiant UUID public et unique utilisé dans l'API et les URLs.
     */
    protected Uuid $uuid;

    public function __construct()
    {
        $this->uuid = Uuid::v4();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }
}
