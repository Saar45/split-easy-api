<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateRemboursementDto
{
    public function __construct(
        #[Assert\NotNull(message: 'Le créancier est obligatoire.')]
        #[Assert\Positive(message: 'L\'identifiant du créancier doit être positif.')]
        public readonly ?int $id_crediteur = null,

        #[Assert\NotNull(message: 'Le montant est obligatoire.')]
        #[Assert\Positive(message: 'Le montant doit être strictement positif.')]
        #[Assert\LessThanOrEqual(value: 999999.99, message: 'Le montant ne peut pas dépasser 999999.99.')]
        public readonly ?float $montant = null,
    ) {
    }
}
