<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260522093906 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial Split-Easy — 9 tables alignées sur le MPD du dossier (Jalon 3 §6.5).';
    }

    public function up(Schema $schema): void
    {
        // Tables — auto-générées par make:migration depuis les entités Doctrine.
        $this->addSql('CREATE TABLE appartenir (date_adhesion DATETIME DEFAULT NULL, statut_invitation ENUM(\'en_attente\',\'acceptee\',\'refusee\',\'expiree\') NOT NULL DEFAULT \'en_attente\', token_invitation VARCHAR(250) NOT NULL, date_invitation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, role ENUM(\'createur\',\'membre\') NOT NULL DEFAULT \'membre\', id_utilisateur INT NOT NULL, id_groupe INT NOT NULL, INDEX IDX_A2A0D90C50EAE44 (id_utilisateur), INDEX idx_appartenir_groupe (id_groupe), UNIQUE INDEX idx_appartenir_token (token_invitation), PRIMARY KEY (id_utilisateur, id_groupe)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE categorie (id_categorie INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(50) NOT NULL, icone VARCHAR(50) DEFAULT NULL, couleur VARCHAR(7) DEFAULT NULL, ordre_affichage INT DEFAULT NULL, UNIQUE INDEX idx_categorie_libelle (libelle), PRIMARY KEY (id_categorie)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE depense (id_depense INT AUTO_INCREMENT NOT NULL, description VARCHAR(100) NOT NULL, montant NUMERIC(10, 2) NOT NULL, date_depense DATE NOT NULL, date_creation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, chemin_ticket VARCHAR(255) DEFAULT NULL, type_repartition ENUM(\'equitable\',\'personnalisee\',\'pourcentage\') NOT NULL DEFAULT \'equitable\', date_modification DATETIME DEFAULT NULL, id_categorie INT NOT NULL, id_utilisateur INT NOT NULL, id_groupe INT NOT NULL, INDEX idx_depense_groupe (id_groupe), INDEX idx_depense_utilisateur (id_utilisateur), INDEX idx_depense_categorie (id_categorie), INDEX idx_depense_date (date_depense), PRIMARY KEY (id_depense)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE groupe (id_groupe INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, couleur VARCHAR(7) DEFAULT NULL, description LONGTEXT DEFAULT NULL, date_creation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, statut ENUM(\'actif\',\'archive\') NOT NULL DEFAULT \'actif\', PRIMARY KEY (id_groupe)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification (id_notification INT AUTO_INCREMENT NOT NULL, type_notification VARCHAR(75) NOT NULL, message VARCHAR(255) NOT NULL, est_lu TINYINT DEFAULT 0 NOT NULL, date_creation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, date_lecture DATETIME DEFAULT NULL, reference_type VARCHAR(50) DEFAULT NULL, reference_id INT DEFAULT NULL, id_utilisateur INT NOT NULL, INDEX idx_notification_utilisateur (id_utilisateur), INDEX idx_notification_lecture (id_utilisateur, est_lu), INDEX idx_notification_reference (reference_type, reference_id), PRIMARY KEY (id_notification)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE preference_notification (id_preference INT AUTO_INCREMENT NOT NULL, type_notification VARCHAR(75) NOT NULL, est_active TINYINT DEFAULT 1 NOT NULL, canal_email TINYINT DEFAULT 1 NOT NULL, frequence_rappel ENUM(\'jamais\',\'hebdomadaire\',\'mensuel\') NOT NULL DEFAULT \'hebdomadaire\', id_utilisateur INT NOT NULL, INDEX idx_preference_utilisateur (id_utilisateur), UNIQUE INDEX idx_preference_unique (id_utilisateur, type_notification), PRIMARY KEY (id_preference)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE remboursement (id_remboursement INT AUTO_INCREMENT NOT NULL, montant NUMERIC(10, 2) NOT NULL, statut ENUM(\'en_attente\',\'propose\',\'valide\',\'conteste\') NOT NULL DEFAULT \'en_attente\', date_creation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, date_proposition DATETIME DEFAULT NULL, date_validation DATETIME DEFAULT NULL, id_groupe INT NOT NULL, id_debiteur INT NOT NULL, id_crediteur INT NOT NULL, INDEX idx_remboursement_groupe (id_groupe), INDEX idx_remboursement_debiteur (id_debiteur), INDEX idx_remboursement_crediteur (id_crediteur), PRIMARY KEY (id_remboursement)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE repartir (montant_part NUMERIC(10, 2) NOT NULL, pourcentage NUMERIC(5, 2) DEFAULT NULL, id_utilisateur INT NOT NULL, id_depense INT NOT NULL, INDEX IDX_6553119F50EAE44 (id_utilisateur), INDEX idx_repartir_depense (id_depense), PRIMARY KEY (id_utilisateur, id_depense)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id_utilisateur INT AUTO_INCREMENT NOT NULL, nom VARCHAR(50) NOT NULL, prenom VARCHAR(50) NOT NULL, email VARCHAR(255) NOT NULL, mot_de_passe VARCHAR(255) NOT NULL, photo_profil VARCHAR(255) DEFAULT NULL, date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, email_verifie TINYINT DEFAULT 0 NOT NULL, token_reinitialisation VARCHAR(100) DEFAULT NULL, date_token_reinitialisation DATETIME DEFAULT NULL, UNIQUE INDEX idx_utilisateur_email (email), PRIMARY KEY (id_utilisateur)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE appartenir ADD CONSTRAINT FK_A2A0D90C50EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE appartenir ADD CONSTRAINT FK_A2A0D90C228E39CC FOREIGN KEY (id_groupe) REFERENCES groupe (id_groupe)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_34059757C9486A13 FOREIGN KEY (id_categorie) REFERENCES categorie (id_categorie)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_3405975750EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_34059757228E39CC FOREIGN KEY (id_groupe) REFERENCES groupe (id_groupe)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA50EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE preference_notification ADD CONSTRAINT FK_42D2B37D50EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE remboursement ADD CONSTRAINT FK_C0C0D9EF228E39CC FOREIGN KEY (id_groupe) REFERENCES groupe (id_groupe)');
        $this->addSql('ALTER TABLE remboursement ADD CONSTRAINT FK_C0C0D9EF2FCAE631 FOREIGN KEY (id_debiteur) REFERENCES utilisateur (id_utilisateur) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE remboursement ADD CONSTRAINT FK_C0C0D9EF756C1B94 FOREIGN KEY (id_crediteur) REFERENCES utilisateur (id_utilisateur) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repartir ADD CONSTRAINT FK_6553119F50EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repartir ADD CONSTRAINT FK_6553119FEA983E27 FOREIGN KEY (id_depense) REFERENCES depense (id_depense) ON DELETE CASCADE');

        // Contraintes CHECK du MPD (dossier §6.5.3).
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT chk_depense_montant CHECK (montant > 0 AND montant <= 999999.99)');
        $this->addSql('ALTER TABLE remboursement ADD CONSTRAINT chk_remboursement_montant CHECK (montant > 0)');
        $this->addSql('ALTER TABLE remboursement ADD CONSTRAINT chk_remboursement_debiteur_crediteur CHECK (id_debiteur != id_crediteur)');
        $this->addSql('ALTER TABLE repartir ADD CONSTRAINT chk_repartir_montant CHECK (montant_part >= 0)');
        $this->addSql('ALTER TABLE repartir ADD CONSTRAINT chk_repartir_pourcentage CHECK (pourcentage IS NULL OR (pourcentage >= 0 AND pourcentage <= 100))');
    }

    public function down(Schema $schema): void
    {
        // Suppression des contraintes CHECK avant FK.
        $this->addSql('ALTER TABLE depense DROP CONSTRAINT chk_depense_montant');
        $this->addSql('ALTER TABLE remboursement DROP CONSTRAINT chk_remboursement_montant');
        $this->addSql('ALTER TABLE remboursement DROP CONSTRAINT chk_remboursement_debiteur_crediteur');
        $this->addSql('ALTER TABLE repartir DROP CONSTRAINT chk_repartir_montant');
        $this->addSql('ALTER TABLE repartir DROP CONSTRAINT chk_repartir_pourcentage');


        $this->addSql('ALTER TABLE appartenir DROP FOREIGN KEY FK_A2A0D90C50EAE44');
        $this->addSql('ALTER TABLE appartenir DROP FOREIGN KEY FK_A2A0D90C228E39CC');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_34059757C9486A13');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_3405975750EAE44');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_34059757228E39CC');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA50EAE44');
        $this->addSql('ALTER TABLE preference_notification DROP FOREIGN KEY FK_42D2B37D50EAE44');
        $this->addSql('ALTER TABLE remboursement DROP FOREIGN KEY FK_C0C0D9EF228E39CC');
        $this->addSql('ALTER TABLE remboursement DROP FOREIGN KEY FK_C0C0D9EF2FCAE631');
        $this->addSql('ALTER TABLE remboursement DROP FOREIGN KEY FK_C0C0D9EF756C1B94');
        $this->addSql('ALTER TABLE repartir DROP FOREIGN KEY FK_6553119F50EAE44');
        $this->addSql('ALTER TABLE repartir DROP FOREIGN KEY FK_6553119FEA983E27');
        $this->addSql('DROP TABLE appartenir');
        $this->addSql('DROP TABLE categorie');
        $this->addSql('DROP TABLE depense');
        $this->addSql('DROP TABLE groupe');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE preference_notification');
        $this->addSql('DROP TABLE remboursement');
        $this->addSql('DROP TABLE repartir');
        $this->addSql('DROP TABLE utilisateur');
    }
}
