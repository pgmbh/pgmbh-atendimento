<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona coluna active às tabelas service_type, priority, sector e tag';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_type ADD COLUMN active BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql('ALTER TABLE priority      ADD COLUMN active BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql('ALTER TABLE sector        ADD COLUMN active BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql('ALTER TABLE tag           ADD COLUMN active BOOLEAN NOT NULL DEFAULT TRUE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_type DROP COLUMN active');
        $this->addSql('ALTER TABLE priority      DROP COLUMN active');
        $this->addSql('ALTER TABLE sector        DROP COLUMN active');
        $this->addSql('ALTER TABLE tag           DROP COLUMN active');
    }
}
