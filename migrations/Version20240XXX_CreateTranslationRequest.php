<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240XXX_CreateTranslationRequest extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create translation_request table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE translation_request (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            document_type VARCHAR(100) NOT NULL,
            source_language VARCHAR(50) NOT NULL,
            target_language VARCHAR(50) NOT NULL,
            deadline DATE DEFAULT NULL,
            message LONGTEXT DEFAULT NULL,
            status VARCHAR(50) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY(id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_email (email)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE translation_request');
    }
}