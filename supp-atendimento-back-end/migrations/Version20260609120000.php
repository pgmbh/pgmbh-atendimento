<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Normaliza service.status e service.priority para FKs.
 * Cria tabelas: status, priority, tag, service_tag.
 * Adiciona status "backlog" e "product backlog" (ClickUp).
 * Adiciona 12 etiquetas do ClickUp.
 */
final class Version20260609120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normaliza status/priority para FK, cria tabelas tag/service_tag, seeds de domínio';
    }

    public function up(Schema $schema): void
    {
        // -------------------------------------------------------------------
        // 1. Tabela status
        // -------------------------------------------------------------------
        $this->addSql('CREATE TABLE status (
            id        SERIAL PRIMARY KEY,
            name      VARCHAR(30)  NOT NULL,
            label     VARCHAR(50)  NOT NULL,
            color     VARCHAR(20)  NOT NULL,
            type      VARCHAR(20)  NOT NULL,
            sort_order INT         NOT NULL,
            CONSTRAINT uq_status_name UNIQUE (name)
        )');

        $this->addSql("INSERT INTO status (name, label, color, type, sort_order) VALUES
            ('NOVO',            'Novo',             '#87909e', 'open',   0),
            ('OPEN',            'Aberto',           '#4e84c4', 'open',   1),
            ('IN_PROGRESS',     'Em Andamento',     '#ffc53d', 'custom', 2),
            ('RESOLVED',        'Resolvido',        '#23a55a', 'custom', 3),
            ('RETORNO',         'Retorno',          '#00b0d7', 'custom', 4),
            ('CANCELADO',       'Cancelado',        '#e5484d', 'done',   5),
            ('CONCLUDED',       'Concluído',        '#9b59b6', 'closed', 6),
            ('backlog',         'Backlog',          '#87909e', 'open',   7),
            ('product backlog', 'Product Backlog',  '#87909e', 'open',   8)
        ");

        // -------------------------------------------------------------------
        // 2. Tabela priority
        // -------------------------------------------------------------------
        $this->addSql('CREATE TABLE priority (
            id      SERIAL PRIMARY KEY,
            name    VARCHAR(20) NOT NULL,
            label   VARCHAR(50) NOT NULL,
            color   VARCHAR(20) NOT NULL,
            weight  INT         NOT NULL,
            CONSTRAINT uq_priority_name UNIQUE (name)
        )');

        $this->addSql("INSERT INTO priority (name, label, color, weight) VALUES
            ('URGENTE', 'Urgente', '#e5484d', 0),
            ('ALTA',    'Alta',    '#f76808', 1),
            ('NORMAL',  'Normal',  '#4e84c4', 2),
            ('BAIXA',   'Baixa',   '#23a55a', 3)
        ");

        // -------------------------------------------------------------------
        // 3. Tabela tag
        // -------------------------------------------------------------------
        $this->addSql('CREATE TABLE tag (
            id    SERIAL PRIMARY KEY,
            name  VARCHAR(60) NOT NULL,
            color VARCHAR(20) NOT NULL,
            CONSTRAINT uq_tag_name UNIQUE (name)
        )');

        $this->addSql("INSERT INTO tag (name, color) VALUES
            ('aprimoramento',         '#0197a7'),
            ('correção de bugs',      '#cddc39'),
            ('defeito',               '#dc646a'),
            ('esforço:alto',          '#3e2724'),
            ('esforço:baixo',         '#e16b16'),
            ('esforço:médio',         '#c51162'),
            ('estudo',                '#827718'),
            ('implementação inicial', '#ff897f'),
            ('melhoria de desempenho','#304ffe'),
            ('nova funcionalidade',   '#6A85FF'),
            ('novo recurso',          '#d60800'),
            ('ui/ux',                 '#aa2fff')
        ");

        // -------------------------------------------------------------------
        // 4. Tabela de junção service_tag
        // -------------------------------------------------------------------
        $this->addSql('CREATE TABLE service_tag (
            service_id INT NOT NULL,
            tag_id     INT NOT NULL,
            PRIMARY KEY (service_id, tag_id),
            CONSTRAINT fk_service_tag_service FOREIGN KEY (service_id) REFERENCES service(id) ON DELETE CASCADE,
            CONSTRAINT fk_service_tag_tag     FOREIGN KEY (tag_id)     REFERENCES tag(id)     ON DELETE CASCADE
        )');

        // -------------------------------------------------------------------
        // 5. Adiciona colunas FK (nullable temporariamente para backfill)
        // -------------------------------------------------------------------
        $this->addSql('ALTER TABLE service ADD COLUMN status_id   INT');
        $this->addSql('ALTER TABLE service ADD COLUMN priority_id INT');

        // -------------------------------------------------------------------
        // 6. Backfill status_id
        // -------------------------------------------------------------------
        $this->addSql("UPDATE service s SET status_id = st.id
            FROM status st WHERE st.name = s.status");

        // Fallback para qualquer status não mapeado → NOVO
        $this->addSql("UPDATE service SET status_id = (SELECT id FROM status WHERE name = 'NOVO')
            WHERE status_id IS NULL");

        // -------------------------------------------------------------------
        // 7. Backfill priority_id
        // -------------------------------------------------------------------
        $this->addSql("UPDATE service s SET priority_id = p.id
            FROM priority p WHERE p.name = s.priority");

        // Fallback para qualquer prioridade não mapeada → NORMAL
        $this->addSql("UPDATE service SET priority_id = (SELECT id FROM priority WHERE name = 'NORMAL')
            WHERE priority_id IS NULL");

        // -------------------------------------------------------------------
        // 8. Torna as colunas NOT NULL, adiciona FKs, remove colunas antigas
        // -------------------------------------------------------------------
        $this->addSql('ALTER TABLE service ALTER COLUMN status_id   SET NOT NULL');
        $this->addSql('ALTER TABLE service ALTER COLUMN priority_id SET NOT NULL');

        $this->addSql('ALTER TABLE service
            ADD CONSTRAINT fk_service_status   FOREIGN KEY (status_id)   REFERENCES status(id),
            ADD CONSTRAINT fk_service_priority FOREIGN KEY (priority_id) REFERENCES priority(id)
        ');

        $this->addSql('ALTER TABLE service DROP COLUMN status');
        $this->addSql('ALTER TABLE service DROP COLUMN priority');
    }

    public function down(Schema $schema): void
    {
        // Restaura as colunas de texto e remove estruturas novas
        $this->addSql('ALTER TABLE service ADD COLUMN status   VARCHAR(30)');
        $this->addSql('ALTER TABLE service ADD COLUMN priority VARCHAR(20)');

        $this->addSql("UPDATE service s SET status   = st.name FROM status   st WHERE st.id = s.status_id");
        $this->addSql("UPDATE service s SET priority = p.name  FROM priority p  WHERE p.id  = s.priority_id");

        $this->addSql('ALTER TABLE service ALTER COLUMN status   SET NOT NULL');
        $this->addSql('ALTER TABLE service ALTER COLUMN priority SET NOT NULL');

        $this->addSql('ALTER TABLE service DROP CONSTRAINT fk_service_status');
        $this->addSql('ALTER TABLE service DROP CONSTRAINT fk_service_priority');
        $this->addSql('ALTER TABLE service DROP COLUMN status_id');
        $this->addSql('ALTER TABLE service DROP COLUMN priority_id');

        $this->addSql('DROP TABLE service_tag');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE priority');
        $this->addSql('DROP TABLE status');
    }
}
