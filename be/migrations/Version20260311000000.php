<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add platform and last_bumped_at columns to article table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE article ADD COLUMN IF NOT EXISTS platform VARCHAR(50) NOT NULL DEFAULT 'seznam'");
        $this->addSql('ALTER TABLE article ADD COLUMN IF NOT EXISTS last_bumped_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP COLUMN IF EXISTS platform');
        $this->addSql('ALTER TABLE article DROP COLUMN IF EXISTS last_bumped_at');
    }
}
