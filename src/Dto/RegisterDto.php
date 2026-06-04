<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
        #[Assert\Length(min: 2, max: 50)]
        public readonly string $nom = '',

        #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
        #[Assert\Length(min: 2, max: 50)]
        public readonly string $prenom = '',

        #[Assert\NotBlank(message: "L'email est obligatoire.")]
        #[Assert\Email(message: "L'email n'est pas valide.")]
        #[Assert\Length(max: 255)]
        public readonly string $email = '',

        #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.')]
        #[Assert\Length(min: 8, max: 255, minMessage: 'Le mot de passe doit faire au moins 8 caractères.')]
        #[Assert\Regex(pattern: '/[a-z]/', message: 'Doit contenir au moins une minuscule.')]
        #[Assert\Regex(pattern: '/[A-Z]/', message: 'Doit contenir au moins une majuscule.')]
        #[Assert\Regex(pattern: '/[0-9]/', message: 'Doit contenir au moins un chiffre.')]
        public readonly string $motDePasse = '',

        // Consentement RGPD à l'inscription — dossier §3.4.5.
        #[Assert\IsTrue(message: 'Vous devez accepter les CGU et la politique de confidentialité.')]
        public readonly bool $cguAcceptees = false,
    ) {
    }
}
