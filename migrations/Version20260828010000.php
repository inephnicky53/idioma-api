<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend otp.phone to store email destinations (email change, registration OTP).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE otp CHANGE phone phone VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE otp CHANGE phone phone VARCHAR(15) NOT NULL');
    }
}
