<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add terms_accepted_at column to user table for GDPR consent tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD terms_accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN "user".terms_accepted_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP terms_accepted_at');
    }
}
