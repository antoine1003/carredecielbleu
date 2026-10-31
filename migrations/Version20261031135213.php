<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261031135213 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ateliers : acompte (montant, lien SumUp), règles d\'annulation, suivi des acomptes par inscription.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE registration ADD terms_accepted_at DATETIME DEFAULT NULL, ADD deposit_paid_at DATETIME DEFAULT NULL, ADD deposit_reminder_sent_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE site_setting ADD deposit_payment_days INT NOT NULL, ADD refund_days_before INT NOT NULL, ADD cancellation_terms LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE workshop ADD deposit_cents INT DEFAULT NULL, ADD deposit_url VARCHAR(500) DEFAULT NULL');
        // Valeurs par défaut pour la ligne de réglages existante.
        $this->addSql("UPDATE site_setting SET deposit_payment_days = 5, refund_days_before = 7, cancellation_terms = 'Si l''atelier est annulé de mon fait, l''acompte est remboursé en totalité.'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE registration DROP terms_accepted_at, DROP deposit_paid_at, DROP deposit_reminder_sent_at');
        $this->addSql('ALTER TABLE site_setting DROP deposit_payment_days, DROP refund_days_before, DROP cancellation_terms');
        $this->addSql('ALTER TABLE workshop DROP deposit_cents, DROP deposit_url');
    }
}
