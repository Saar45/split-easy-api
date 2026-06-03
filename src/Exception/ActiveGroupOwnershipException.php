<?php

declare(strict_types=1);

namespace App\Exception;

final class ActiveGroupOwnershipException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Vous êtes créateur de groupes actifs. Transférez la propriété ou supprimez ces groupes avant de supprimer votre compte.'
        );
    }
}
