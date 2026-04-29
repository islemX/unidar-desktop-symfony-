<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260428174144 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE listings ADD floor INT DEFAULT NULL, ADD area DOUBLE PRECISION DEFAULT NULL, ADD amenities JSON DEFAULT NULL, ADD views_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE users ADD field_of_study VARCHAR(255) DEFAULT NULL, ADD year_of_study INT DEFAULT NULL, ADD profile_photo VARCHAR(500) DEFAULT NULL, ADD last_login_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE listings DROP floor, DROP area, DROP amenities, DROP views_count');
        $this->addSql('ALTER TABLE users DROP field_of_study, DROP year_of_study, DROP profile_photo, DROP last_login_at');
    }
}
