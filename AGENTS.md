# BareAPI

BareAPI is a Symfony-based backend that automatically exposes dynamic CRUD endpoints for arbitrary object types defined by JSON Schema files (under `config/schemas/`). Instead of hand-crafting Doctrine entities, repositories, and controllers per type, BareAPI uses a single generic entity and runtime schema validation to handle all types uniformly.

## Key Concepts

### JSON Schemas (`config/schemas/*.json`)
Each file `config/schemas/{type}.json` describes the structure and validation rules for objects of that type (e.g. `note.json`, `task.json`). When a request arrives, BareAPI loads the corresponding schema and applies validation.

### Single generic Doctrine entity: `MetaObject`
All objects are stored in one table `meta_objects` with the following columns:

| Column           | Type        | Description                                           |
| ---------------- | ----------- | ----------------------------------------------------- |
| `id`             | UUID (PK)   | Unique identifier                                     |
| `type`           | VARCHAR     | The schema type (filename without `.json`)            |
| `schema_version` | VARCHAR     | Version from the JSON Schema (or default `'1.0'`)     |
| `data`           | JSONB       | The raw object payload, validated against the schema  |
| `created_at`     | TIMESTAMP   | Creation timestamp                                    |
| `updated_at`     | TIMESTAMP   | Last update timestamp                                 |

The entity is defined under `src/Bareapi/Entity/MetaObject.php` using Doctrine annotations.

### Generic Repository
`src/Bareapi/Repository/MetaObjectRepository.php` is a service wrapping `EntityManagerInterface`. It provides methods to:

- `find($id)` — load a single object by UUID  
- `findAllByType($type)` — list all objects of a given type  
- `findByTypeAndFilters($type, $filters)` — basic JSONB filtering via query parameters  
- `save(MetaObject $obj)` — insert or update  
- `delete(MetaObject $obj)` — remove

Filtering uses PostgreSQL JSONB operators via Doctrine QueryBuilder (`m.data->> :field = :value`).

### Invokable Controllers
Each CRUD operation is handled by one single-action controller (`__invoke`):

- `DataListController` — `GET /data/{type}` (with optional `?field=value` filters)  
- `DataCreateController` — `POST /data/{type}`  
- `DataShowController` — `GET /data/{type}/{id}`  
- `DataUpdateController` — `PUT /data/{type}/{id}`  
- `DataDeleteController` — `DELETE /data/{type}/{id}`  

Controllers live under `src/Bareapi/Controller` and leverage the generic repository and JSON Schema validation (via `justinrainbow/json-schema` and Symfony Validator).

### Dynamic Routing
Routes are declared once in `config/routes/data.yaml` with wildcards:

```yaml
data_collection:
    path: /data/{type}
    controller: Bareapi\Controller\DataListController
    methods: [GET]

data_create:
    path: /data/{type}
    controller: Bareapi\Controller\DataCreateController
    methods: [POST]

# ... and similarly for show/update/delete
```

Symfony imports these routes at runtime, so adding a new schema file immediately makes the endpoints available.

### Validation Pipeline
`DataCreateController` and `DataUpdateController` load the JSON Schema, then call:

```php
$validator->validate($payload, $schema, Constraint::CHECK_MODE_APPLY_DEFAULTS);
if (!$validator->isValid()) { /* return 422 with errors */ }
```

This ensures that only schema-compliant data is persisted.

### Namespaces & Autoloading
All PHP code now lives under the `Bareapi\` namespace. Composer PSR-4 is configured in `composer.json`:

```json
"autoload": {
  "psr-4": {
    "Bareapi\\": "src/Bareapi/"
  }
}
```

Service auto-wiring is tuned in `config/services.yaml` to discover entities, repositories, and controllers under `Bareapi\`.

### Entry Points
- `public/index.php` and `bin/console` bootstrap `Bareapi\Kernel` instead of `App\Kernel`.

## How to Add a New Object Type

1. Drop your JSON Schema to `config/schemas/{newtype}.json`.
2. (Optionally) define `version` in your schema.
3. POST/GET/PUT/DELETE to `/data/{newtype}` and `/data/{newtype}/{id}` — no code changes required.

## Dependencies
- PHP 8.3+  
- Symfony Framework + Flex  
- Doctrine ORM + Migrations + DBAL (PostgreSQL)  
- symfony/validator, justinrainbow/json-schema  
- ramsey/uuid

---  
**BareAPI** delivers a fully schema-driven CRUD API platform: add schemas, get REST endpoints, no boilerplate per type.

## Running commands

Any application commands (e.g. Composer, Symfony console, migrations) should be run inside the PHP "app" container. For example:

```bash
docker compose run --rm app composer install
docker compose run --rm app bin/console doctrine:migrations:migrate
docker compose run --rm app bin/console cache:clear
```

Other tool commands (Docker Compose itself, git, host‑side utilities) can be run directly on the host.

### PHPUnit

Install the Symfony PHPUnit Bridge (along with PHPUnit and helpers) in the app container:
```bash
docker compose run --rm app composer require --dev symfony/test-pack --no-interaction
```

Run the test suite via either the composer helper or directly:
```bash
composer test
# or:
docker compose run --rm app php bin/phpunit
```

New functional tests for REST controllers live in `tests/Controller/DataFunctionalTest.php`, covering create, fetch, update and delete flows against an in-memory SQLite schema.

Tests also require the testing framework enabled. Ensure you have:
```yaml
# config/packages/test/framework.yaml
framework:
  test: true
  session:
    storage_factory_id: session.storage.factory.mock_file
```