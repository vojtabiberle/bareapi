<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605092000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product-neutral metadata columns to meta objects';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE meta_objects
                ADD object_type VARCHAR(100) DEFAULT NULL,
                ADD branch VARCHAR(50) NOT NULL DEFAULT 'main',
                ADD name VARCHAR(255) NOT NULL DEFAULT '',
                ADD last_updated TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE meta_objects
            SET object_type = type,
                last_updated = updated_at
            WHERE object_type IS NULL
        SQL);
        $this->addSql('ALTER TABLE meta_objects ALTER object_type SET NOT NULL');
        $this->addSql('ALTER TABLE meta_objects ALTER last_updated SET NOT NULL');
        $this->addSql('CREATE INDEX idx_meta_objects_object_type ON meta_objects (object_type)');
        $this->addSql('CREATE INDEX idx_meta_objects_object_type_branch ON meta_objects (object_type, branch)');
        $this->addSql("CREATE UNIQUE INDEX uniq_meta_objects_type_name_branch ON meta_objects (object_type, name, branch) WHERE name <> ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_meta_objects_type_name_branch');
        $this->addSql('DROP INDEX idx_meta_objects_object_type_branch');
        $this->addSql('DROP INDEX idx_meta_objects_object_type');
        $this->addSql(<<<'SQL'
            ALTER TABLE meta_objects
                DROP object_type,
                DROP branch,
                DROP name,
                DROP last_updated,
                DROP deleted_at
        SQL);
    }
}
