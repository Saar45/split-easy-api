<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateGroupDto
{
    public function __construct(
        #[Assert\Length(min: 2, max: 100)]
        public readonly ?string $nom = null,

        #[Assert\Length(max: 255)]
        public readonly ?string $description = null,

        #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'La couleur doit être au format hexadécimal #RRGGBB.')]
        public readonly ?string $couleur = null,
    ) {
    }
}
