<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250920171953 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE teacher_certification ADD proof_image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE teacher_formation ADD proof_image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD is_newsletter_subscribed TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE teacher_certification DROP proof_image');
        $this->addSql('ALTER TABLE teacher_formation DROP proof_image');
        $this->addSql('ALTER TABLE `user` DROP is_newsletter_subscribed');
    }
}
