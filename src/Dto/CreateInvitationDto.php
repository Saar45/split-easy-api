<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateInvitationDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
        #[Assert\Email(message: 'L\'email n\'est pas valide.')]
        #[Assert\Length(max: 255, maxMessage: 'L\'email ne peut pas dépasser 255 caractères.')]
        public readonly ?string $email = null,
    ) {
    }
}
