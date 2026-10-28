<?php

declare(strict_types=1);

namespace DoctrineMigrations\MySQL;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initialise le schéma MySQL et les référentiels de radiologie.';
    }

    public function isTransactional(): bool
    {
        return false; // MySQL DDL commits implicitly.
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Cette migration nécessite MySQL.');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON `user` (email)');
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX IDX_7CE748AA76ED395 ON reset_password_request (user_id)');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('CREATE TABLE imaging_modality (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL, code VARCHAR(50) NOT NULL, active BOOLEAN DEFAULT true NOT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A831601A989D9B62 ON imaging_modality (slug)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A831601A77153098 ON imaging_modality (code)');

        $this->addSql('CREATE TABLE case_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(160) NOT NULL, slug VARCHAR(180) NOT NULL, description LONGTEXT DEFAULT NULL, active BOOLEAN DEFAULT true NOT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_913163BC989D9B62 ON case_category (slug)');

        $this->addSql('CREATE TABLE radiology_case (id INT AUTO_INCREMENT NOT NULL, modality_id INT NOT NULL, category_id INT NOT NULL, author_id INT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, difficulty VARCHAR(24) NOT NULL, patient_gender VARCHAR(24) NOT NULL, patient_age INT DEFAULT NULL, clinical_context LONGTEXT NOT NULL, training_instruction LONGTEXT DEFAULT NULL, training_placeholder LONGTEXT DEFAULT NULL, expert_description LONGTEXT DEFAULT NULL, diagnosis LONGTEXT DEFAULT NULL, global_discussion LONGTEXT DEFAULT NULL, status VARCHAR(28) NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1B515D04989D9B62 ON radiology_case (slug)');
        $this->addSql('CREATE INDEX idx_radiology_case_status ON radiology_case (status)');
        $this->addSql('CREATE INDEX idx_radiology_case_published_at ON radiology_case (published_at)');
        $this->addSql('CREATE INDEX IDX_1B515D042D6D889B ON radiology_case (modality_id)');
        $this->addSql('CREATE INDEX IDX_1B515D0412469DE2 ON radiology_case (category_id)');
        $this->addSql('CREATE INDEX IDX_1B515D04F675F31B ON radiology_case (author_id)');

        $this->addSql('CREATE TABLE case_media (id INT AUTO_INCREMENT NOT NULL, radiology_case_id INT NOT NULL, path VARCHAR(2048) NOT NULL, media_type VARCHAR(80) DEFAULT \'IMAGE\' NOT NULL, title VARCHAR(255) DEFAULT NULL, caption LONGTEXT DEFAULT NULL, alt_text VARCHAR(500) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, is_primary BOOLEAN DEFAULT false NOT NULL, primary_case_id INT GENERATED ALWAYS AS (CASE WHEN is_primary THEN radiology_case_id ELSE NULL END) VIRTUAL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX IDX_38C55BE072312F8C ON case_media (radiology_case_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_case_primary_media ON case_media (primary_case_id)');

        $this->addSql('CREATE TABLE case_reference (id INT AUTO_INCREMENT NOT NULL, radiology_case_id INT NOT NULL, title VARCHAR(500) NOT NULL, authors VARCHAR(500) DEFAULT NULL, source VARCHAR(255) DEFAULT NULL, url VARCHAR(2048) DEFAULT NULL, doi VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX IDX_808039E872312F8C ON case_reference (radiology_case_id)');

        $this->addSql('ALTER TABLE radiology_case ADD CONSTRAINT FK_RADIOLOGY_CASE_MODALITY FOREIGN KEY (modality_id) REFERENCES imaging_modality (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE radiology_case ADD CONSTRAINT FK_RADIOLOGY_CASE_CATEGORY FOREIGN KEY (category_id) REFERENCES case_category (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE radiology_case ADD CONSTRAINT FK_RADIOLOGY_CASE_AUTHOR FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE case_media ADD CONSTRAINT FK_CASE_MEDIA_CASE FOREIGN KEY (radiology_case_id) REFERENCES radiology_case (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE case_reference ADD CONSTRAINT FK_CASE_REFERENCE_CASE FOREIGN KEY (radiology_case_id) REFERENCES radiology_case (id) ON DELETE CASCADE');

        $this->addSql("INSERT INTO imaging_modality (name, slug, code, active, position, created_at, updated_at) VALUES ('Radiographie', 'radiographie', 'RADIOGRAPHY', true, 10, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP), ('IRM', 'irm', 'MRI', true, 20, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP), ('TDM', 'tdm', 'CT', true, 30, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP), ('Échographie', 'echographie', 'ULTRASOUND', true, 40, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $this->addSql("INSERT INTO case_category (name, slug, active, position, created_at, updated_at) VALUES ('Imagerie thoracique', 'imagerie-thoracique', true, 10, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP), ('Neuroradiologie', 'neuroradiologie', true, 20, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP), ('Imagerie abdominale et digestive', 'imagerie-abdominale-et-digestive', true, 30, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
    }

    public function down(Schema $schema): void
    {
        foreach (['case_reference', 'case_media', 'radiology_case', 'case_category', 'imaging_modality', 'reset_password_request', '`user`'] as $table) {
            $this->addSql('DROP TABLE '.$table);
        }
    }
}
