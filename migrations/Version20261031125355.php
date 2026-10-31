<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261031125355 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ateliers : photos (workshop_image) et lien vidéo.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE workshop_image (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(64) NOT NULL, alt VARCHAR(255) NOT NULL, width INT NOT NULL, height INT NOT NULL, widths JSON NOT NULL, position INT NOT NULL, workshop_id INT NOT NULL, INDEX IDX_2DBF57451FDCE57C (workshop_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE workshop_image ADD CONSTRAINT FK_2DBF57451FDCE57C FOREIGN KEY (workshop_id) REFERENCES workshop (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workshop ADD video_url VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE workshop_image DROP FOREIGN KEY FK_2DBF57451FDCE57C');
        $this->addSql('DROP TABLE workshop_image');
        $this->addSql('ALTER TABLE workshop DROP video_url');
    }
}
