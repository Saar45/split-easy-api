<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateExpenseDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'La description est obligatoire.')]
        #[Assert\Length(min: 2, max: 100, minMessage: 'La description doit faire au moins 2 caractères.', maxMessage: 'La description doit faire 100 caractères max.')]
        public readonly string $description = '',

        #[Assert\NotNull(message: 'Le montant est obligatoire.')]
        #[Assert\Positive(message: 'Le montant doit être positif.')]
        #[Assert\LessThanOrEqual(value: 999999.99, message: 'Le montant ne peut pas dépasser 999999.99.')]
        public readonly ?float $montant = null,

        #[Assert\Date(message: 'La date doit être au format YYYY-MM-DD.')]
        public readonly ?string $date_depense = null,

        #[Assert\NotNull(message: 'La catégorie est obligatoire.')]
        #[Assert\Positive(message: 'L\'identifiant de catégorie doit être positif.')]
        public readonly ?int $id_categorie = null,

        #[Assert\NotNull(message: 'Les bénéficiaires sont obligatoires.')]
        #[Assert\Count(min: 1, minMessage: 'Au moins un bénéficiaire est requis.')]
        #[Assert\Unique(message: 'Les bénéficiaires ne doivent pas contenir de doublons.')]
        #[Assert\All([
            new Assert\Type(type: 'integer', message: 'Chaque identifiant de bénéficiaire doit être un entier.'),
            new Assert\Positive(message: 'Chaque identifiant de bénéficiaire doit être positif.'),
        ])]
        public readonly ?array $beneficiaire_ids = null,
    ) {
    }
}
