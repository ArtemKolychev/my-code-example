<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260317000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add position column to image table for AI-selected cover ordering';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image ADD COLUMN position INT NOT NULL DEFAULT 0');
        $this->addSql('CREATE INDEX idx_image_position ON image(article_id, position)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_image_position');
        $this->addSql('ALTER TABLE image DROP COLUMN position');
    }
}
