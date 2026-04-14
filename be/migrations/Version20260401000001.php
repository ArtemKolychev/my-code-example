<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove is_published column from article table (derived from article_submission)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP COLUMN IF EXISTS is_published');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD COLUMN IF NOT EXISTS is_published BOOLEAN DEFAULT false NOT NULL');
    }
}
