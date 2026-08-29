<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Store user.banned_at as a real datetime.
 *
 * The property was typed `?DateTimeInterface`. Doctrine cannot infer a column
 * type from the interface (it can from DateTimeImmutable, as $lastLoginAt right
 * above it does), so it fell back to `string` and the column was created as
 * VARCHAR(255). Banning anyone would then have thrown "object of class
 * DateTimeImmutable could not be converted to string" on flush.
 */
final class Version20260829210500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert user.banned_at from VARCHAR to DATETIME (datetime_immutable).';
    }

    public function up(Schema $schema): void
    {
        // Defensive: local rows are all NULL, but another environment could hold
        // text that MySQL cannot coerce, which would abort the ALTER.
        $this->addSql(<<<'SQL'
            UPDATE `user`
            SET banned_at = NULL
            WHERE banned_at IS NOT NULL
              AND STR_TO_DATE(banned_at, '%Y-%m-%d %H:%i:%s') IS NULL
            SQL);

        $this->addSql(
            "ALTER TABLE `user` CHANGE banned_at banned_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` CHANGE banned_at banned_at VARCHAR(255) DEFAULT NULL');
    }
}
