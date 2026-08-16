<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260816001406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE course_lesson (id INT AUTO_INCREMENT NOT NULL, section_id INT NOT NULL, title VARCHAR(255) NOT NULL, type VARCHAR(20) DEFAULT \'video\' NOT NULL, duration_minutes INT DEFAULT 0 NOT NULL, position INT DEFAULT 0 NOT NULL, is_preview TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_564CB5BED823E37A (section_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE course_section (id INT AUTO_INCREMENT NOT NULL, course_id INT NOT NULL, title VARCHAR(255) NOT NULL, position INT DEFAULT 0 NOT NULL, INDEX IDX_25B07F03591CC992 (course_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE course_lesson ADD CONSTRAINT FK_564CB5BED823E37A FOREIGN KEY (section_id) REFERENCES course_section (id)');
        $this->addSql('ALTER TABLE course_section ADD CONSTRAINT FK_25B07F03591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
        $this->addSql('ALTER TABLE course ADD is_bestseller TINYINT(1) DEFAULT 0 NOT NULL, ADD is_new TINYINT(1) DEFAULT 0 NOT NULL, ADD has_certificate TINYINT(1) DEFAULT 1 NOT NULL, ADD has_lifetime_access TINYINT(1) DEFAULT 1 NOT NULL, ADD quizzes_count INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course_lesson DROP FOREIGN KEY FK_564CB5BED823E37A');
        $this->addSql('ALTER TABLE course_section DROP FOREIGN KEY FK_25B07F03591CC992');
        $this->addSql('DROP TABLE course_lesson');
        $this->addSql('DROP TABLE course_section');
        $this->addSql('ALTER TABLE course DROP is_bestseller, DROP is_new, DROP has_certificate, DROP has_lifetime_access, DROP quizzes_count');
    }
}
