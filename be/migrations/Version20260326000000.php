<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add withdrawn_at column to article table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD withdrawn_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN article.withdrawn_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP withdrawn_at');
    }
}
