<?php

declare(strict_types=1);

namespace App\Enum;

enum TypeRepartition: string
{
    case Equitable = 'equitable';
    case Personnalisee = 'personnalisee';
    case Pourcentage = 'pourcentage';
}
