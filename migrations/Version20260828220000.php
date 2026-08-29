<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Site contact details, social links, and FAQ site/order fields for public pages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE faq CHANGE answer answer LONGTEXT NOT NULL');
        $this->addSql("ALTER TABLE faq ADD position INT DEFAULT 0 NOT NULL, ADD is_active TINYINT(1) DEFAULT 1 NOT NULL, ADD site VARCHAR(20) DEFAULT 'both' NOT NULL");

        $this->addSql('CREATE TABLE site_contact (id INT AUTO_INCREMENT NOT NULL, phone VARCHAR(40) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, site VARCHAR(20) DEFAULT \'both\' NOT NULL, is_active TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE site_social (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, link VARCHAR(255) NOT NULL, icon VARCHAR(50) DEFAULT NULL, position INT NOT NULL, is_active TINYINT(1) NOT NULL, site VARCHAR(20) DEFAULT \'both\' NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql("INSERT INTO site_contact (phone, email, address, site, is_active) VALUES ('+243 974 807 116', 'contact@idioma.international', 'Kinshasa, RDC', 'idioma', 1)");
        $this->addSql("INSERT INTO site_contact (phone, email, address, site, is_active) VALUES ('+33 (0)1 84 88 00 01', 'contact@straton.fr', NULL, 'straton', 1)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE site_social');
        $this->addSql('DROP TABLE site_contact');
        $this->addSql('ALTER TABLE faq DROP position, DROP is_active, DROP site');
        $this->addSql('ALTER TABLE faq CHANGE answer answer VARCHAR(255) NOT NULL');
    }
}
