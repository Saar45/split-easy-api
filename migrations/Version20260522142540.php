<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522142540 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table refresh_token (gesdinet/jwt-refresh-token-bundle).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE refresh_token (
            id INT AUTO_INCREMENT NOT NULL,
            refresh_token VARCHAR(128) NOT NULL,
            username VARCHAR(255) NOT NULL,
            valid DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_C74F2195C74F2195 (refresh_token),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE refresh_token');
    }
}
