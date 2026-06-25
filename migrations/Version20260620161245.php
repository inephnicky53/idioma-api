<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260620161245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD email_verification_token VARCHAR(255) DEFAULT NULL, ADD phone_otp_expires_at DATETIME DEFAULT NULL, ADD is_phone_verified TINYINT(1) DEFAULT 0 NOT NULL, CHANGE otp_expires_at email_verification_token_expires_at DATETIME DEFAULT NULL, CHANGE is_verified is_email_verified TINYINT(1) DEFAULT 0 NOT NULL, CHANGE otp phone_otp VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` ADD otp_expires_at DATETIME DEFAULT NULL, ADD is_verified TINYINT(1) DEFAULT 0 NOT NULL, DROP email_verification_token, DROP email_verification_token_expires_at, DROP is_email_verified, DROP phone_otp_expires_at, DROP is_phone_verified, CHANGE phone_otp otp VARCHAR(10) DEFAULT NULL');
    }
}
