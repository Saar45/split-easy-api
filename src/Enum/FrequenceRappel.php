<?php

declare(strict_types=1);

namespace App\Enum;

enum FrequenceRappel: string
{
    case Jamais = 'jamais';
    case Hebdomadaire = 'hebdomadaire';
    case Mensuel = 'mensuel';
}
