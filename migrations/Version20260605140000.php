<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove unused parent reference from meta object revisions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE meta_object_revisions DROP CONSTRAINT IF EXISTS fk_meta_object_revision_parent');
        $this->addSql('ALTER TABLE meta_object_revisions DROP COLUMN IF EXISTS parent_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE meta_object_revisions ADD parent_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE meta_object_revisions ADD CONSTRAINT fk_meta_object_revision_parent FOREIGN KEY (parent_id) REFERENCES meta_objects (id) ON DELETE SET NULL');
    }
}
