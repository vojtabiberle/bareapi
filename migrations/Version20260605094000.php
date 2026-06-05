<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605094000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create meta object reference table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE meta_refs (
                id SERIAL NOT NULL,
                from_type VARCHAR(100) NOT NULL,
                from_uuid UUID NOT NULL,
                path VARCHAR(255) NOT NULL,
                to_type VARCHAR(100) NOT NULL,
                to_uuid UUID NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_meta_refs_from ON meta_refs (from_type, from_uuid)');
        $this->addSql('CREATE INDEX idx_meta_refs_to ON meta_refs (to_type, to_uuid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE meta_refs');
    }
}
