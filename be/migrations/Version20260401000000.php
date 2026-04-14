<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove platform and last_bumped_at columns from article table (bump feature removed)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP COLUMN IF EXISTS platform');
        $this->addSql('ALTER TABLE article DROP COLUMN IF EXISTS last_bumped_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE article ADD COLUMN IF NOT EXISTS platform VARCHAR(255) NOT NULL DEFAULT 'seznam'");
        $this->addSql('ALTER TABLE article ADD COLUMN IF NOT EXISTS last_bumped_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN article.last_bumped_at IS '(DC2Type:datetime_immutable)'");
    }
}
