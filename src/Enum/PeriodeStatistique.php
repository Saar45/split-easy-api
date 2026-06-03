<?php

declare(strict_types=1);

namespace App\Enum;

enum PeriodeStatistique: string
{
    case Semaine = 'semaine';
    case Mois = 'mois';
    case Annee = 'annee';
}
