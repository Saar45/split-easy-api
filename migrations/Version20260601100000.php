<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F6: ajoute la valeur ENUM annule au statut remboursement et passe le défaut à propose.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE remboursement MODIFY statut ENUM('en_attente','propose','valide','conteste','annule') NOT NULL DEFAULT 'propose'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE remboursement SET statut = 'propose' WHERE statut = 'annule'"
        );
        $this->addSql(
            "ALTER TABLE remboursement MODIFY statut ENUM('en_attente','propose','valide','conteste') NOT NULL DEFAULT 'en_attente'"
        );
    }
}
