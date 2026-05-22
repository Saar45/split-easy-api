<?php

declare(strict_types=1);

namespace App\Enum;

enum StatutGroupe: string
{
    case Actif = 'actif';
    case Archive = 'archive';
}
