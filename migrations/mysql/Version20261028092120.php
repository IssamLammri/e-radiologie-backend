<?php

declare(strict_types=1);

namespace DoctrineMigrations\MySQL;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028092120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les modules éditoriaux V1, les comptes rendus privés et reprend les prototypes existants.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE course (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, image_path VARCHAR(2048) NOT NULL, author_name VARCHAR(255) NOT NULL, institution VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, specialty_id INT NOT NULL, INDEX IDX_169E6FB99A353316 (specialty_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE course_block (id INT AUTO_INCREMENT NOT NULL, content_html LONGTEXT NOT NULL, image_path VARCHAR(2048) DEFAULT NULL, layout VARCHAR(24) NOT NULL, position INT NOT NULL, section_id INT NOT NULL, INDEX IDX_6AB090CAD823E37A (section_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE course_section (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, position INT NOT NULL, course_id INT NOT NULL, parent_id INT DEFAULT NULL, INDEX IDX_25B07F03591CC992 (course_id), INDEX IDX_25B07F03727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE dems_exercise (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, masked_name VARCHAR(255) NOT NULL, image_path VARCHAR(2048) NOT NULL, clinical_presentation LONGTEXT NOT NULL, identification LONGTEXT NOT NULL, analytic_study LONGTEXT NOT NULL, synthetic_study LONGTEXT NOT NULL, differential_diagnosis LONGTEXT NOT NULL, management LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE evaluation_rule (id INT AUTO_INCREMENT NOT NULL, grade VARCHAR(255) NOT NULL, semiology LONGTEXT NOT NULL, clinical_description LONGTEXT NOT NULL, position INT NOT NULL, classification_id INT NOT NULL, INDEX IDX_B9EE3742A86559F (classification_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE medical_classification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(120) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, indications LONGTEXT NOT NULL, calculator_key VARCHAR(120) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, specialty_id INT NOT NULL, INDEX IDX_BB279E49A353316 (specialty_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE report_exam (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, modality_id INT NOT NULL, INDEX IDX_4B974B3D2D6D889B (modality_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE report_template (id INT AUTO_INCREMENT NOT NULL, category VARCHAR(16) NOT NULL, label VARCHAR(255) NOT NULL, content_html LONGTEXT NOT NULL, conclusion_html LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, exam_id INT NOT NULL, INDEX IDX_970086FE578D5E91 (exam_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE saved_report (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content_html LONGTEXT NOT NULL, conclusion_html LONGTEXT NOT NULL, personalization JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, owner_id INT NOT NULL, INDEX IDX_A0290C9F7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE specialty (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, position INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB99A353316 FOREIGN KEY (specialty_id) REFERENCES specialty (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE course_block ADD CONSTRAINT FK_6AB090CAD823E37A FOREIGN KEY (section_id) REFERENCES course_section (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE course_section ADD CONSTRAINT FK_25B07F03591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE course_section ADD CONSTRAINT FK_25B07F03727ACA70 FOREIGN KEY (parent_id) REFERENCES course_section (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evaluation_rule ADD CONSTRAINT FK_B9EE3742A86559F FOREIGN KEY (classification_id) REFERENCES medical_classification (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE medical_classification ADD CONSTRAINT FK_BB279E49A353316 FOREIGN KEY (specialty_id) REFERENCES specialty (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE report_exam ADD CONSTRAINT FK_4B974B3D2D6D889B FOREIGN KEY (modality_id) REFERENCES imaging_modality (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE report_template ADD CONSTRAINT FK_970086FE578D5E91 FOREIGN KEY (exam_id) REFERENCES report_exam (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE saved_report ADD CONSTRAINT FK_A0290C9F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE CASCADE');

        // Snapshot of the existing prototypes: no new medical content is introduced.
        $legacy = json_decode(file_get_contents(__DIR__.'/../../resources/legacy-editorial-content.json'), true, flags: JSON_THROW_ON_ERROR);
        $classificationId = 0;
        foreach (array_values(array_keys($legacy['classifications'])) as $position => $name) {
            $specialtyId = $position + 1;
            $this->addSql('INSERT INTO specialty (id, name, position, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())', [$specialtyId, $name, $position]);
            foreach ($legacy['classifications'][$name] as $item) {
                ++$classificationId;
                $this->addSql('INSERT INTO medical_classification (id, specialty_id, name, type, description, indications, calculator_key, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [$classificationId, $specialtyId, $item['name'], $item['acronym'] ?? $item['name'], $item['description'], $item['indications'], isset($item['calculator']) ? $item['id'] : null]);
                foreach ($item['criteria'] as $order => $rule) {
                    $this->addSql('INSERT INTO evaluation_rule (classification_id, grade, semiology, clinical_description, position) VALUES (?, ?, ?, ?, ?)', [$classificationId, $rule['grade'], $rule['label'], $rule['description'], $order]);
                }
            }
        }
        $modalities = ['Radiographie standard' => 'RADIOGRAPHY', 'Échographie' => 'ULTRASOUND', 'Écho-Doppler' => 'DOPPLER', 'TDM' => 'CT', 'IRM' => 'MRI'];
        foreach ($modalities as $name => $code) {
            $slug = strtolower($code);
            $this->addSql('INSERT INTO imaging_modality (name, slug, code, active, position, created_at, updated_at) SELECT ?, ?, ?, 1, 50, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM imaging_modality WHERE code = ?)', [$name, $slug, $code, $code]);
        }
        $escape = static fn (string $text): string => nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        foreach ($legacy['reports'] as $index => $item) {
            $examId = $index + 1;
            $this->addSql('INSERT INTO report_exam (id, name, modality_id, created_at, updated_at) SELECT ?, ?, id, NOW(), NOW() FROM imaging_modality WHERE code = ?', [$examId, $item['title'], $modalities[$item['modality']]]);
            foreach (['normalVersion' => 'NORMAL', 'pathologicalVersion' => 'PATHOLOGICAL'] as $key => $category) {
                if (!isset($item[$key])) { continue; }
                $version = $item[$key];
                $html = '<h3>Technique</h3><p>'.$escape($version['technique']).'</p><h3>Résultats et observations</h3><p>'.$escape($version['description']).'</p>';
                $this->addSql('INSERT INTO report_template (exam_id, category, label, content_html, conclusion_html, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())', [$examId, $category, $version['pathologyName'] ?? 'Normal', $html, '<p>'.$escape($version['conclusion']).'</p>']);
            }
        }

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course DROP FOREIGN KEY FK_169E6FB99A353316');
        $this->addSql('ALTER TABLE course_block DROP FOREIGN KEY FK_6AB090CAD823E37A');
        $this->addSql('ALTER TABLE course_section DROP FOREIGN KEY FK_25B07F03591CC992');
        $this->addSql('ALTER TABLE course_section DROP FOREIGN KEY FK_25B07F03727ACA70');
        $this->addSql('ALTER TABLE evaluation_rule DROP FOREIGN KEY FK_B9EE3742A86559F');
        $this->addSql('ALTER TABLE medical_classification DROP FOREIGN KEY FK_BB279E49A353316');
        $this->addSql('ALTER TABLE report_exam DROP FOREIGN KEY FK_4B974B3D2D6D889B');
        $this->addSql('ALTER TABLE report_template DROP FOREIGN KEY FK_970086FE578D5E91');
        $this->addSql('ALTER TABLE saved_report DROP FOREIGN KEY FK_A0290C9F7E3C61F9');
        $this->addSql('DROP TABLE course');
        $this->addSql('DROP TABLE course_block');
        $this->addSql('DROP TABLE course_section');
        $this->addSql('DROP TABLE dems_exercise');
        $this->addSql('DROP TABLE evaluation_rule');
        $this->addSql('DROP TABLE medical_classification');
        $this->addSql('DROP TABLE report_exam');
        $this->addSql('DROP TABLE report_template');
        $this->addSql('DROP TABLE saved_report');
        $this->addSql('DROP TABLE specialty');
    }
}
