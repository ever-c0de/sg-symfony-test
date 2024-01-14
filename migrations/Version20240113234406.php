<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240113234406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE failure_report (id INT NOT NULL, description VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, date_of_service_visit DATE DEFAULT NULL, priority VARCHAR(50) NOT NULL, status VARCHAR(25) NOT NULL, service_comments VARCHAR(255) DEFAULT NULL, client_phone VARCHAR(35) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN failure_report.client_phone IS \'(DC2Type:phone_number)\'');
        $this->addSql('COMMENT ON COLUMN failure_report.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE failure_report');
    }
}
