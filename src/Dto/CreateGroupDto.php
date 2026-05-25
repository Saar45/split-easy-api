<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateGroupDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
        #[Assert\Length(min: 2, max: 100, minMessage: 'Le nom doit faire au moins 2 caractères.', maxMessage: 'Le nom doit faire 100 caractères max.')]
        public readonly string $nom = '',

        #[Assert\Length(max: 255)]
        public readonly ?string $description = null,

        #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'La couleur doit être au format hexadécimal #RRGGBB.')]
        public readonly ?string $couleur = null,
    ) {
    }
}
