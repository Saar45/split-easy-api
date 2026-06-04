<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F9: add titre column on notification table and widen message to TEXT.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification ADD titre VARCHAR(150) NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE notification ALTER titre DROP DEFAULT');
        $this->addSql('ALTER TABLE notification MODIFY message TEXT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification MODIFY message VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE notification DROP titre');
    }
}
