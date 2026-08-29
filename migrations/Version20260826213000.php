<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Classroom: Vimeo lesson URLs, salon chat thread, attendance and sanctions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course_lesson ADD vimeo_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE inbox_thread ADD planning_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_INBOX_THREAD_PLANNING ON inbox_thread (planning_id)');
        $this->addSql('ALTER TABLE inbox_thread ADD CONSTRAINT FK_INBOX_THREAD_PLANNING FOREIGN KEY (planning_id) REFERENCES planning (id) ON DELETE CASCADE');
        $this->addSql('CREATE TABLE planning_attendance (id INT AUTO_INCREMENT NOT NULL, planning_id INT NOT NULL, student_id INT NOT NULL, reported_by_id INT NOT NULL, status VARCHAR(20) NOT NULL, sanction VARCHAR(32) DEFAULT \'none\' NOT NULL, note LONGTEXT DEFAULT NULL, reported_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', sanctioned_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', hours_deducted DOUBLE PRECISION DEFAULT \'0\' NOT NULL, INDEX IDX_PA_PLANNING (planning_id), INDEX IDX_PA_STUDENT (student_id), INDEX IDX_PA_REPORTER (reported_by_id), UNIQUE INDEX uniq_planning_attendance_student (planning_id, student_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE planning_attendance ADD CONSTRAINT FK_PA_PLANNING FOREIGN KEY (planning_id) REFERENCES planning (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE planning_attendance ADD CONSTRAINT FK_PA_STUDENT FOREIGN KEY (student_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE planning_attendance ADD CONSTRAINT FK_PA_REPORTER FOREIGN KEY (reported_by_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planning_attendance DROP FOREIGN KEY FK_PA_PLANNING');
        $this->addSql('ALTER TABLE planning_attendance DROP FOREIGN KEY FK_PA_STUDENT');
        $this->addSql('ALTER TABLE planning_attendance DROP FOREIGN KEY FK_PA_REPORTER');
        $this->addSql('DROP TABLE planning_attendance');
        $this->addSql('ALTER TABLE inbox_thread DROP FOREIGN KEY FK_INBOX_THREAD_PLANNING');
        $this->addSql('DROP INDEX UNIQ_INBOX_THREAD_PLANNING ON inbox_thread');
        $this->addSql('ALTER TABLE inbox_thread DROP planning_id');
        $this->addSql('ALTER TABLE course_lesson DROP vimeo_url');
    }
}
