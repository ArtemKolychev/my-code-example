<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add category and condition columns to article table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD COLUMN category VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD COLUMN condition VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP COLUMN category');
        $this->addSql('ALTER TABLE article DROP COLUMN condition');
    }
}
