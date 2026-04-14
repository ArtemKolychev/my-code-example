<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add token_balance column to user table for AI token usage tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD token_balance INT DEFAULT 100000 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP token_balance');
    }
}
