<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * UnidarMediaPipelineBundle + UnidarContractTemplateBundle entity schema updates.
 */
final class Version20260429002_BundleColumns extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'MediaPipeline: add srcset to listing_images | ContractTemplate: add locale, version, is_default columns';
    }

    public function up(Schema $schema): void
    {
        // ListingImage — WebP srcset (nullable JSON)
        $this->addSql(
            'ALTER TABLE listing_images ADD COLUMN IF NOT EXISTS srcset LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\''
        );

        // ContractTemplate — locale, version, is_default
        $this->addSql(
            'ALTER TABLE contract_templates
             ADD COLUMN IF NOT EXISTS locale      VARCHAR(5)   NOT NULL DEFAULT \'fr\',
             ADD COLUMN IF NOT EXISTS version     INT          NOT NULL DEFAULT 1,
             ADD COLUMN IF NOT EXISTS is_default  TINYINT(1)   NOT NULL DEFAULT 0'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE listing_images DROP COLUMN IF EXISTS srcset');
        $this->addSql('ALTER TABLE contract_templates DROP COLUMN IF EXISTS locale, DROP COLUMN IF EXISTS version, DROP COLUMN IF EXISTS is_default');
    }
}
