<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260317225623 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article ADD meta JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE article_submission ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE article_submission ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN article_submission.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN article_submission.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX uq_article_submission_article_platform RENAME TO UNIQ_E7DC8B727294869C3952D0CB');
        $this->addSql('DROP INDEX idx_image_position');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE INDEX idx_image_position ON image (article_id, "position")');
        $this->addSql('ALTER TABLE article_submission ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE article_submission ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN article_submission.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN article_submission.updated_at IS NULL');
        $this->addSql('ALTER INDEX uniq_e7dc8b727294869c3952d0cb RENAME TO uq_article_submission_article_platform');
        $this->addSql('ALTER TABLE article DROP meta');
    }
}
