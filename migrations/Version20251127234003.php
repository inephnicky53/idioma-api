<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour ajouter la table Rate (taux de change)
 */
final class Version20251127234003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table rate pour la gestion des taux de change';
    }

    public function up(Schema $schema): void
    {
        // Créer la table rate avec un index (pas de contrainte unique pour garder l'historique)
        $this->addSql('CREATE TABLE rate (id INT AUTO_INCREMENT NOT NULL, from_currency VARCHAR(3) NOT NULL, to_currency VARCHAR(3) NOT NULL, rate NUMERIC(15, 6) NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX idx_currency_pair (from_currency, to_currency, is_active), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE payment ADD currency VARCHAR(3) DEFAULT NULL, ADD is_sms_send TINYINT(1) NOT NULL, ADD responsed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE rate');
        $this->addSql('ALTER TABLE payment DROP currency, DROP is_sms_send, DROP responsed_at');
    }
}
