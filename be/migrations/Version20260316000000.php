<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create article_submission table for per-platform submission tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE article_submission (
            id SERIAL PRIMARY KEY,
            article_id INT NOT NULL REFERENCES article(id) ON DELETE CASCADE,
            platform VARCHAR(20) NOT NULL,
            job_id VARCHAR(64) DEFAULT NULL,
            status VARCHAR(32) NOT NULL DEFAULT \'pending\',
            result_data JSON DEFAULT NULL,
            error_data JSON DEFAULT NULL,
            pending_input JSON DEFAULT NULL,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP NOT NULL,
            CONSTRAINT uq_article_submission_article_platform UNIQUE (article_id, platform)
        )');
        $this->addSql('CREATE INDEX idx_article_submission_job_id ON article_submission(job_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE article_submission');
    }
}
