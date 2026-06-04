<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F7: ajoute date_expiration et date_acceptation sur appartenir pour les invitations par lien.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appartenir ADD date_expiration DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE appartenir ADD date_acceptation DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appartenir DROP date_expiration');
        $this->addSql('ALTER TABLE appartenir DROP date_acceptation');
    }
}
