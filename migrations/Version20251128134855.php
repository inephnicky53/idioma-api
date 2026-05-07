<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251128134855 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE course (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, title_en VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, description_en LONGTEXT DEFAULT NULL, price NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) NOT NULL, thumbnail VARCHAR(255) DEFAULT NULL, ebook_path VARCHAR(255) DEFAULT NULL, ebook_title VARCHAR(255) DEFAULT NULL, is_published TINYINT(1) NOT NULL, position INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE course_purchase (id INT AUTO_INCREMENT NOT NULL, purchased_at DATETIME NOT NULL, is_active TINYINT(1) NOT NULL, user_id INT NOT NULL, course_id INT NOT NULL, payment_id INT NOT NULL, INDEX IDX_C8A3BC6DA76ED395 (user_id), INDEX IDX_C8A3BC6D591CC992 (course_id), INDEX IDX_C8A3BC6D4C3A3BB (payment_id), UNIQUE INDEX unique_user_course (user_id, course_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE course_video (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, title_en VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, video_url VARCHAR(500) NOT NULL, duration INT DEFAULT NULL, position INT NOT NULL, thumbnail VARCHAR(255) DEFAULT NULL, is_free_preview TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, course_id INT NOT NULL, INDEX IDX_956CDDC4591CC992 (course_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE course_purchase ADD CONSTRAINT FK_C8A3BC6DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE course_purchase ADD CONSTRAINT FK_C8A3BC6D591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
        $this->addSql('ALTER TABLE course_purchase ADD CONSTRAINT FK_C8A3BC6D4C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id)');
        $this->addSql('ALTER TABLE course_video ADD CONSTRAINT FK_956CDDC4591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
        $this->addSql('ALTER TABLE payment ADD purchase_type VARCHAR(20) NOT NULL, ADD provider VARCHAR(20) DEFAULT NULL, ADD course_id INT DEFAULT NULL, DROP is_callbacked, CHANGE subscription_plan_id subscription_plan_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
        $this->addSql('CREATE INDEX IDX_6D28840D591CC992 ON payment (course_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course_purchase DROP FOREIGN KEY FK_C8A3BC6DA76ED395');
        $this->addSql('ALTER TABLE course_purchase DROP FOREIGN KEY FK_C8A3BC6D591CC992');
        $this->addSql('ALTER TABLE course_purchase DROP FOREIGN KEY FK_C8A3BC6D4C3A3BB');
        $this->addSql('ALTER TABLE course_video DROP FOREIGN KEY FK_956CDDC4591CC992');
        $this->addSql('DROP TABLE course');
        $this->addSql('DROP TABLE course_purchase');
        $this->addSql('DROP TABLE course_video');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D591CC992');
        $this->addSql('DROP INDEX IDX_6D28840D591CC992 ON payment');
        $this->addSql('ALTER TABLE payment ADD is_callbacked TINYINT(1) DEFAULT 0 NOT NULL, DROP purchase_type, DROP provider, DROP course_id, CHANGE subscription_plan_id subscription_plan_id INT NOT NULL');
    }
}
