<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Persist Planning::isTrial.
 *
 * It used to be a plain PHP property, so the flag was lost on reload: every
 * booking looked non-trial afterwards and cancelling a free trial credited a
 * prepaid hour that had never been paid for.
 *
 * Existing rows are backfilled: a session is a trial when it is the user's
 * first one with that teacher.
 */
final class Version20260829203808 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add planning.is_trial and backfill it from each user\'s first session per teacher.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planning ADD is_trial TINYINT(1) DEFAULT 0 NOT NULL');

        $this->addSql(<<<'SQL'
            UPDATE planning p
            JOIN planning_user pu ON pu.planning_id = p.id
            SET p.is_trial = 1
            WHERE p.teacher_id IS NOT NULL
              AND p.id = (
                  SELECT first_id FROM (
                      SELECT MIN(p2.id) AS first_id
                      FROM planning p2
                      JOIN planning_user pu2 ON pu2.planning_id = p2.id
                      WHERE pu2.user_id = pu.user_id
                        AND p2.teacher_id = p.teacher_id
                  ) AS earliest
              )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planning DROP is_trial');
    }
}
