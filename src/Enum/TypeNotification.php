<?php

declare(strict_types=1);

namespace App\Enum;

enum TypeNotification: string
{
    case NouvelleDepense = 'nouvelle_depense';
    case Invitation = 'invitation';
    case RappelSolde = 'rappel_solde';
    case ValidationRemboursement = 'validation_remboursement';
    case ModificationDepense = 'modification_depense';
}
