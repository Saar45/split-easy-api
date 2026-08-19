<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819091608 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RGPD: persist CGU consent timestamp on utilisateur.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur ADD cgu_acceptees_le DATETIME DEFAULT NULL');
        // Backfill existing rows with their registration date, since consent was
        // already required by validation at registration time but never persisted.
        $this->addSql('UPDATE utilisateur SET cgu_acceptees_le = date_inscription WHERE cgu_acceptees_le IS NULL');
        $this->addSql('ALTER TABLE utilisateur MODIFY cgu_acceptees_le DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur DROP cgu_acceptees_le');
    }
}
