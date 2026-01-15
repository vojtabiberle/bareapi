<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Create meta_refs table for reference tracking.
 *
 * This table maintains a reverse index of x-metastore.refersTo relationships
 * for fast inbound lookups, delete cascade planning, and reference counting.
 */
final class Version20250115120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create meta_refs table for reference tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE meta_refs (
                project_id INTEGER,
                from_type VARCHAR(100) NOT NULL,
                from_uuid UUID NOT NULL,
                path VARCHAR(255) NOT NULL,
                to_type VARCHAR(100) NOT NULL,
                to_uuid UUID NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (from_type, from_uuid, path, to_type, to_uuid)
            )
        SQL);

        // Partial unique index for NULL project_id rows
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_meta_refs_null_project
            ON meta_refs (from_type, from_uuid, path, to_type, to_uuid)
            WHERE project_id IS NULL
        SQL);

        // Index for inbound lookups (who references target object?)
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_meta_refs_inbound
            ON meta_refs (project_id, to_type, to_uuid)
        SQL);

        // Index for outbound lookups (what does source object reference?)
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_meta_refs_outbound
            ON meta_refs (project_id, from_type, from_uuid)
        SQL);

        $this->addSql(<<<'SQL'
            COMMENT ON TABLE meta_refs IS 'Reverse index for x-metastore.refersTo relationships'
        SQL);

        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN meta_refs.from_uuid IS '(DC2Type:uuid)'
        SQL);

        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN meta_refs.to_uuid IS '(DC2Type:uuid)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS meta_refs');
    }
}
