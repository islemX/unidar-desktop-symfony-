<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * UnidarAuditTrailBundle — creates the audit_logs table.
 */
final class Version20260429001_AuditLogs extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'UnidarAuditTrailBundle: create audit_logs table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE IF NOT EXISTS audit_logs (
                id            INT AUTO_INCREMENT NOT NULL,
                entity_class  VARCHAR(128) NOT NULL,
                entity_id     INT NOT NULL,
                action        VARCHAR(16) NOT NULL,
                changed_fields LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)',
                user_email    VARCHAR(255) DEFAULT NULL,
                ip_address    VARCHAR(64) DEFAULT NULL,
                created_at    DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id),
                INDEX idx_audit_entity (entity_class, entity_id),
                INDEX idx_audit_date   (created_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS audit_logs');
    }
}
