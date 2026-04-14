<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260303091440 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE IF NOT EXISTS service_credential (id SERIAL NOT NULL, user_id INT NOT NULL, service VARCHAR(50) NOT NULL, login VARCHAR(255) NOT NULL, encrypted_password TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_932E24FFA76ED395 ON service_credential (user_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS unique_user_service ON service_credential (user_id, service)');
        $this->addSql('DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = \'fk_932e24ffa76ed395\') THEN ALTER TABLE service_credential ADD CONSTRAINT FK_932E24FFA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE; END IF; END $$');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE service_credential DROP CONSTRAINT FK_932E24FFA76ED395');
        $this->addSql('DROP TABLE service_credential');
    }
}
