<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create immutable meta object revisions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE meta_object_revisions (
                id SERIAL NOT NULL,
                uuid UUID NOT NULL,
                revision INTEGER NOT NULL,
                parent_id UUID DEFAULT NULL,
                data JSONB NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('ALTER TABLE meta_object_revisions ADD CONSTRAINT fk_meta_object_revision_object FOREIGN KEY (uuid) REFERENCES meta_objects (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE meta_object_revisions ADD CONSTRAINT fk_meta_object_revision_parent FOREIGN KEY (parent_id) REFERENCES meta_objects (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_meta_object_revision ON meta_object_revisions (uuid, revision)');
        $this->addSql('CREATE INDEX idx_meta_object_revisions_uuid_revision ON meta_object_revisions (uuid, revision DESC)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE meta_object_revisions');
    }
}
