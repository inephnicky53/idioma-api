<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-user favorite teachers (synced likes) — user_favorite_teachers join table.
 */
final class Version20260830120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_favorite_teachers join table for synced teacher favorites.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_favorite_teachers (user_id INT NOT NULL, teacher_id INT NOT NULL, INDEX IDX_UFT_USER (user_id), INDEX IDX_UFT_TEACHER (teacher_id), PRIMARY KEY(user_id, teacher_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_favorite_teachers ADD CONSTRAINT FK_UFT_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_favorite_teachers ADD CONSTRAINT FK_UFT_TEACHER FOREIGN KEY (teacher_id) REFERENCES teacher (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_favorite_teachers');
    }
}
