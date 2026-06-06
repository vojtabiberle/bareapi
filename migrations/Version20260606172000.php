<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606172000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Limit meta object name uniqueness to active rows';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_meta_objects_type_name_branch');
        $this->addSql("CREATE UNIQUE INDEX uniq_meta_objects_type_name_branch ON meta_objects (object_type, name, branch) WHERE name <> '' AND deleted_at IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_meta_objects_type_name_branch');
        $this->addSql("CREATE UNIQUE INDEX uniq_meta_objects_type_name_branch ON meta_objects (object_type, name, branch) WHERE name <> '' AND deleted_at IS NULL");
    }
}
