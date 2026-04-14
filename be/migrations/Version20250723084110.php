<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250723084110 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article ADD price DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD is_published BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD address VARCHAR(250) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD phone VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "user" DROP address');
        $this->addSql('ALTER TABLE "user" DROP phone');
        $this->addSql('ALTER TABLE article DROP price');
        $this->addSql('ALTER TABLE article DROP is_published');
    }
}
