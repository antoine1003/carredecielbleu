<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261026195626 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, sumup_name VARCHAR(120) DEFAULT NULL, slug VARCHAR(140) NOT NULL, description LONGTEXT DEFAULT NULL, visible TINYINT NOT NULL, position INT NOT NULL, UNIQUE INDEX UNIQ_64C19C17F786E50 (sumup_name), UNIQUE INDEX UNIQ_64C19C1989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE page (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(160) NOT NULL, slug VARCHAR(160) NOT NULL, content LONGTEXT DEFAULT NULL, meta_description VARCHAR(300) DEFAULT NULL, published TINYINT NOT NULL, in_footer TINYINT NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_140AB620989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, sumup_item_id VARCHAR(64) DEFAULT NULL, sku VARCHAR(64) DEFAULT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, sumup_seo_title VARCHAR(255) DEFAULT NULL, sumup_seo_description LONGTEXT DEFAULT NULL, slug VARCHAR(160) NOT NULL, visible TINYINT NOT NULL, featured TINYINT NOT NULL, purchase_url VARCHAR(500) DEFAULT NULL, story LONGTEXT DEFAULT NULL, technique VARCHAR(255) DEFAULT NULL, dimensions VARCHAR(255) DEFAULT NULL, sold_out_behavior VARCHAR(32) DEFAULT NULL, sync_status VARCHAR(16) NOT NULL, last_synced_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_D34A04ADCC259A50 (sumup_item_id), UNIQUE INDEX UNIQ_D34A04AD989D9B62 (slug), INDEX idx_product_sku (sku), INDEX IDX_D34A04AD12469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product_image (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(64) NOT NULL, alt VARCHAR(255) NOT NULL, width INT NOT NULL, height INT NOT NULL, widths JSON NOT NULL, position INT NOT NULL, product_id INT NOT NULL, INDEX IDX_64617F034584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product_variant (id INT AUTO_INCREMENT NOT NULL, sumup_variant_id VARCHAR(64) DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, price_cents INT DEFAULT NULL, track_inventory TINYINT NOT NULL, stock INT NOT NULL, position INT NOT NULL, sync_status VARCHAR(16) NOT NULL, product_id INT NOT NULL, UNIQUE INDEX UNIQ_209AA41DA4DD71B3 (sumup_variant_id), INDEX IDX_209AA41D4584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE site_setting (id INT AUTO_INCREMENT NOT NULL, site_name VARCHAR(120) NOT NULL, home_title VARCHAR(160) NOT NULL, home_intro LONGTEXT NOT NULL, home_meta_description VARCHAR(300) NOT NULL, city VARCHAR(120) NOT NULL, contact_email VARCHAR(180) DEFAULT NULL, instagram_url VARCHAR(255) DEFAULT NULL, facebook_url VARCHAR(255) DEFAULT NULL, sold_out_behavior VARCHAR(32) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product_image ADD CONSTRAINT FK_64617F034584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_variant ADD CONSTRAINT FK_209AA41D4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2');
        $this->addSql('ALTER TABLE product_image DROP FOREIGN KEY FK_64617F034584665A');
        $this->addSql('ALTER TABLE product_variant DROP FOREIGN KEY FK_209AA41D4584665A');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE page');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_image');
        $this->addSql('DROP TABLE product_variant');
        $this->addSql('DROP TABLE site_setting');
        $this->addSql('DROP TABLE `user`');
    }
}
