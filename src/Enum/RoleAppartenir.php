<?php

declare(strict_types=1);

namespace App\Enum;

enum RoleAppartenir: string
{
    case Createur = 'createur';
    case Membre = 'membre';
}
