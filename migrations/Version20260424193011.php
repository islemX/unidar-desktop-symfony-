<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260424193011 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_actions (id INT AUTO_INCREMENT NOT NULL, action_type VARCHAR(255) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, admin_id INT NOT NULL, target_user_id INT DEFAULT NULL, target_listing_id INT DEFAULT NULL, target_report_id INT DEFAULT NULL, INDEX IDX_C1747366642B8210 (admin_id), INDEX IDX_C17473666C066AFE (target_user_id), INDEX IDX_C1747366E68D458B (target_listing_id), INDEX IDX_C17473667F66D6EC (target_report_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE blocked_users (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, blocker_id INT NOT NULL, blocked_id INT NOT NULL, INDEX IDX_A3C2E415548D5975 (blocker_id), INDEX IDX_A3C2E41521FF5136 (blocked_id), UNIQUE INDEX UNIQ_A3C2E415548D597521FF5136 (blocker_id, blocked_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contract_templates (id INT AUTO_INCREMENT NOT NULL, template_name VARCHAR(255) NOT NULL, template_type VARCHAR(100) NOT NULL, contract_content LONGTEXT NOT NULL, legal_clauses LONGTEXT NOT NULL, tunisia_specific_clauses LONGTEXT NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contract_termination_requests (id INT AUTO_INCREMENT NOT NULL, reason LONGTEXT NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, created_at DATETIME NOT NULL, contract_id INT NOT NULL, requested_by_id INT NOT NULL, INDEX IDX_BA0B3DF2576E0FD (contract_id), INDEX IDX_BA0B3DF4DA1E751 (requested_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contracts (id INT AUTO_INCREMENT NOT NULL, contract_number VARCHAR(100) NOT NULL, contract_content LONGTEXT DEFAULT NULL, student_signature_path VARCHAR(500) DEFAULT NULL, owner_signature_path VARCHAR(500) DEFAULT NULL, contract_file_path VARCHAR(500) DEFAULT NULL, status VARCHAR(255) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, monthly_rent NUMERIC(10, 2) NOT NULL, security_deposit NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, listing_id INT NOT NULL, student_id INT NOT NULL, owner_id INT NOT NULL, template_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_950A973AAD0FA19 (contract_number), INDEX IDX_950A973D4619D1A (listing_id), INDEX IDX_950A973CB944F1A (student_id), INDEX IDX_950A9737E3C61F9 (owner_id), INDEX IDX_950A9735DA0FB8 (template_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE conversations (id INT AUTO_INCREMENT NOT NULL, is_archived_user1 TINYINT DEFAULT 0 NOT NULL, is_archived_user2 TINYINT DEFAULT 0 NOT NULL, is_deleted_user1 TINYINT DEFAULT 0 NOT NULL, is_deleted_user2 TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, listing_id INT DEFAULT NULL, user1_id INT NOT NULL, user2_id INT NOT NULL, INDEX IDX_C2521BF1D4619D1A (listing_id), INDEX IDX_C2521BF156AE248B (user1_id), INDEX IDX_C2521BF1441B8B65 (user2_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE faculties (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, city VARCHAR(100) NOT NULL, governorate VARCHAR(100) NOT NULL, address VARCHAR(500) DEFAULT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE listing_images (id INT AUTO_INCREMENT NOT NULL, image_path VARCHAR(500) NOT NULL, display_order INT DEFAULT 0 NOT NULL, listing_id INT NOT NULL, INDEX IDX_4E79FB9D4619D1A (listing_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE listings (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, address VARCHAR(500) NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, price NUMERIC(10, 2) NOT NULL, bedrooms INT NOT NULL, beds_count INT NOT NULL, capacity INT NOT NULL, bathrooms INT NOT NULL, property_type VARCHAR(255) NOT NULL, gender_preference VARCHAR(20) DEFAULT NULL, available_from DATE DEFAULT NULL, available_until DATE DEFAULT NULL, owner_signature_path VARCHAR(500) DEFAULT NULL, status VARCHAR(20) DEFAULT \'active\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, owner_id INT NOT NULL, INDEX IDX_9A7BD98E7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messages (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT NOT NULL, is_read TINYINT DEFAULT 0 NOT NULL, read_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, conversation_id INT NOT NULL, sender_id INT NOT NULL, receiver_id INT NOT NULL, INDEX IDX_DB021E969AC0396 (conversation_id), INDEX IDX_DB021E96F624B39D (sender_id), INDEX IDX_DB021E96CD53EDB6 (receiver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payment_transactions (id INT AUTO_INCREMENT NOT NULL, transaction_data JSON NOT NULL, created_at DATETIME NOT NULL, payment_id INT NOT NULL, INDEX IDX_8C58AD564C3A3BB (payment_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payments (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(10, 2) NOT NULL, owner_amount NUMERIC(10, 2) NOT NULL, platform_fee NUMERIC(10, 2) NOT NULL, status VARCHAR(255) NOT NULL, payment_method VARCHAR(50) NOT NULL, transaction_id VARCHAR(255) DEFAULT NULL, payment_type VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, contract_id INT NOT NULL, INDEX IDX_65D29B322576E0FD (contract_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE platform_commissions (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(10, 2) NOT NULL, calculated_on NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, payment_id INT NOT NULL, contract_id INT NOT NULL, UNIQUE INDEX UNIQ_3105756B4C3A3BB (payment_id), INDEX IDX_3105756B2576E0FD (contract_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reports (id INT AUTO_INCREMENT NOT NULL, report_type VARCHAR(50) NOT NULL, reason VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, priority VARCHAR(20) DEFAULT \'medium\' NOT NULL, status VARCHAR(255) NOT NULL, resolution_notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, reporter_id INT NOT NULL, reported_user_id INT DEFAULT NULL, reported_listing_id INT DEFAULT NULL, resolved_by_id INT DEFAULT NULL, INDEX IDX_F11FA745E1CFE6F5 (reporter_id), INDEX IDX_F11FA745E7566E (reported_user_id), INDEX IDX_F11FA745BF605606 (reported_listing_id), INDEX IDX_F11FA7456713A32B (resolved_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE roommate_preferences (id INT AUTO_INCREMENT NOT NULL, budget_min NUMERIC(10, 2) DEFAULT NULL, budget_max NUMERIC(10, 2) DEFAULT NULL, cleanliness_level INT DEFAULT NULL, smoking_preference VARCHAR(50) DEFAULT NULL, noise_tolerance VARCHAR(50) DEFAULT NULL, sleep_schedule VARCHAR(50) DEFAULT NULL, gender_preference VARCHAR(20) DEFAULT NULL, age_min INT DEFAULT NULL, age_max INT DEFAULT NULL, guests VARCHAR(50) DEFAULT NULL, pets VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_B5ADF44DA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE saved_listings (id INT AUTO_INCREMENT NOT NULL, saved_at DATETIME NOT NULL, user_id INT NOT NULL, listing_id INT NOT NULL, INDEX IDX_F49ADEA6A76ED395 (user_id), INDEX IDX_F49ADEA6D4619D1A (listing_id), UNIQUE INDEX UNIQ_F49ADEA6A76ED395D4619D1A (user_id, listing_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE subscriptions (id INT AUTO_INCREMENT NOT NULL, plan VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) NOT NULL, status VARCHAR(20) DEFAULT \'active\' NOT NULL, starts_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, payment_method VARCHAR(50) NOT NULL, card_last4 VARCHAR(4) DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_4778A01A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, full_name VARCHAR(255) NOT NULL, role VARCHAR(255) NOT NULL, university VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, gender VARCHAR(20) DEFAULT NULL, preferred_lat DOUBLE PRECISION DEFAULT NULL, preferred_lng DOUBLE PRECISION DEFAULT NULL, preferred_address VARCHAR(500) DEFAULT NULL, university_address VARCHAR(500) DEFAULT NULL, status VARCHAR(255) NOT NULL, is_email_verified TINYINT DEFAULT 0 NOT NULL, email_verification_token VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), UNIQUE INDEX UNIQ_1483A5E9C4995C67 (email_verification_token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE verifications (id INT AUTO_INCREMENT NOT NULL, student_id_file VARCHAR(500) DEFAULT NULL, national_id_file VARCHAR(500) DEFAULT NULL, status VARCHAR(255) NOT NULL, submitted_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, rejection_reason LONGTEXT DEFAULT NULL, user_id INT NOT NULL, reviewed_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_8C86E746A76ED395 (user_id), INDEX IDX_8C86E746FC6B21F1 (reviewed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE admin_actions ADD CONSTRAINT FK_C1747366642B8210 FOREIGN KEY (admin_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE admin_actions ADD CONSTRAINT FK_C17473666C066AFE FOREIGN KEY (target_user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE admin_actions ADD CONSTRAINT FK_C1747366E68D458B FOREIGN KEY (target_listing_id) REFERENCES listings (id)');
        $this->addSql('ALTER TABLE admin_actions ADD CONSTRAINT FK_C17473667F66D6EC FOREIGN KEY (target_report_id) REFERENCES reports (id)');
        $this->addSql('ALTER TABLE blocked_users ADD CONSTRAINT FK_A3C2E415548D5975 FOREIGN KEY (blocker_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE blocked_users ADD CONSTRAINT FK_A3C2E41521FF5136 FOREIGN KEY (blocked_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE contract_termination_requests ADD CONSTRAINT FK_BA0B3DF2576E0FD FOREIGN KEY (contract_id) REFERENCES contracts (id)');
        $this->addSql('ALTER TABLE contract_termination_requests ADD CONSTRAINT FK_BA0B3DF4DA1E751 FOREIGN KEY (requested_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE contracts ADD CONSTRAINT FK_950A973D4619D1A FOREIGN KEY (listing_id) REFERENCES listings (id)');
        $this->addSql('ALTER TABLE contracts ADD CONSTRAINT FK_950A973CB944F1A FOREIGN KEY (student_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE contracts ADD CONSTRAINT FK_950A9737E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE contracts ADD CONSTRAINT FK_950A9735DA0FB8 FOREIGN KEY (template_id) REFERENCES contract_templates (id)');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT FK_C2521BF1D4619D1A FOREIGN KEY (listing_id) REFERENCES listings (id)');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT FK_C2521BF156AE248B FOREIGN KEY (user1_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT FK_C2521BF1441B8B65 FOREIGN KEY (user2_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE listing_images ADD CONSTRAINT FK_4E79FB9D4619D1A FOREIGN KEY (listing_id) REFERENCES listings (id)');
        $this->addSql('ALTER TABLE listings ADD CONSTRAINT FK_9A7BD98E7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E969AC0396 FOREIGN KEY (conversation_id) REFERENCES conversations (id)');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96F624B39D FOREIGN KEY (sender_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96CD53EDB6 FOREIGN KEY (receiver_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE payment_transactions ADD CONSTRAINT FK_8C58AD564C3A3BB FOREIGN KEY (payment_id) REFERENCES payments (id)');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B322576E0FD FOREIGN KEY (contract_id) REFERENCES contracts (id)');
        $this->addSql('ALTER TABLE platform_commissions ADD CONSTRAINT FK_3105756B4C3A3BB FOREIGN KEY (payment_id) REFERENCES payments (id)');
        $this->addSql('ALTER TABLE platform_commissions ADD CONSTRAINT FK_3105756B2576E0FD FOREIGN KEY (contract_id) REFERENCES contracts (id)');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_F11FA745E1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_F11FA745E7566E FOREIGN KEY (reported_user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_F11FA745BF605606 FOREIGN KEY (reported_listing_id) REFERENCES listings (id)');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_F11FA7456713A32B FOREIGN KEY (resolved_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE roommate_preferences ADD CONSTRAINT FK_B5ADF44DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE saved_listings ADD CONSTRAINT FK_F49ADEA6A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE saved_listings ADD CONSTRAINT FK_F49ADEA6D4619D1A FOREIGN KEY (listing_id) REFERENCES listings (id)');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_4778A01A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE verifications ADD CONSTRAINT FK_8C86E746A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE verifications ADD CONSTRAINT FK_8C86E746FC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_actions DROP FOREIGN KEY FK_C1747366642B8210');
        $this->addSql('ALTER TABLE admin_actions DROP FOREIGN KEY FK_C17473666C066AFE');
        $this->addSql('ALTER TABLE admin_actions DROP FOREIGN KEY FK_C1747366E68D458B');
        $this->addSql('ALTER TABLE admin_actions DROP FOREIGN KEY FK_C17473667F66D6EC');
        $this->addSql('ALTER TABLE blocked_users DROP FOREIGN KEY FK_A3C2E415548D5975');
        $this->addSql('ALTER TABLE blocked_users DROP FOREIGN KEY FK_A3C2E41521FF5136');
        $this->addSql('ALTER TABLE contract_termination_requests DROP FOREIGN KEY FK_BA0B3DF2576E0FD');
        $this->addSql('ALTER TABLE contract_termination_requests DROP FOREIGN KEY FK_BA0B3DF4DA1E751');
        $this->addSql('ALTER TABLE contracts DROP FOREIGN KEY FK_950A973D4619D1A');
        $this->addSql('ALTER TABLE contracts DROP FOREIGN KEY FK_950A973CB944F1A');
        $this->addSql('ALTER TABLE contracts DROP FOREIGN KEY FK_950A9737E3C61F9');
        $this->addSql('ALTER TABLE contracts DROP FOREIGN KEY FK_950A9735DA0FB8');
        $this->addSql('ALTER TABLE conversations DROP FOREIGN KEY FK_C2521BF1D4619D1A');
        $this->addSql('ALTER TABLE conversations DROP FOREIGN KEY FK_C2521BF156AE248B');
        $this->addSql('ALTER TABLE conversations DROP FOREIGN KEY FK_C2521BF1441B8B65');
        $this->addSql('ALTER TABLE listing_images DROP FOREIGN KEY FK_4E79FB9D4619D1A');
        $this->addSql('ALTER TABLE listings DROP FOREIGN KEY FK_9A7BD98E7E3C61F9');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E969AC0396');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E96F624B39D');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E96CD53EDB6');
        $this->addSql('ALTER TABLE payment_transactions DROP FOREIGN KEY FK_8C58AD564C3A3BB');
        $this->addSql('ALTER TABLE payments DROP FOREIGN KEY FK_65D29B322576E0FD');
        $this->addSql('ALTER TABLE platform_commissions DROP FOREIGN KEY FK_3105756B4C3A3BB');
        $this->addSql('ALTER TABLE platform_commissions DROP FOREIGN KEY FK_3105756B2576E0FD');
        $this->addSql('ALTER TABLE reports DROP FOREIGN KEY FK_F11FA745E1CFE6F5');
        $this->addSql('ALTER TABLE reports DROP FOREIGN KEY FK_F11FA745E7566E');
        $this->addSql('ALTER TABLE reports DROP FOREIGN KEY FK_F11FA745BF605606');
        $this->addSql('ALTER TABLE reports DROP FOREIGN KEY FK_F11FA7456713A32B');
        $this->addSql('ALTER TABLE roommate_preferences DROP FOREIGN KEY FK_B5ADF44DA76ED395');
        $this->addSql('ALTER TABLE saved_listings DROP FOREIGN KEY FK_F49ADEA6A76ED395');
        $this->addSql('ALTER TABLE saved_listings DROP FOREIGN KEY FK_F49ADEA6D4619D1A');
        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY FK_4778A01A76ED395');
        $this->addSql('ALTER TABLE verifications DROP FOREIGN KEY FK_8C86E746A76ED395');
        $this->addSql('ALTER TABLE verifications DROP FOREIGN KEY FK_8C86E746FC6B21F1');
        $this->addSql('DROP TABLE admin_actions');
        $this->addSql('DROP TABLE blocked_users');
        $this->addSql('DROP TABLE contract_templates');
        $this->addSql('DROP TABLE contract_termination_requests');
        $this->addSql('DROP TABLE contracts');
        $this->addSql('DROP TABLE conversations');
        $this->addSql('DROP TABLE faculties');
        $this->addSql('DROP TABLE listing_images');
        $this->addSql('DROP TABLE listings');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE payment_transactions');
        $this->addSql('DROP TABLE payments');
        $this->addSql('DROP TABLE platform_commissions');
        $this->addSql('DROP TABLE reports');
        $this->addSql('DROP TABLE roommate_preferences');
        $this->addSql('DROP TABLE saved_listings');
        $this->addSql('DROP TABLE subscriptions');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE verifications');
    }
}
