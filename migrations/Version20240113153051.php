<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240113153051 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE failure_report ALTER client_phone TYPE VARCHAR(35)');
        $this->addSql('ALTER TABLE failure_report ALTER client_phone TYPE VARCHAR(35)');
        $this->addSql('COMMENT ON COLUMN failure_report.client_phone IS \'(DC2Type:phone_number)\'');
        $this->addSql('ALTER TABLE review ALTER client_phone TYPE VARCHAR(35)');
        $this->addSql('ALTER TABLE review ALTER client_phone TYPE VARCHAR(35)');
        $this->addSql('COMMENT ON COLUMN review.client_phone IS \'(DC2Type:phone_number)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE failure_report ALTER client_phone TYPE VARCHAR(20)');
        $this->addSql('COMMENT ON COLUMN failure_report.client_phone IS NULL');
        $this->addSql('ALTER TABLE review ALTER client_phone TYPE VARCHAR(20)');
        $this->addSql('COMMENT ON COLUMN review.client_phone IS NULL');
    }
}
