<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomeia service_type "Implantação" para "Implementação inicial"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE service_type SET name = 'Implementação inicial' WHERE name = 'Implantação'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE service_type SET name = 'Implantação' WHERE name = 'Implementação inicial'");
    }
}
