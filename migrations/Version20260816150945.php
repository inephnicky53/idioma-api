<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260816150945 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_product ADD course_id INT DEFAULT NULL, CHANGE teacher_id teacher_id INT DEFAULT NULL, CHANGE package_id package_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE order_product ADD CONSTRAINT FK_2530ADE6591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
        $this->addSql('CREATE INDEX IDX_2530ADE6591CC992 ON order_product (course_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_product DROP FOREIGN KEY FK_2530ADE6591CC992');
        $this->addSql('DROP INDEX IDX_2530ADE6591CC992 ON order_product');
        $this->addSql('ALTER TABLE order_product DROP course_id, CHANGE teacher_id teacher_id INT NOT NULL, CHANGE package_id package_id INT NOT NULL');
    }
}
