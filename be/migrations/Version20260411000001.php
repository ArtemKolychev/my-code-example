<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add progress_data column to article_submission for informative progress messages (#66)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article_submission ADD progress_data JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article_submission DROP COLUMN progress_data');
    }
}
