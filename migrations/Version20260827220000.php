<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bilateral attendance (teacher + student) and hour refunds for teacher no-show';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE planning_attendance ADD party VARCHAR(20) DEFAULT 'student' NOT NULL");
        $this->addSql('ALTER TABLE planning_attendance ADD hours_refunded DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planning_attendance DROP party');
        $this->addSql('ALTER TABLE planning_attendance DROP hours_refunded');
    }
}
