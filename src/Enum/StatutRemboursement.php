<?php

declare(strict_types=1);

namespace App\Enum;

enum StatutRemboursement: string
{
    case EnAttente = 'en_attente';
    case Propose = 'propose';
    case Valide = 'valide';
    case Conteste = 'conteste';
    case Annule = 'annule';
}
