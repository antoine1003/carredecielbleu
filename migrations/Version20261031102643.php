<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261031102643 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE site_setting ADD newsletter_enabled TINYINT NOT NULL, ADD newsletter_list_id INT DEFAULT NULL, ADD newsletter_title VARCHAR(160) NOT NULL, ADD newsletter_text VARCHAR(300) NOT NULL');
        // Textes par défaut (placeholders) pour la ligne de réglages existante.
        $this->addSql("UPDATE site_setting SET newsletter_title = 'Recevoir les nouvelles de l''atelier' WHERE newsletter_title = ''");
        $this->addSql("UPDATE site_setting SET newsletter_text = '[Nouvelles créations, dates des ateliers… Texte d''invitation à écrire par l''artisane]' WHERE newsletter_text = ''");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE site_setting DROP newsletter_enabled, DROP newsletter_list_id, DROP newsletter_title, DROP newsletter_text');
    }
}
