<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260307000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add createdAt column to article table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'article\' AND column_name = \'created_at\') THEN ALTER TABLE article ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL; END IF; END $$');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP COLUMN created_at');
    }
}
