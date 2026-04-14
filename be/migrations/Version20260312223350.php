<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260312223350 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE image_batch (id SERIAL NOT NULL, user_id INT NOT NULL, job_id VARCHAR(64) NOT NULL, image_paths JSON NOT NULL, status VARCHAR(32) DEFAULT \'pending\' NOT NULL, article_ids JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EF4ED67DBE04EA9 ON image_batch (job_id)');
        $this->addSql('CREATE INDEX IDX_EF4ED67DA76ED395 ON image_batch (user_id)');
        $this->addSql('COMMENT ON COLUMN image_batch.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE image_batch ADD CONSTRAINT FK_EF4ED67DA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE article ALTER platform TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE article ALTER last_bumped_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN article.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN article.last_bumped_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE image_batch DROP CONSTRAINT FK_EF4ED67DA76ED395');
        $this->addSql('DROP TABLE image_batch');
        $this->addSql('ALTER TABLE article ALTER platform TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE article ALTER last_bumped_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE article ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN article.last_bumped_at IS NULL');
        $this->addSql('COMMENT ON COLUMN article.created_at IS NULL');
    }
}
