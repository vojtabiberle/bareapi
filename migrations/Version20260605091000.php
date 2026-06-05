<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605091000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create versioned JSON schema store';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE schemas (
                id UUID NOT NULL,
                object_type VARCHAR(100) NOT NULL,
                version VARCHAR(20) NOT NULL,
                is_default BOOLEAN NOT NULL DEFAULT FALSE,
                schema JSONB NOT NULL,
                description TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_by JSONB DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_schema_object_type_version ON schemas (object_type, version)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_schemas_object_type ON schemas (object_type)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_default_schema_per_object_type ON schemas (object_type) WHERE is_default = TRUE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE schemas');
    }
}
