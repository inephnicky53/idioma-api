<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260125034557 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_created_at ON translation_request');
        $this->addSql('DROP INDEX idx_status ON translation_request');
        $this->addSql('DROP INDEX idx_email ON translation_request');
        $this->addSql('ALTER TABLE translation_request CHANGE phone phone VARCHAR(20) DEFAULT NULL, CHANGE document_type document_type VARCHAR(255) NOT NULL, CHANGE source_language source_language VARCHAR(100) NOT NULL, CHANGE target_language target_language VARCHAR(100) NOT NULL, CHANGE deadline deadline VARCHAR(100) NOT NULL, CHANGE message message LONGTEXT NOT NULL, CHANGE status status VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE translation_request CHANGE phone phone VARCHAR(50) DEFAULT NULL, CHANGE document_type document_type VARCHAR(100) NOT NULL, CHANGE source_language source_language VARCHAR(50) NOT NULL, CHANGE target_language target_language VARCHAR(50) NOT NULL, CHANGE deadline deadline DATE DEFAULT NULL, CHANGE message message LONGTEXT DEFAULT NULL, CHANGE status status VARCHAR(50) NOT NULL');
        $this->addSql('CREATE INDEX idx_created_at ON translation_request (created_at)');
        $this->addSql('CREATE INDEX idx_status ON translation_request (status)');
        $this->addSql('CREATE INDEX idx_email ON translation_request (email)');
    }
}
