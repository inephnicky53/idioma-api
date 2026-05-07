<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260125002022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE IF NOT EXISTS newsletter_subscription (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, unsubscribed_at DATETIME DEFAULT NULL, UNIQUE KEY UNIQ_NEWSLETTER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE IF NOT EXISTS news (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, excerpt VARCHAR(500) DEFAULT NULL, content LONGTEXT NOT NULL, image VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, published_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE news');
        $this->addSql('ALTER TABLE payment ADD transaction_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE translation_request CHANGE phone phone VARCHAR(50) DEFAULT NULL, CHANGE document_type document_type VARCHAR(100) NOT NULL, CHANGE source_language source_language VARCHAR(50) NOT NULL, CHANGE target_language target_language VARCHAR(50) NOT NULL, CHANGE deadline deadline DATE DEFAULT NULL, CHANGE message message LONGTEXT DEFAULT NULL, CHANGE status status VARCHAR(50) NOT NULL');
        $this->addSql('CREATE INDEX idx_status ON translation_request (status)');
        $this->addSql('CREATE INDEX idx_email ON translation_request (email)');
        $this->addSql('CREATE INDEX idx_created_at ON translation_request (created_at)');
    }
}
