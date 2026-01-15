# RFC: PHP Symfony Reimplementation of Go Metastore Service

## Document Information

| Field | Value |
|-------|-------|
| RFC Title | PHP Symfony Reimplementation of Go Metastore Service |
| Status | Draft |
| Authors | Architecture Team |
| Created | 2026-01-15 |
| Target Framework | Symfony 7.x (latest stable) |
| PHP Version | 8.2+ |

---

## 1. Executive Summary

### 1.1 Purpose

This RFC outlines the complete reimplementation of the existing Go-based metastore service as a standalone PHP application using the Symfony framework. The metastore service manages metadata objects with JSON Schema validation, revision control, multi-tenancy support, and role-based access control.

### 1.2 Goals

- Create a functionally equivalent PHP/Symfony implementation
- Maintain full database compatibility with the existing PostgreSQL schema
- Preserve all API contracts (JSON:API responses, endpoint structure)
- Implement equivalent authorization, validation, and telemetry features
- Enable seamless migration with zero data loss

### 1.3 Non-Goals

- Modifying the existing database schema beyond what is necessary
- Adding new features not present in the Go implementation
- Supporting PHP versions below 8.2
- Integration with the Go service (this is a complete replacement)

### 1.4 Key Features to Implement

- Full CRUD operations for metadata objects
- JSON Schema validation (draft 2020-12)
- Revision control with soft deletes
- Multi-tenancy (project and organization scoping)
- Role-based access control (RBAC) with schema-defined ACL policies
- JSON:API compliant responses
- OpenTelemetry for tracing/metrics
- Storage API Token authentication

---

## 2. Architecture Overview

### 2.1 Go to Symfony Component Mapping

| Go Component | Symfony Equivalent |
|--------------|-------------------|
| Chi Router | Symfony Router + Controllers |
| pgx/v5 (PostgreSQL driver) | Doctrine DBAL/ORM |
| Dependency Injection (custom) | Symfony DI Container |
| Middleware chain | Symfony Event Listeners / Kernel Events |
| go-playground/validator | Symfony Validator |
| santhosh-tekuri/jsonschema | opis/json-schema |
| api2go/jsonapi | Custom JSON:API serializer |
| OpenTelemetry Go SDK | OpenTelemetry PHP SDK |
| Zap Logger | Monolog |
| envconfig | Symfony DotEnv + Configuration |

### 2.2 High-Level Architecture Diagram

```
                                    +------------------+
                                    |   HTTP Request   |
                                    +--------+---------+
                                             |
                                    +--------v---------+
                                    |  Symfony Kernel  |
                                    +--------+---------+
                                             |
                              +--------------+--------------+
                              |                             |
                    +---------v----------+       +----------v---------+
                    | Authentication     |       | Request Logging    |
                    | Event Listener     |       | Event Listener     |
                    +---------+----------+       +--------------------+
                              |
                    +---------v----------+
                    |    Controller      |
                    |  (handles routing) |
                    +---------+----------+
                              |
              +---------------+---------------+
              |               |               |
     +--------v------+ +------v-------+ +-----v--------+
     | Authorization | | Validation   | | Repository   |
     | Service       | | Service      | | Layer        |
     +---------------+ +--------------+ +------+-------+
                                               |
                                        +------v-------+
                                        |   Doctrine   |
                                        |   DBAL/ORM   |
                                        +------+-------+
                                               |
                                        +------v-------+
                                        |  PostgreSQL  |
                                        +--------------+
```

### 2.3 Request Flow

1. HTTP request enters Symfony Kernel
2. `kernel.request` event triggers authentication listener (validates Storage API token)
3. Router matches request to controller action
4. Controller invokes authorization service (checks ACL policy)
5. Controller delegates to repository for data operations
6. Repository validates data against JSON Schema
7. Repository performs database operations via Doctrine
8. Response formatted as JSON:API and returned

---

## 3. Directory Structure

```
metastore-php/
├── bin/
│   └── console                          # Symfony console entry point
├── config/
│   ├── packages/
│   │   ├── doctrine.yaml                # Doctrine configuration
│   │   ├── framework.yaml               # Framework configuration
│   │   ├── monolog.yaml                 # Logging configuration
│   │   ├── open_telemetry.yaml          # OpenTelemetry configuration
│   │   └── validator.yaml               # Validator configuration
│   ├── routes/
│   │   └── api.yaml                     # API route definitions
│   ├── services.yaml                    # Service definitions
│   └── bundles.php                      # Bundle registration
├── migrations/
│   └── Version*.php                     # Doctrine migrations (compatibility)
├── public/
│   ├── index.php                        # Front controller
│   └── api-docs/                        # Swagger UI assets
├── src/
│   ├── Controller/
│   │   └── Api/
│   │       ├── HealthCheckController.php
│   │       ├── IndexController.php
│   │       ├── DocumentationController.php
│   │       ├── SchemaController.php
│   │       └── RepositoryController.php
│   ├── Entity/
│   │   ├── Schema.php
│   │   ├── MetaObject.php
│   │   └── MetaObjectRevision.php
│   ├── Repository/
│   │   ├── SchemaRepositoryInterface.php
│   │   ├── SchemaRepository.php
│   │   ├── MetaObjectRepositoryInterface.php
│   │   └── MetaObjectRepository.php
│   ├── Security/
│   │   ├── StorageApiAuthenticator.php
│   │   ├── TokenValidator.php
│   │   ├── Identity.php
│   │   ├── IdentityFactory.php
│   │   ├── ProjectScope.php
│   │   └── StorageApiToken.php
│   ├── Authorization/
│   │   ├── AuthorizationService.php
│   │   ├── PolicyEvaluator.php
│   │   ├── Policy.php
│   │   ├── Rule.php
│   │   ├── RoleMapper.php
│   │   ├── AuthorizationRequest.php
│   │   ├── ObjectContext.php
│   │   ├── ScopeHint.php
│   │   └── Types.php                    # Role, Action, Scope enums
│   ├── Validation/
│   │   ├── JsonSchemaValidator.php
│   │   └── AclParser.php
│   ├── Response/
│   │   ├── JsonApiSerializer.php
│   │   └── ErrorResponse.php
│   ├── EventListener/
│   │   ├── AuthenticationListener.php
│   │   ├── RequestLoggingListener.php
│   │   └── ExceptionListener.php
│   ├── DTO/
│   │   ├── CreateRequest.php
│   │   ├── UpdatePatchRequest.php
│   │   ├── UpdatePutRequest.php
│   │   └── MetaObjectResponse.php
│   ├── Doctrine/
│   │   └── Type/
│   │       └── JsonbType.php
│   ├── Service/
│   │   ├── TransactionManager.php
│   │   ├── MetaObjectService.php
│   │   └── SchemaService.php
│   ├── Exception/
│   │   ├── MetaObjectNotFoundException.php
│   │   ├── SchemaNotFoundException.php
│   │   ├── ValidationException.php
│   │   ├── ForbiddenException.php
│   │   └── UnauthorizedException.php
│   ├── Logging/
│   │   └── JsonFormatter.php
│   ├── Telemetry/
│   │   └── TelemetryService.php
│   ├── Config/
│   │   ├── MetastoreConfig.php
│   │   ├── DatabaseConfig.php
│   │   ├── DatadogConfig.php
│   │   └── MetricsConfig.php
│   └── Kernel.php
├── tests/
│   ├── Unit/
│   │   ├── Authorization/
│   │   ├── Validation/
│   │   └── Repository/
│   ├── Integration/
│   │   └── Repository/
│   └── Functional/
│       └── Controller/
├── var/
│   ├── cache/
│   └── log/
├── vendor/
├── .env
├── .env.local
├── composer.json
├── composer.lock
├── docker-compose.yaml
├── Dockerfile
└── phpunit.xml.dist
```

---

## 4. Database Layer (Doctrine ORM/DBAL)

### 4.1 Strategy

Use Doctrine DBAL for direct SQL queries where performance is critical and ORM for entity management. The existing Go service uses raw SQL with squirrel query builder, so a hybrid approach is recommended.

### 4.2 Connection Configuration

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        driver: 'pdo_pgsql'
        server_version: '14'
        charset: utf8
        url: '%env(resolve:DATABASE_URL)%'
        types:
            jsonb: App\Doctrine\Type\JsonbType
    orm:
        auto_generate_proxy_classes: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        mappings:
            App:
                is_bundle: false
                type: attribute
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
                alias: App
```

### 4.3 Custom JSONB Type

```php
<?php
// src/Doctrine/Type/JsonbType.php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class JsonbType extends Type
{
    public const NAME = 'jsonb';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'JSONB';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?array
    {
        if ($value === null) {
            return null;
        }
        return json_decode($value, true);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        return json_encode($value);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
```

### 4.4 Transaction Support

```php
<?php
// src/Service/TransactionManager.php

namespace App\Service;

use Doctrine\DBAL\Connection;

class TransactionManager
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transactional(callable $callback): mixed
    {
        $this->connection->beginTransaction();
        try {
            $result = $callback();
            $this->connection->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
}
```

---

## 5. Entity Definitions

### 5.1 Schema Entity

```php
<?php
// src/Entity/Schema.php

namespace App\Entity;

use App\Repository\SchemaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SchemaRepository::class)]
#[ORM\Table(name: 'schemas')]
#[ORM\Index(columns: ['object_type'], name: 'idx_schemas_object_type')]
#[ORM\UniqueConstraint(name: 'uniq_schema_object_type_version', columns: ['object_type', 'version'])]
class Schema
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(name: 'object_type', length: 100)]
    private string $objectType;

    #[ORM\Column(length: 20)]
    private string $version;

    #[ORM\Column(name: 'is_default')]
    private bool $isDefault = false;

    #[ORM\Column(type: 'jsonb')]
    private array $schema;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'created_by', type: 'jsonb', nullable: true)]
    private ?array $createdBy = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getObjectType(): string
    {
        return $this->objectType;
    }

    public function setObjectType(string $objectType): self
    {
        $this->objectType = $objectType;
        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }

    public function setSchema(array $schema): self
    {
        $this->schema = $schema;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getCreatedBy(): ?array
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?array $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }
}
```

### 5.2 MetaObject Entity

```php
<?php
// src/Entity/MetaObject.php

namespace App\Entity;

use App\Repository\MetaObjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MetaObjectRepository::class)]
#[ORM\Table(name: 'meta_objects')]
#[ORM\Index(columns: ['project_id'], name: 'idx_meta_objects_project_id')]
#[ORM\Index(columns: ['object_type', 'project_id'], name: 'idx_meta_objects_type_project')]
#[ORM\Index(columns: ['organization_id', 'project_id', 'object_type'], name: 'idx_meta_objects_org_project_type')]
#[ORM\UniqueConstraint(name: 'uniq_meta_object', columns: ['object_type', 'name', 'branch', 'project_id'])]
class MetaObject
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $uuid;

    #[ORM\Column(name: 'object_type', length: 100)]
    private string $objectType;

    #[ORM\Column(name: 'schema_version', length: 100)]
    private string $schemaVersion;

    #[ORM\Column(length: 50)]
    private string $branch = 'main';

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(name: 'project_id', nullable: true)]
    private ?int $projectId = null;

    #[ORM\Column(name: 'organization_id', type: Types::TEXT)]
    private string $organizationId;

    #[ORM\Column(name: 'last_updated', type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $lastUpdated;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\OneToMany(mappedBy: 'metaObject', targetEntity: MetaObjectRevision::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['revision' => 'DESC'])]
    private Collection $revisions;

    public function __construct()
    {
        $this->uuid = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->lastUpdated = new \DateTimeImmutable();
        $this->revisions = new ArrayCollection();
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function setUuid(Uuid $uuid): self
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function getObjectType(): string
    {
        return $this->objectType;
    }

    public function setObjectType(string $objectType): self
    {
        $this->objectType = $objectType;
        return $this;
    }

    public function getSchemaVersion(): string
    {
        return $this->schemaVersion;
    }

    public function setSchemaVersion(string $schemaVersion): self
    {
        $this->schemaVersion = $schemaVersion;
        return $this;
    }

    public function getBranch(): string
    {
        return $this->branch;
    }

    public function setBranch(string $branch): self
    {
        $this->branch = $branch;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getProjectId(): ?int
    {
        return $this->projectId;
    }

    public function setProjectId(?int $projectId): self
    {
        $this->projectId = $projectId;
        return $this;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function setOrganizationId(string $organizationId): self
    {
        $this->organizationId = $organizationId;
        return $this;
    }

    public function getLastUpdated(): \DateTimeImmutable
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(\DateTimeImmutable $lastUpdated): self
    {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function getRevisions(): Collection
    {
        return $this->revisions;
    }

    public function addRevision(MetaObjectRevision $revision): self
    {
        if (!$this->revisions->contains($revision)) {
            $this->revisions->add($revision);
            $revision->setMetaObject($this);
        }
        return $this;
    }

    public function getLatestRevision(): ?MetaObjectRevision
    {
        $filtered = $this->revisions->filter(fn ($r) => $r->getDeletedAt() === null);
        return $filtered->first() ?: null;
    }
}
```

### 5.3 MetaObjectRevision Entity

```php
<?php
// src/Entity/MetaObjectRevision.php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'meta_object_revisions')]
#[ORM\Index(columns: ['uuid', 'revision'], name: 'idx_meta_object_revisions_uuid_revision')]
#[ORM\UniqueConstraint(name: 'uniq_object_revision', columns: ['uuid', 'revision'])]
class MetaObjectRevision
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MetaObject::class, inversedBy: 'revisions')]
    #[ORM\JoinColumn(name: 'uuid', referencedColumnName: 'uuid', onDelete: 'CASCADE')]
    private MetaObject $metaObject;

    #[ORM\Column]
    private int $revision;

    #[ORM\Column(name: 'parent_id', type: 'uuid', nullable: true)]
    private ?Uuid $parentId = null;

    #[ORM\Column(type: 'jsonb')]
    private array $data;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMetaObject(): MetaObject
    {
        return $this->metaObject;
    }

    public function setMetaObject(MetaObject $metaObject): self
    {
        $this->metaObject = $metaObject;
        return $this;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function setRevision(int $revision): self
    {
        $this->revision = $revision;
        return $this;
    }

    public function getParentId(): ?Uuid
    {
        return $this->parentId;
    }

    public function setParentId(?Uuid $parentId): self
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }
}
```

---

## 6. Repository Interfaces and Implementations

### 6.1 Schema Repository Interface

```php
<?php
// src/Repository/SchemaRepositoryInterface.php

namespace App\Repository;

use App\Entity\Schema;

interface SchemaRepositoryInterface
{
    public function getByObjectType(string $objectType, string $version = ''): ?Schema;

    public function getFilterableFields(string $objectType, string $version = ''): array;
}
```

### 6.2 Schema Repository Implementation

```php
<?php
// src/Repository/SchemaRepository.php

namespace App\Repository;

use App\Entity\Schema;
use App\Exception\SchemaNotFoundException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SchemaRepository extends ServiceEntityRepository implements SchemaRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Schema::class);
    }

    public function getByObjectType(string $objectType, string $version = ''): ?Schema
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.objectType = :objectType')
            ->setParameter('objectType', $objectType);

        if ($version !== '') {
            $qb->andWhere('s.version = :version')
               ->setParameter('version', $version);
        } else {
            $qb->andWhere('s.isDefault = true');
        }

        $schema = $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();

        if ($schema === null) {
            throw new SchemaNotFoundException(
                sprintf('Schema not found for object type: %s', $objectType)
            );
        }

        return $schema;
    }

    public function getFilterableFields(string $objectType, string $version = ''): array
    {
        $schema = $this->getByObjectType($objectType, $version);
        $schemaData = $schema->getSchema();

        $properties = $schemaData['properties'] ?? [];
        $hasXFilterable = $this->checkForXFilterable($properties);

        return $this->collectFilterableFields($properties, '', $hasXFilterable);
    }

    private function checkForXFilterable(array $props): bool
    {
        foreach ($props as $prop) {
            if (!is_array($prop)) {
                continue;
            }
            if (isset($prop['x-filterable'])) {
                return true;
            }
            if (($prop['type'] ?? '') === 'object' && isset($prop['properties'])) {
                if ($this->checkForXFilterable($prop['properties'])) {
                    return true;
                }
            }
        }
        return false;
    }

    private function collectFilterableFields(array $props, string $prefix, bool $hasXFilterable): array
    {
        $filterable = [];

        foreach ($props as $field => $prop) {
            if (!is_array($prop)) {
                continue;
            }

            $path = $prefix !== '' ? "{$prefix}.{$field}" : $field;

            if ($hasXFilterable) {
                if (($prop['x-filterable'] ?? false) === true) {
                    $filterable[] = $path;
                }
            } else {
                $type = $prop['type'] ?? '';
                if ($type !== 'object') {
                    $filterable[] = $path;
                }
            }

            if (($prop['type'] ?? '') === 'object' && isset($prop['properties'])) {
                $filterable = array_merge(
                    $filterable,
                    $this->collectFilterableFields($prop['properties'], $path, $hasXFilterable)
                );
            }
        }

        return $filterable;
    }
}
```

### 6.3 MetaObject Repository Interface

```php
<?php
// src/Repository/MetaObjectRepositoryInterface.php

namespace App\Repository;

use App\DTO\CreateRequest;
use App\DTO\MetaObjectResponse;
use App\DTO\UpdatePatchRequest;
use App\DTO\UpdatePutRequest;
use Symfony\Component\Uid\Uuid;

interface MetaObjectRepositoryInterface
{
    public function create(
        string $objectType,
        CreateRequest $request,
        int $projectId,
        string $organizationId,
        string $scopeOverride = ''
    ): MetaObjectResponse;

    public function getByUuid(
        string $objectType,
        Uuid $id,
        int $projectId,
        string $organizationId
    ): MetaObjectResponse;

    public function patch(
        string $objectType,
        Uuid $id,
        UpdatePatchRequest $request,
        int $projectId,
        string $organizationId
    ): MetaObjectResponse;

    public function put(
        string $objectType,
        Uuid $id,
        UpdatePutRequest $request,
        int $projectId,
        string $organizationId
    ): MetaObjectResponse;

    public function getRevision(
        string $objectType,
        Uuid $id,
        int $revision,
        int $projectId,
        string $organizationId
    ): MetaObjectResponse;

    public function softDelete(
        string $objectType,
        Uuid $id,
        int $projectId,
        string $organizationId
    ): void;

    public function softDeleteRevision(
        string $objectType,
        Uuid $objectUuid,
        int $revision,
        int $projectId,
        string $organizationId
    ): void;

    /**
     * @return MetaObjectResponse[]
     */
    public function listMetaObjects(
        string $objectType,
        array $filters,
        int $projectId,
        string $organizationId
    ): array;

    /**
     * @return MetaObjectResponse[]
     */
    public function listMetaObjectRevisions(
        string $objectType,
        array $filters,
        int $projectId,
        string $organizationId
    ): array;
}
```

### 6.4 MetaObject Repository Implementation (Key Methods)

```php
<?php
// src/Repository/MetaObjectRepository.php

namespace App\Repository;

use App\DTO\CreateRequest;
use App\DTO\MetaObjectResponse;
use App\Entity\MetaObject;
use App\Entity\MetaObjectRevision;
use App\Exception\MetaObjectNotFoundException;
use App\Service\TransactionManager;
use App\Validation\JsonSchemaValidator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

class MetaObjectRepository extends ServiceEntityRepository implements MetaObjectRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private SchemaRepositoryInterface $schemaRepository,
        private JsonSchemaValidator $validator,
        private TransactionManager $transactionManager,
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
        parent::__construct($registry, MetaObject::class);
    }

    public function create(
        string $objectType,
        CreateRequest $request,
        int $projectId,
        string $organizationId,
        string $scopeOverride = ''
    ): MetaObjectResponse {
        return $this->transactionManager->transactional(function () use (
            $objectType,
            $request,
            $projectId,
            $organizationId,
            $scopeOverride
        ) {
            $schema = $this->schemaRepository->getByObjectType($objectType, $request->schemaVersion);
            $scope = $this->resolveScope($schema->getSchema(), $scopeOverride);

            // Validate data against schema
            $this->validator->validate($schema->getSchema(), $request->data);

            $uuid = Uuid::v7();

            $metaObject = new MetaObject();
            $metaObject->setUuid($uuid);
            $metaObject->setObjectType($objectType);
            $metaObject->setSchemaVersion($schema->getVersion());
            $metaObject->setBranch($request->branch ?: 'main');
            $metaObject->setName($request->name);
            $metaObject->setOrganizationId($organizationId);

            if ($scope === 'project') {
                $metaObject->setProjectId($projectId);
            }

            $revision = new MetaObjectRevision();
            $revision->setRevision(1);
            $revision->setData($request->data);
            $metaObject->addRevision($revision);

            $this->getEntityManager()->persist($metaObject);
            $this->getEntityManager()->flush();

            $this->logger->info('Created new meta object', ['uuid' => (string) $uuid]);

            return MetaObjectResponse::fromEntity($metaObject, $revision);
        });
    }

    public function getByUuid(
        string $objectType,
        Uuid $id,
        int $projectId,
        string $organizationId
    ): MetaObjectResponse {
        $sql = <<<'SQL'
            SELECT
                m.uuid, m.object_type, m.schema_version, m.branch, m.name,
                m.project_id, m.organization_id, m.created_at, m.last_updated,
                r.revision, r.data, r.created_at AS revision_created_at
            FROM meta_objects m
            LEFT JOIN meta_object_revisions r ON m.uuid = r.uuid AND r.revision = (
                SELECT MAX(revision) FROM meta_object_revisions
                WHERE uuid = m.uuid AND deleted_at IS NULL
            )
            WHERE m.uuid = :id
              AND m.object_type = :objectType
              AND m.organization_id = :organizationId
              AND (m.project_id = :projectId OR m.project_id IS NULL)
              AND m.deleted_at IS NULL
        SQL;

        $result = $this->connection->fetchAssociative($sql, [
            'id' => (string) $id,
            'objectType' => $objectType,
            'organizationId' => $organizationId,
            'projectId' => $projectId,
        ]);

        if ($result === false) {
            throw new MetaObjectNotFoundException('Meta object not found');
        }

        return MetaObjectResponse::fromArray($result);
    }

    // Additional methods (patch, put, softDelete, etc.) follow similar patterns...

    private function resolveScope(array $schemaData, string $scopeOverride): string
    {
        $xMetastore = $schemaData['x-metastore'] ?? [];
        $defaultScope = 'project';

        $scopeConfig = $xMetastore['scope'] ?? null;
        if (is_string($scopeConfig)) {
            $defaultScope = $scopeConfig;
        } elseif (is_array($scopeConfig) && isset($scopeConfig['default'])) {
            $defaultScope = $scopeConfig['default'];
        }

        if ($scopeOverride !== '' && $this->scopeSupported($schemaData, $scopeOverride)) {
            return strtolower($scopeOverride);
        }

        return $defaultScope;
    }

    private function scopeSupported(array $schemaData, string $desired): bool
    {
        $xMetastore = $schemaData['x-metastore'] ?? [];
        $scopeConfig = $xMetastore['scope'] ?? null;

        if (is_string($scopeConfig)) {
            return strtolower($scopeConfig) === strtolower($desired);
        }

        if (is_array($scopeConfig)) {
            $supported = $scopeConfig['supported'] ?? [];
            foreach ($supported as $s) {
                if (strtolower($s) === strtolower($desired)) {
                    return true;
                }
            }
            if (isset($scopeConfig['default']) && strtolower($scopeConfig['default']) === strtolower($desired)) {
                return true;
            }
        }

        return false;
    }
}
```

---

## 7. Controller Structure

### 7.1 Repository Controller

```php
<?php
// src/Controller/Api/RepositoryController.php

namespace App\Controller\Api;

use App\Authorization\AuthorizationService;
use App\DTO\CreateRequest;
use App\DTO\UpdatePatchRequest;
use App\DTO\UpdatePutRequest;
use App\Repository\MetaObjectRepositoryInterface;
use App\Response\JsonApiSerializer;
use App\Security\Identity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/repository/{objectType}', name: 'api_repository_')]
class RepositoryController extends AbstractController
{
    public function __construct(
        private MetaObjectRepositoryInterface $repository,
        private AuthorizationService $authorizationService,
        private JsonApiSerializer $serializer,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        string $objectType,
        #[MapRequestPayload] CreateRequest $request,
        #[CurrentUser] Identity $identity,
    ): JsonResponse {
        $scope = $identity->getProjectScope();

        // Authorize
        $this->authorizationService->authorizeCreate(
            $objectType,
            $request->schemaVersion,
            $identity,
            $scope,
            $request->data,
            $request->scope ?? ''
        );

        $response = $this->repository->create(
            $objectType,
            $request,
            $scope->getProjectId(),
            $scope->getOrganizationId(),
            $request->scope ?? ''
        );

        return $this->serializer->created($response);
    }

    #[Route('/{uuid}', name: 'get', methods: ['GET'])]
    public function get(
        string $objectType,
        string $uuid,
        #[CurrentUser] Identity $identity,
    ): JsonResponse {
        $scope = $identity->getProjectScope();

        $response = $this->repository->getByUuid(
            $objectType,
            Uuid::fromString($uuid),
            $scope->getProjectId(),
            $scope->getOrganizationId()
        );

        return $this->serializer->success($response);
    }

    #[Route('/{uuid}', name: 'patch', methods: ['PATCH'])]
    public function patch(
        string $objectType,
        string $uuid,
        #[MapRequestPayload] UpdatePatchRequest $request,
        #[CurrentUser] Identity $identity,
    ): JsonResponse {
        $scope = $identity->getProjectScope();
        $id = Uuid::fromString($uuid);

        // Authorize update action
        $this->authorizationService->authorizeExistingObjectAction(
            'update',
            $objectType,
            $id,
            $identity,
            $scope
        );

        $response = $this->repository->patch(
            $objectType,
            $id,
            $request,
            $scope->getProjectId(),
            $scope->getOrganizationId()
        );

        return $this->serializer->success($response);
    }

    #[Route('/{uuid}', name: 'put', methods: ['PUT'])]
    public function put(
        string $objectType,
        string $uuid,
        #[MapRequestPayload] UpdatePutRequest $request,
        #[CurrentUser] Identity $identity,
    ): JsonResponse {
        $scope = $identity->getProjectScope();
        $id = Uuid::fromString($uuid);

        $this->authorizationService->authorizeExistingObjectAction(
            'update',
            $objectType,
            $id,
            $identity,
            $scope
        );

        $response = $this->repository->put(
            $objectType,
            $id,
            $request,
            $scope->getProjectId(),
            $scope->getOrganizationId()
        );

        return $this->serializer->success($response);
    }

    #[Route('/{uuid}', name: 'delete', methods: ['DELETE'])]
    public function delete(
        string $objectType,
        string $uuid,
        #[CurrentUser] Identity $identity,
    ): Response {
        $scope = $identity->getProjectScope();
        $id = Uuid::fromString($uuid);

        $this->authorizationService->authorizeExistingObjectAction(
            'delete',
            $objectType,
            $id,
            $identity,
            $scope
        );

        $this->repository->softDelete(
            $objectType,
            $id,
            $scope->getProjectId(),
            $scope->getOrganizationId()
        );

        return new Response(null, Response::HTTP_NO_CONTENT, [
            'Content-Type' => 'application/vnd.api+json',
        ]);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        string $objectType,
        Request $request,
        #[CurrentUser] Identity $identity,
    ): JsonResponse {
        $scope = $identity->getProjectScope();
        $filters = $this->parseFilters($request);

        $response = $this->repository->listMetaObjects(
            $objectType,
            $filters,
            $scope->getProjectId(),
            $scope->getOrganizationId()
        );

        return $this->serializer->success($response);
    }

    #[Route('/revisions', name: 'list_revisions', methods: ['GET'])]
    public function listRevisions(
        string $objectType,
        Request $request,
        #[CurrentUser] Identity $identity,
    ): JsonResponse {
        $scope = $identity->getProjectScope();
        $filters = $this->parseFilters($request);

        $response = $this->repository->listMetaObjectRevisions(
            $objectType,
            $filters,
            $scope->getProjectId(),
            $scope->getOrganizationId()
        );

        return $this->serializer->success($response);
    }

    #[Route('/{uuid}/revisions/{revision}', name: 'get_revision', methods: ['GET'])]
    public function getRevision(
        string $objectType,
        string $uuid,
        int $revision,
        #[CurrentUser] Identity $identity,
    ): JsonResponse {
        $scope = $identity->getProjectScope();

        $response = $this->repository->getRevision(
            $objectType,
            Uuid::fromString($uuid),
            $revision,
            $scope->getProjectId(),
            $scope->getOrganizationId()
        );

        return $this->serializer->success($response);
    }

    #[Route('/{uuid}/revisions/{revision}', name: 'delete_revision', methods: ['DELETE'])]
    public function deleteRevision(
        string $objectType,
        string $uuid,
        int $revision,
        #[CurrentUser] Identity $identity,
    ): Response {
        $scope = $identity->getProjectScope();

        $this->repository->softDeleteRevision(
            $objectType,
            Uuid::fromString($uuid),
            $revision,
            $scope->getProjectId(),
            $scope->getOrganizationId()
        );

        return new Response(null, Response::HTTP_NO_CONTENT, [
            'Content-Type' => 'application/vnd.api+json',
        ]);
    }

    private function parseFilters(Request $request): array
    {
        // Parse filter query parameters similar to Go's filterparser
        return $request->query->all('filter');
    }
}
```

### 7.2 Schema Controller

```php
<?php
// src/Controller/Api/SchemaController.php

namespace App\Controller\Api;

use App\Repository\SchemaRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/schema', name: 'api_schema_')]
class SchemaController extends AbstractController
{
    public function __construct(
        private SchemaRepositoryInterface $schemaRepository,
    ) {
    }

    #[Route('/{objectType}', name: 'get_default', methods: ['GET'])]
    public function getDefault(string $objectType): JsonResponse
    {
        $schema = $this->schemaRepository->getByObjectType($objectType);

        return new JsonResponse([
            'objectType' => $schema->getObjectType(),
            'version' => $schema->getVersion(),
            'isDefault' => $schema->isDefault(),
            'schema' => $schema->getSchema(),
            'description' => $schema->getDescription(),
        ]);
    }

    #[Route('/{objectType}/{version}', name: 'get_version', methods: ['GET'])]
    public function getVersion(string $objectType, string $version): JsonResponse
    {
        $schema = $this->schemaRepository->getByObjectType($objectType, $version);

        return new JsonResponse([
            'objectType' => $schema->getObjectType(),
            'version' => $schema->getVersion(),
            'isDefault' => $schema->isDefault(),
            'schema' => $schema->getSchema(),
            'description' => $schema->getDescription(),
        ]);
    }
}
```

### 7.3 Health Check Controller

```php
<?php
// src/Controller/Api/HealthCheckController.php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthCheckController extends AbstractController
{
    #[Route('/health-check', name: 'health_check', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse([
            'service' => 'metastore',
            'version' => '1.0.0',
            'documentation' => '/api/v1/documentation/',
        ]);
    }
}
```

---

## 8. Authorization System Design

### 8.1 Authorization Types (Enums)

```php
<?php
// src/Authorization/Types.php

namespace App\Authorization;

enum Role: string
{
    case OrganizationAdmin = 'organization-admin';
    case ProjectAdmin = 'project-admin';
}

enum Action: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Batch = 'batch';
}

enum Scope: string
{
    case Any = '*';
    case Organization = 'organization';
    case Project = 'project';
}
```

### 8.2 Policy and Rule Classes

```php
<?php
// src/Authorization/Policy.php

namespace App\Authorization;

class Policy
{
    /**
     * @param Rule[] $create
     * @param Rule[] $update
     * @param Rule[] $delete
     */
    public function __construct(
        public readonly array $create = [],
        public readonly array $update = [],
        public readonly array $delete = [],
    ) {
    }

    public function getRulesFor(Action $action): array
    {
        return match ($action) {
            Action::Create => $this->create,
            Action::Update => $this->update,
            Action::Delete => $this->delete,
            default => [],
        };
    }

    public function validate(): void
    {
        foreach ([Action::Create, Action::Update, Action::Delete] as $action) {
            $rules = $this->getRulesFor($action);
            if (empty($rules)) {
                throw new \InvalidArgumentException(
                    sprintf('Action "%s" must have at least one rule', $action->value)
                );
            }
            foreach ($rules as $i => $rule) {
                $rule->validate($action, $i);
            }
        }
    }
}
```

```php
<?php
// src/Authorization/Rule.php

namespace App\Authorization;

class Rule
{
    /**
     * @param Role[] $roles
     * @param Scope[] $scopes
     */
    public function __construct(
        public readonly array $roles,
        public readonly array $scopes,
        public readonly ?string $when = null,
    ) {
    }

    public function validate(Action $action, int $index): void
    {
        if (empty($this->roles)) {
            throw new \InvalidArgumentException(
                sprintf('Rule %d for action "%s": at least one role must be defined', $index, $action->value)
            );
        }

        if (empty($this->scopes)) {
            throw new \InvalidArgumentException(
                sprintf('Rule %d for action "%s": at least one scope must be defined', $index, $action->value)
            );
        }
    }
}
```

### 8.3 Authorization Service

```php
<?php
// src/Authorization/AuthorizationService.php

namespace App\Authorization;

use App\Repository\MetaObjectRepositoryInterface;
use App\Repository\SchemaRepositoryInterface;
use App\Security\Identity;
use App\Security\ProjectScope;
use App\Validation\AclParser;
use Symfony\Component\Uid\Uuid;

class AuthorizationService
{
    public function __construct(
        private SchemaRepositoryInterface $schemaRepository,
        private MetaObjectRepositoryInterface $metaObjectRepository,
        private PolicyEvaluator $evaluator,
        private AclParser $aclParser,
    ) {
    }

    public function authorizeCreate(
        string $objectType,
        string $schemaVersion,
        Identity $identity,
        ProjectScope $scope,
        array $data,
        string $requestedScope = ''
    ): void {
        $schema = $this->schemaRepository->getByObjectType($objectType, $schemaVersion);
        $policy = $this->aclParser->parse($schema->getSchema());

        if (empty($policy->create)) {
            return; // ACL not configured, allow by default
        }

        $request = new AuthorizationRequest(
            action: Action::Create,
            identity: $identity,
            objectContext: new ObjectContext(
                objectType: $objectType,
                projectId: (string) $scope->getProjectId(),
                organizationId: $scope->getOrganizationId(),
            ),
            hint: new ScopeHint(
                isProjectScoped: $requestedScope === 'project' || $requestedScope === '',
                isOrgScoped: $requestedScope === 'organization',
            ),
            policy: $policy,
        );

        $this->evaluator->evaluate($request);
    }

    public function authorizeExistingObjectAction(
        string $action,
        string $objectType,
        Uuid $id,
        Identity $identity,
        ProjectScope $scope
    ): void {
        $metaObject = $this->metaObjectRepository->getByUuid(
            $objectType,
            $id,
            $scope->getProjectId(),
            $scope->getOrganizationId()
        );

        $schema = $this->schemaRepository->getByObjectType($objectType, $metaObject->schemaVersion);
        $policy = $this->aclParser->parse($schema->getSchema());

        $actionEnum = Action::from($action);
        $rules = $policy->getRulesFor($actionEnum);

        if (empty($rules)) {
            return; // ACL not configured, allow by default
        }

        $request = new AuthorizationRequest(
            action: $actionEnum,
            identity: $identity,
            objectContext: new ObjectContext(
                objectType: $objectType,
                projectId: $metaObject->projectId !== null ? (string) $metaObject->projectId : '',
                organizationId: $metaObject->organizationId,
            ),
            hint: new ScopeHint(
                isProjectScoped: $metaObject->projectId !== null,
                isOrgScoped: true,
            ),
            policy: $policy,
        );

        $this->evaluator->evaluate($request);
    }
}
```

### 8.4 Policy Evaluator

```php
<?php
// src/Authorization/PolicyEvaluator.php

namespace App\Authorization;

use App\Exception\ForbiddenException;

class PolicyEvaluator
{
    public function evaluate(AuthorizationRequest $request): void
    {
        $request->policy->validate();

        $rules = $request->policy->getRulesFor($request->action);
        if (empty($rules)) {
            throw new ForbiddenException('Action not configured');
        }

        $identityRoles = $request->identity->getRoles();
        $hint = $this->normalizeHint($request->objectContext, $request->hint);

        foreach ($rules as $rule) {
            // Check if identity has any of the required roles
            if (!$this->hasAnyRole($identityRoles, $rule->roles)) {
                continue;
            }

            // Check scope match
            if (!$this->scopeMatches($rule, $request->objectContext, $hint)) {
                continue;
            }

            // Check when condition
            if ($rule->when !== null) {
                if ($rule->when === 'false') {
                    continue;
                }
                if ($rule->when === "object.ProjectID != ''" && $request->objectContext->projectId === '') {
                    continue;
                }
            }

            // Rule matched, authorization passes
            return;
        }

        throw new ForbiddenException('No matching authorization rule');
    }

    private function hasAnyRole(array $identityRoles, array $requiredRoles): bool
    {
        foreach ($requiredRoles as $required) {
            if (in_array($required, $identityRoles, true)) {
                return true;
            }
        }
        return false;
    }

    private function scopeMatches(Rule $rule, ObjectContext $object, ScopeHint $hint): bool
    {
        foreach ($rule->scopes as $scope) {
            if ($scope === Scope::Any) {
                return true;
            }
            if ($scope === Scope::Organization && $hint->isOrgScoped) {
                return true;
            }
            if ($scope === Scope::Project && $hint->isProjectScoped) {
                return true;
            }
        }
        return false;
    }

    private function normalizeHint(ObjectContext $object, ScopeHint $hint): ScopeHint
    {
        return new ScopeHint(
            isProjectScoped: $hint->isProjectScoped || $object->projectId !== '',
            isOrgScoped: $hint->isOrgScoped || $object->organizationId !== '',
        );
    }
}
```

### 8.5 Supporting Classes

```php
<?php
// src/Authorization/AuthorizationRequest.php

namespace App\Authorization;

use App\Security\Identity;

class AuthorizationRequest
{
    public function __construct(
        public readonly Action $action,
        public readonly Identity $identity,
        public readonly ObjectContext $objectContext,
        public readonly ScopeHint $hint,
        public readonly Policy $policy,
    ) {
    }
}
```

```php
<?php
// src/Authorization/ObjectContext.php

namespace App\Authorization;

class ObjectContext
{
    public function __construct(
        public readonly string $objectType,
        public readonly string $projectId,
        public readonly string $organizationId,
    ) {
    }
}
```

```php
<?php
// src/Authorization/ScopeHint.php

namespace App\Authorization;

class ScopeHint
{
    public function __construct(
        public readonly bool $isProjectScoped,
        public readonly bool $isOrgScoped,
    ) {
    }
}
```

---

## 9. JSON Schema Validation

### 9.1 JSON Schema Validator

```php
<?php
// src/Validation/JsonSchemaValidator.php

namespace App\Validation;

use App\Exception\ValidationException;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;

class JsonSchemaValidator
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
        $this->validator->setMaxErrors(10);
    }

    public function validate(array $schema, array $data): void
    {
        $schemaObject = json_decode(json_encode($schema));
        $dataObject = json_decode(json_encode($data));

        $result = $this->validator->validate($dataObject, $schemaObject);

        if (!$result->isValid()) {
            $errors = $this->collectErrors($result->error());
            throw new ValidationException('Validation failed', $errors);
        }
    }

    public function validatePartial(array $schema, array $patchData): void
    {
        // Create a partial schema with only the properties being patched
        $properties = $schema['properties'] ?? [];
        $partialSchema = [
            '$schema' => $schema['$schema'] ?? 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'properties' => [],
        ];

        foreach (array_keys($patchData) as $field) {
            if (isset($properties[$field])) {
                $partialSchema['properties'][$field] = $properties[$field];
            }
        }

        $this->validate($partialSchema, $patchData);
    }

    private function collectErrors(?ValidationError $error): array
    {
        if ($error === null) {
            return [];
        }

        $errors = [];

        foreach ($error->subErrors() as $subError) {
            $errors = array_merge($errors, $this->collectErrors($subError));
        }

        if (empty($errors)) {
            $errors[] = [
                'path' => implode('.', $error->data()->fullPath()),
                'message' => $error->message(),
                'code' => 'validation_error',
            ];
        }

        return $errors;
    }
}
```

### 9.2 ACL Parser

```php
<?php
// src/Validation/AclParser.php

namespace App\Validation;

use App\Authorization\Policy;
use App\Authorization\Role;
use App\Authorization\Rule;
use App\Authorization\Scope;

class AclParser
{
    private const ACL_EXTENSION_KEY = 'x-metastore.acl';
    private const ACL_NESTED_KEY = 'x-metastore';

    public function parse(array $schemaData): Policy
    {
        $aclData = $schemaData[self::ACL_EXTENSION_KEY] ?? null;

        if ($aclData === null && isset($schemaData[self::ACL_NESTED_KEY]['acl'])) {
            $aclData = $schemaData[self::ACL_NESTED_KEY]['acl'];
        }

        if ($aclData === null || empty($aclData)) {
            return new Policy();
        }

        return new Policy(
            create: $this->parseRules($aclData['create'] ?? []),
            update: $this->parseRules($aclData['update'] ?? []),
            delete: $this->parseRules($aclData['delete'] ?? []),
        );
    }

    private function parseRules(array $rawRules): array
    {
        $rules = [];

        foreach ($rawRules as $rawRule) {
            $roles = $this->parseRoles($rawRule);
            $scopes = $this->parseScopes($rawRule);

            $rules[] = new Rule(
                roles: $roles,
                scopes: $scopes,
                when: $rawRule['when'] ?? null,
            );
        }

        return $rules;
    }

    private function parseRoles(array $rawRule): array
    {
        $roles = [];

        if (isset($rawRule['roles'])) {
            foreach ($rawRule['roles'] as $role) {
                $roles[] = Role::from($role);
            }
        }

        if (isset($rawRule['role'])) {
            $role = Role::from($rawRule['role']);
            if (!in_array($role, $roles, true)) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    private function parseScopes(array $rawRule): array
    {
        $scopes = [];

        if (isset($rawRule['scopes'])) {
            foreach ($rawRule['scopes'] as $scope) {
                $scopes[] = Scope::from($scope);
            }
        }

        if (isset($rawRule['scope'])) {
            $scope = Scope::from($rawRule['scope']);
            if (!in_array($scope, $scopes, true)) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }
}
```

---

## 10. JSON:API Response Formatting

### 10.1 JSON:API Serializer

```php
<?php
// src/Response/JsonApiSerializer.php

namespace App\Response;

use App\DTO\MetaObjectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

class JsonApiSerializer
{
    private const CONTENT_TYPE = 'application/vnd.api+json';
    private const PREFIX = '/api/v1/repository';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function success(MetaObjectResponse|array $data, int $statusCode = 200): JsonResponse
    {
        $baseUrl = $this->getBaseUrl();
        $payload = $this->serialize($data, $baseUrl);

        return new JsonResponse($payload, $statusCode, [
            'Content-Type' => self::CONTENT_TYPE,
        ]);
    }

    public function created(MetaObjectResponse $data): JsonResponse
    {
        return $this->success($data, 201);
    }

    private function serialize(MetaObjectResponse|array $data, string $baseUrl): array
    {
        if (is_array($data)) {
            return [
                'data' => array_map(fn ($item) => $this->serializeOne($item, $baseUrl), $data),
            ];
        }

        return [
            'data' => $this->serializeOne($data, $baseUrl),
        ];
    }

    private function serializeOne(MetaObjectResponse $response, string $baseUrl): array
    {
        $selfUrl = sprintf(
            '%s%s/%s/%s',
            $baseUrl,
            self::PREFIX,
            $response->objectType,
            $response->uuid
        );

        $data = [
            'type' => $response->objectType,
            'id' => (string) $response->uuid,
            'attributes' => [
                'schemaVersion' => $response->schemaVersion,
                'branch' => $response->branch,
                'name' => $response->name,
                'projectId' => $response->projectId,
                'organizationId' => $response->organizationId,
                'lastUpdated' => $response->lastUpdated->format(\DateTimeInterface::RFC3339),
                'createdAt' => $response->createdAt->format(\DateTimeInterface::RFC3339),
                'revision' => $response->revision,
                'data' => $response->data,
                'revisionCreatedAt' => $response->revisionCreatedAt->format(\DateTimeInterface::RFC3339),
            ],
            'links' => [
                'self' => $selfUrl,
            ],
            'relationships' => [
                'schema' => [
                    'data' => [
                        'type' => 'schemas',
                        'id' => sprintf('%s-%s', $response->objectType, $response->schemaVersion),
                    ],
                ],
                'revisions' => [
                    'data' => [
                        'type' => 'revisions',
                        'id' => (string) $response->revision,
                    ],
                ],
            ],
        ];

        if ($response->projectId !== null) {
            $data['relationships']['project'] = [
                'data' => [
                    'type' => 'projects',
                    'id' => (string) $response->projectId,
                ],
            ];
        }

        return $data;
    }

    private function getBaseUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return '';
        }

        return sprintf(
            '%s://%s',
            $request->getScheme(),
            $request->getHttpHost()
        );
    }
}
```

---

## 11. Authentication

### 11.1 Storage API Authenticator

```php
<?php
// src/Security/StorageApiAuthenticator.php

namespace App\Security;

use App\Exception\UnauthorizedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class StorageApiAuthenticator extends AbstractAuthenticator
{
    private const TOKEN_HEADER = 'X-StorageAPI-Token';

    public function __construct(
        private TokenValidator $tokenValidator,
        private IdentityFactory $identityFactory,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Only authenticate routes under /api/v1/repository
        return str_starts_with($request->getPathInfo(), '/api/v1/repository');
    }

    public function authenticate(Request $request): Passport
    {
        $token = $request->headers->get(self::TOKEN_HEADER);

        if (empty($token)) {
            throw new UnauthorizedException('Authentication failed: missing token');
        }

        // Validate token with Storage API
        $storageApiToken = $this->tokenValidator->validate($token);

        if ($storageApiToken->isDisabled) {
            throw new UnauthorizedException('Token is disabled');
        }

        if ($storageApiToken->isExpired) {
            throw new UnauthorizedException('Token is expired');
        }

        if (empty($storageApiToken->organizationId)) {
            throw new UnauthorizedException('Organization scope required');
        }

        // Create identity from validated token
        $identity = $this->identityFactory->create($storageApiToken);

        return new SelfValidatingPassport(
            new UserBadge($token, fn () => $identity)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => 401,
            'code' => '401',
            'exception' => $exception->getMessage(),
            'exceptionId' => $this->generateExceptionId(),
            'status' => 'error',
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function generateExceptionId(): string
    {
        return 'metastore-' . bin2hex(random_bytes(8));
    }
}
```

### 11.2 Token Validator

```php
<?php
// src/Security/TokenValidator.php

namespace App\Security;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TokenValidator
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $storageApiHost,
    ) {
    }

    public function validate(string $token): StorageApiToken
    {
        $response = $this->httpClient->request(
            'GET',
            "https://{$this->storageApiHost}/v2/storage/tokens/verify",
            [
                'headers' => [
                    'X-StorageApi-Token' => $token,
                ],
            ]
        );

        $data = $response->toArray();

        return new StorageApiToken(
            id: $data['id'],
            projectId: $data['owner']['id'],
            organizationId: $data['owner']['features']['organization-id'] ?? '',
            isDisabled: $data['isDisabled'] ?? false,
            isExpired: $data['isExpired'] ?? false,
            isMasterToken: $data['isMasterToken'] ?? false,
            admin: isset($data['admin']) ? new TokenAdmin(
                isOrganizationMember: $data['admin']['isOrganizationMember'] ?? false,
            ) : null,
        );
    }
}
```

### 11.3 Identity and Project Scope

```php
<?php
// src/Security/Identity.php

namespace App\Security;

use App\Authorization\Role;
use Symfony\Component\Security\Core\User\UserInterface;

class Identity implements UserInterface
{
    /**
     * @param Role[] $roles
     */
    public function __construct(
        private array $roles,
        private ProjectScope $projectScope,
    ) {
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function hasRole(Role $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function getProjectScope(): ProjectScope
    {
        return $this->projectScope;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->projectScope->getProjectId();
    }
}
```

```php
<?php
// src/Security/ProjectScope.php

namespace App\Security;

class ProjectScope
{
    public function __construct(
        private int $projectId,
        private string $organizationId,
    ) {
    }

    public function getProjectId(): int
    {
        return $this->projectId;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }
}
```

---

## 12. Logging and Telemetry

### 12.1 Monolog Configuration

```yaml
# config/packages/monolog.yaml
monolog:
    channels:
        - request
        - database
        - authorization

    handlers:
        main:
            type: stream
            path: "php://stdout"
            level: debug
            formatter: App\Logging\JsonFormatter

        request:
            type: stream
            path: "php://stdout"
            level: info
            channels: [request]
            formatter: App\Logging\JsonFormatter
```

### 12.2 JSON Formatter

```php
<?php
// src/Logging/JsonFormatter.php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter as BaseJsonFormatter;
use Monolog\LogRecord;

class JsonFormatter extends BaseJsonFormatter
{
    public function format(LogRecord $record): string
    {
        $normalized = [
            'level' => strtolower($record->level->getName()),
            'ts' => $record->datetime->format('Y-m-d\TH:i:s.uP'),
            'msg' => $record->message,
            'service' => 'metastore',
        ];

        // Add context fields
        foreach ($record->context as $key => $value) {
            $normalized[$key] = $value;
        }

        // Add extra fields
        foreach ($record->extra as $key => $value) {
            $normalized[$key] = $value;
        }

        return $this->toJson($normalized) . "\n";
    }
}
```

### 12.3 OpenTelemetry Integration

```php
<?php
// src/Telemetry/TelemetryService.php

namespace App\Telemetry;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;

class TelemetryService
{
    public function __construct(
        private TracerInterface $tracer,
    ) {
    }

    public function startSpan(string $name, array $attributes = []): void
    {
        $span = $this->tracer->spanBuilder($name)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        foreach ($attributes as $key => $value) {
            $span->setAttribute($key, $value);
        }

        Context::getCurrent()->withContextValue($span)->activate();
    }

    public function endSpan(): void
    {
        $span = \OpenTelemetry\API\Trace\Span::getCurrent();
        $span->end();
    }

    public function addAttribute(string $key, mixed $value): void
    {
        $span = \OpenTelemetry\API\Trace\Span::getCurrent();
        $span->setAttribute($key, $value);
    }
}
```

---

## 13. Configuration Management

### 13.1 Environment Variables

```dotenv
# .env
###> app/metastore ###
METASTORE_STORAGE_API_HOST=connection.keboola.com
METASTORE_DEBUG_LOG=false
METASTORE_API_LISTEN_ADDRESS=0.0.0.0:8080

# Database
DATABASE_URL="postgresql://user:password@localhost:5432/metastore?serverVersion=14"

# Datadog
DD_TRACE_ENABLED=true
DD_TRACE_DEBUG=false
DD_SERVICE=metastore
DD_ENV=production

# Metrics
METASTORE_METRICS_ENABLED=true
METASTORE_METRICS_PORT=9090
###< app/metastore ###
```

### 13.2 Configuration Class

```php
<?php
// src/Config/MetastoreConfig.php

namespace App\Config;

class MetastoreConfig
{
    public function __construct(
        public readonly string $storageApiHost,
        public readonly bool $debugLog,
        public readonly string $listenAddress,
        public readonly DatabaseConfig $database,
        public readonly DatadogConfig $datadog,
        public readonly MetricsConfig $metrics,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            storageApiHost: $_ENV['METASTORE_STORAGE_API_HOST'] ?? 'connection.keboola.com',
            debugLog: filter_var($_ENV['METASTORE_DEBUG_LOG'] ?? false, FILTER_VALIDATE_BOOLEAN),
            listenAddress: $_ENV['METASTORE_API_LISTEN_ADDRESS'] ?? '0.0.0.0:8080',
            database: DatabaseConfig::fromEnv(),
            datadog: DatadogConfig::fromEnv(),
            metrics: MetricsConfig::fromEnv(),
        );
    }
}
```

---

## 14. Testing Strategy

### 14.1 Test Directory Structure

```
tests/
├── Unit/
│   ├── Authorization/
│   │   ├── PolicyEvaluatorTest.php
│   │   ├── AclParserTest.php
│   │   └── RoleMapperTest.php
│   ├── Validation/
│   │   └── JsonSchemaValidatorTest.php
│   ├── Repository/
│   │   └── SchemaRepositoryTest.php
│   └── Response/
│       └── JsonApiSerializerTest.php
├── Integration/
│   ├── Repository/
│   │   ├── MetaObjectRepositoryTest.php
│   │   └── SchemaRepositoryTest.php
│   └── Security/
│       └── TokenValidatorTest.php
├── Functional/
│   ├── Controller/
│   │   ├── RepositoryControllerTest.php
│   │   ├── SchemaControllerTest.php
│   │   └── HealthCheckControllerTest.php
│   └── Api/
│       └── scenarios/
│           ├── create-meta-object/
│           ├── update-meta-object/
│           └── delete-meta-object/
└── fixtures/
    ├── schemas/
    │   └── tag-1.0.0.json
    └── tokens/
        └── valid-token.json
```

### 14.2 Unit Test Example

```php
<?php
// tests/Unit/Authorization/PolicyEvaluatorTest.php

namespace App\Tests\Unit\Authorization;

use App\Authorization\Action;
use App\Authorization\AuthorizationRequest;
use App\Authorization\ObjectContext;
use App\Authorization\Policy;
use App\Authorization\PolicyEvaluator;
use App\Authorization\Role;
use App\Authorization\Rule;
use App\Authorization\Scope;
use App\Authorization\ScopeHint;
use App\Exception\ForbiddenException;
use App\Security\Identity;
use App\Security\ProjectScope;
use PHPUnit\Framework\TestCase;

class PolicyEvaluatorTest extends TestCase
{
    private PolicyEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new PolicyEvaluator();
    }

    public function testAllowsWhenRoleAndScopeMatch(): void
    {
        $policy = new Policy(
            create: [
                new Rule(
                    roles: [Role::ProjectAdmin],
                    scopes: [Scope::Project],
                ),
            ],
            update: [
                new Rule(
                    roles: [Role::ProjectAdmin],
                    scopes: [Scope::Project],
                ),
            ],
            delete: [
                new Rule(
                    roles: [Role::ProjectAdmin],
                    scopes: [Scope::Project],
                ),
            ],
        );

        $identity = new Identity(
            roles: [Role::ProjectAdmin],
            projectScope: new ProjectScope(123, 'org-456'),
        );

        $request = new AuthorizationRequest(
            action: Action::Create,
            identity: $identity,
            objectContext: new ObjectContext('tag', '123', 'org-456'),
            hint: new ScopeHint(isProjectScoped: true, isOrgScoped: false),
            policy: $policy,
        );

        // Should not throw
        $this->evaluator->evaluate($request);
        $this->assertTrue(true);
    }

    public function testDeniesWhenNoMatchingRule(): void
    {
        $policy = new Policy(
            create: [
                new Rule(
                    roles: [Role::OrganizationAdmin],
                    scopes: [Scope::Organization],
                ),
            ],
            update: [
                new Rule(
                    roles: [Role::OrganizationAdmin],
                    scopes: [Scope::Organization],
                ),
            ],
            delete: [
                new Rule(
                    roles: [Role::OrganizationAdmin],
                    scopes: [Scope::Organization],
                ),
            ],
        );

        $identity = new Identity(
            roles: [Role::ProjectAdmin],
            projectScope: new ProjectScope(123, 'org-456'),
        );

        $request = new AuthorizationRequest(
            action: Action::Create,
            identity: $identity,
            objectContext: new ObjectContext('tag', '123', 'org-456'),
            hint: new ScopeHint(isProjectScoped: true, isOrgScoped: false),
            policy: $policy,
        );

        $this->expectException(ForbiddenException::class);
        $this->evaluator->evaluate($request);
    }
}
```

### 14.3 Functional Test Example

```php
<?php
// tests/Functional/Controller/RepositoryControllerTest.php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class RepositoryControllerTest extends WebTestCase
{
    public function testCreateMetaObject(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/v1/repository/tag',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/vnd.api+json',
                'HTTP_X_STORAGEAPI_TOKEN' => 'test-token',
            ],
            json_encode([
                'schemaVersion' => '1.0.0',
                'name' => 'Test Tag',
                'data' => [
                    'key' => 'value',
                ],
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('tag', $response['data']['type']);
        $this->assertEquals('Test Tag', $response['data']['attributes']['name']);
    }

    public function testUnauthorizedWithoutToken(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/repository/tag');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
```

---

## 15. Database Compatibility

### 15.1 Database Compatibility Requirements

The PHP implementation MUST be fully compatible with the existing PostgreSQL schema created by the Go service:

1. **Table Names**: Use exact same table names (`schemas`, `meta_objects`, `meta_object_revisions`)
2. **Column Types**: Match PostgreSQL types exactly (UUID, JSONB, TIMESTAMPTZ)
3. **Constraints**: Maintain all unique constraints and indexes
4. **Triggers**: The `ensure_single_default_schema` trigger must continue to function

### 15.2 Migration Checklist

- [ ] Verify database connection uses same credentials format
- [ ] Test against existing Go-populated database
- [ ] Validate all queries produce identical results
- [ ] Confirm soft delete behavior matches (using `deleted_at` timestamps)
- [ ] Test revision numbering consistency
- [ ] Verify UUID v7 generation compatibility
- [ ] Test JSONB storage and retrieval matches Go behavior

### 15.3 Database Verification Command

```php
<?php
// src/Command/VerifyDatabaseCompatibilityCommand.php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'metastore:verify-db-compatibility')]
class VerifyDatabaseCompatibilityCommand extends Command
{
    public function __construct(private Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Verifying database compatibility...');

        // Check table structure
        $this->verifyTableStructure($output, 'schemas');
        $this->verifyTableStructure($output, 'meta_objects');
        $this->verifyTableStructure($output, 'meta_object_revisions');

        // Check triggers
        $this->verifyTrigger($output, 'enforce_single_default_schema');

        // Check indexes
        $this->verifyIndexes($output);

        $output->writeln('<info>Database compatibility verified!</info>');
        return Command::SUCCESS;
    }

    private function verifyTableStructure(OutputInterface $output, string $table): void
    {
        $columns = $this->connection->createSchemaManager()->listTableColumns($table);
        $output->writeln(sprintf('Table %s has %d columns', $table, count($columns)));
    }

    private function verifyTrigger(OutputInterface $output, string $triggerName): void
    {
        $sql = 'SELECT tgname FROM pg_trigger WHERE tgname = :name';
        $result = $this->connection->fetchOne($sql, ['name' => $triggerName]);

        if ($result) {
            $output->writeln(sprintf('<info>Trigger %s exists</info>', $triggerName));
        } else {
            $output->writeln(sprintf('<error>Trigger %s NOT FOUND</error>', $triggerName));
        }
    }

    private function verifyIndexes(OutputInterface $output): void
    {
        $expectedIndexes = [
            'idx_schemas_object_type',
            'idx_schemas_is_default',
            'idx_meta_objects_project_id',
            'idx_meta_objects_type_project',
            'idx_meta_objects_org_project_type',
            'idx_meta_object_revisions_uuid_revision',
        ];

        foreach ($expectedIndexes as $indexName) {
            $sql = 'SELECT indexname FROM pg_indexes WHERE indexname = :name';
            $result = $this->connection->fetchOne($sql, ['name' => $indexName]);

            if ($result) {
                $output->writeln(sprintf('<info>Index %s exists</info>', $indexName));
            } else {
                $output->writeln(sprintf('<error>Index %s NOT FOUND</error>', $indexName));
            }
        }
    }
}
```

---

## 16. Dependencies

### 16.1 Required Composer Packages

```json
{
    "require": {
        "php": ">=8.2",
        "symfony/framework-bundle": "^7.0",
        "symfony/http-client": "^7.0",
        "symfony/security-bundle": "^7.0",
        "symfony/validator": "^7.0",
        "symfony/uid": "^7.0",
        "doctrine/doctrine-bundle": "^2.11",
        "doctrine/orm": "^3.0",
        "opis/json-schema": "^2.3",
        "open-telemetry/sdk": "^1.0",
        "monolog/monolog": "^3.5"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5",
        "symfony/test-pack": "^1.0",
        "phpstan/phpstan": "^1.10",
        "friendsofphp/php-cs-fixer": "^3.0"
    }
}
```

---

## 17. Risks and Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| JSON Schema library differences | Medium | Extensive validation testing with Go service schemas |
| PostgreSQL driver behavior differences | High | Direct SQL queries matching Go implementation exactly |
| Authentication token format changes | Medium | Abstract token validation behind interface |
| Performance regression | Medium | Load testing and query optimization |
| JSONB handling inconsistencies | High | Custom Doctrine type with exact Go behavior matching |

---

## 18. Critical Files for Reference

When implementing, refer to these Go source files:

1. **`services/metastore/internal/repository/meta_object_repository.go`** - Core repository logic including transaction handling, scope resolution, and data merging
2. **`services/metastore/internal/authz/evaluator.go`** - Authorization evaluation logic and policy matching
3. **`services/metastore/api/handlers/repository.go`** - Controller patterns and request/response handling
4. **`services/metastore/internal/jsonschema/validator.go`** - JSON Schema validation logic
5. **`services/metastore/internal/jsonschema/acl.go`** - ACL parsing from schema extensions
6. **`services/metastore/migrations/*.sql`** - Database schema definitions

---

## 19. API Endpoints Summary

### Public Endpoints (No Authentication)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/` | Service index |
| GET | `/health-check` | Health status |
| GET | `/api/v1/documentation/*` | Swagger UI |
| GET | `/api/v1/schema/{objectType}` | Get default schema |
| GET | `/api/v1/schema/{objectType}/{version}` | Get specific schema version |

### Authenticated Endpoints (Storage API Token Required)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/v1/repository/{objectType}` | Create metadata object |
| GET | `/api/v1/repository/{objectType}` | List metadata objects |
| GET | `/api/v1/repository/{objectType}/{UUID}` | Get specific object |
| PATCH | `/api/v1/repository/{objectType}/{UUID}` | Partial update |
| PUT | `/api/v1/repository/{objectType}/{UUID}` | Full replacement |
| DELETE | `/api/v1/repository/{objectType}/{UUID}` | Soft delete object |
| GET | `/api/v1/repository/{objectType}/revisions` | List all revisions |
| GET | `/api/v1/repository/{objectType}/{UUID}/revisions/{revision}` | Get specific revision |
| DELETE | `/api/v1/repository/{objectType}/{UUID}/revisions/{revision}` | Soft delete revision |

---

## 20. Verification

After implementation, the PHP service should:

1. Pass all existing Go E2E tests against the PHP endpoints
2. Maintain full database compatibility (can read/write data created by Go service)
3. Return identical JSON:API responses
4. Support all existing authentication tokens
5. Implement identical authorization rules

Run verification with:

```bash
# Run PHP tests
./vendor/bin/phpunit

# Verify database compatibility
php bin/console metastore:verify-db-compatibility

# Run linting
./vendor/bin/php-cs-fixer fix --dry-run
./vendor/bin/phpstan analyse
```
