<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pending_input column to article table for SMS input wait state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD pending_input JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP COLUMN pending_input');
    }
}
