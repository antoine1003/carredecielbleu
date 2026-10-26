<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261026202533 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE registration (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(80) NOT NULL, last_name VARCHAR(80) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(30) DEFAULT NULL, show_name_publicly TINYINT NOT NULL, cancel_token VARCHAR(64) NOT NULL, submission_id VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, session_id INT NOT NULL, UNIQUE INDEX UNIQ_62A8A7A7AF53DC87 (cancel_token), UNIQUE INDEX uniq_registration_session_email (session_id, email), INDEX IDX_62A8A7A7613FECDF (session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE workshop (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(160) NOT NULL, slug VARCHAR(160) NOT NULL, summary VARCHAR(300) DEFAULT NULL, description LONGTEXT DEFAULT NULL, duration VARCHAR(120) DEFAULT NULL, price_info VARCHAR(160) DEFAULT NULL, default_location VARCHAR(255) DEFAULT NULL, default_capacity INT NOT NULL, published TINYINT NOT NULL, UNIQUE INDEX UNIQ_9B6F02C4989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE workshop_session (id INT AUTO_INCREMENT NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, capacity INT DEFAULT NULL, status VARCHAR(16) NOT NULL, confirmed_at DATETIME DEFAULT NULL, workshop_id INT NOT NULL, INDEX idx_session_starts_at (starts_at), INDEX IDX_920BC7C31FDCE57C (workshop_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A7613FECDF FOREIGN KEY (session_id) REFERENCES workshop_session (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workshop_session ADD CONSTRAINT FK_920BC7C31FDCE57C FOREIGN KEY (workshop_id) REFERENCES workshop (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A7613FECDF');
        $this->addSql('ALTER TABLE workshop_session DROP FOREIGN KEY FK_920BC7C31FDCE57C');
        $this->addSql('DROP TABLE registration');
        $this->addSql('DROP TABLE workshop');
        $this->addSql('DROP TABLE workshop_session');
    }
}
