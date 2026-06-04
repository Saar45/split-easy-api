<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RGPD: create preferences_utilisateur table (F9 notifications opt-in, §3.4.5).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE preferences_utilisateur (
                id_utilisateur INT NOT NULL,
                notifications_email TINYINT(1) NOT NULL DEFAULT 1,
                notifications_push  TINYINT(1) NOT NULL DEFAULT 1,
                date_modification   DATETIME    NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id_utilisateur),
                CONSTRAINT fk_prefs_utilisateur
                    FOREIGN KEY (id_utilisateur)
                    REFERENCES utilisateur (id_utilisateur)
                    ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE preferences_utilisateur');
    }
}
