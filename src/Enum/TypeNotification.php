<?php

declare(strict_types=1);

namespace App\Enum;

enum TypeNotification: string
{
    case InvitationRecue = 'invitation_recue';
    case InvitationAcceptee = 'invitation_acceptee';
    case InvitationRefusee = 'invitation_refusee';
    case DepenseAjoutee = 'depense_ajoutee';
    case RemboursementPropose = 'remboursement_propose';
    case RemboursementAccepte = 'remboursement_accepte';
    case RemboursementRejete = 'remboursement_rejete';
    case RemboursementAnnule = 'remboursement_annule';
}
