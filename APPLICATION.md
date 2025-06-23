# BareAPI

BareAPI is a Symfony-based backend that automatically exposes dynamic CRUD endpoints for arbitrary object types defined by JSON Schema files (located in `config/schemas/`). Rather than manually creating Doctrine entities, repositories, and controllers for each type, BareAPI leverages a single generic entity and runtime schema validation to handle all types uniformly and efficiently.

## Key Concepts

### JSON Schemas (`config/schemas/*.json`)

Each file in `config/schemas/{type}.json` defines the structure and validation rules for objects of that type (e.g., `note.json`, `task.json`). Upon receiving a request, BareAPI loads the relevant schema and applies validation to the incoming data.

### Single Generic Doctrine Entity: `MetaObject`

All objects are persisted in a single table, `meta_objects`, with the following columns:

| Column           | Type        | Description                                           |
| ---------------- | ----------- | ----------------------------------------------------- |
| `id`             | UUID (PK)   | Unique identifier                                     |
| `type`           | VARCHAR     | The schema type (filename without `.json`)            |
| `schema_version` | VARCHAR     | Version from the JSON Schema (or default `'1.0'`)     |
| `data`           | JSONB       | The raw object payload, validated against the schema  |
| `created_at`     | TIMESTAMP   | Creation timestamp                                    |
| `updated_at`     | TIMESTAMP   | Last update timestamp                                 |

The entity is defined in [`src/Entity/MetaObject.php`](src/Entity/MetaObject.php:1) using Doctrine annotations and follows strict typing and PSR-12 conventions.

### Generic Repository

[`src/Repository/MetaObjectRepository.php`](src/Repository/MetaObjectRepository.php:1) is a service that wraps `EntityManagerInterface` and provides the following methods:

- `find(string $id): ?MetaObject` — Load a single object by UUID.
- `findAllByType(string $type): array` — List all objects of a given type.
- `findByTypeAndFilters(string $type, array $filters): array` — Perform basic JSONB filtering via query parameters.
- `save(MetaObject $object): void` — Insert or update an object.
- `delete(MetaObject $object): void` — Remove an object.

Filtering utilizes PostgreSQL JSONB operators through Doctrine QueryBuilder, for example: `m.data->> :field = :value`.

### Invokable Controllers

Each CRUD operation is managed by a dedicated single-action controller implementing the `__invoke` method:

- [`DataListController`](src/Controller/DataListController.php:1) — Handles `GET /data/{type}` (supports optional `?field=value` filters).
- [`DataCreateController`](src/Controller/DataCreateController.php:1) — Handles `POST /data/{type}`.
- [`DataShowController`](src/Controller/DataShowController.php:1) — Handles `GET /data/{type}/{id}`.
- [`DataUpdateController`](src/Controller/DataUpdateController.php:1) — Handles `PUT /data/{type}/{id}`.
- [`DataDeleteController`](src/Controller/DataDeleteController.php:1) — Handles `DELETE /data/{type}/{id}`.

Controllers reside in `src/Controller/` and utilize the generic repository and JSON Schema validation (via `justinrainbow/json-schema` and Symfony Validator). All controllers are written with strict typing and follow PSR-12.

### Dynamic Routing

Routes are defined once in `config/routes/data.yaml` using wildcards:

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

Symfony imports these routes at runtime. Adding a new schema file immediately exposes the corresponding endpoints without requiring code changes.

### Validation Pipeline

`DataCreateController` and `DataUpdateController` load the relevant JSON Schema and validate incoming data as follows:

```php
<?php declare(strict_types=1);

$validator->validate($payload, $schema, Constraint::CHECK_MODE_APPLY_DEFAULTS);
if (!$validator->isValid()) {
    // Return HTTP 422 with validation errors
}
```

This process ensures that only schema-compliant data is persisted.

### Namespaces & Autoloading

All PHP code in this application uses the `Bareapi\` namespace. Composer PSR-4 autoloading is configured in `composer.json`:

```json
"autoload": {
  "psr-4": {
    "Bareapi\\": "src/Bareapi/"
  }
}
```

Service auto-wiring is configured in `config/services.yaml` to automatically discover entities, repositories, and controllers under the `Bareapi\` namespace.

### Entry Points

- [`public/index.php`](public/index.php:1) and [`bin/console`](bin/console:1) bootstrap `Bareapi\Kernel` instead of `App\Kernel`.

## Adding a New Object Type

To introduce a new object type:

1. Add your JSON Schema to `config/schemas/{newtype}.json`.
2. (Optional) Define a `version` property in your schema.
3. Use the REST endpoints: `POST`, `GET`, `PUT`, or `DELETE` at `/data/{newtype}` and `/data/{newtype}/{id}`. No code changes are required.

## Dependencies

- PHP 8.3+
- Symfony Framework with Flex
- Doctrine ORM, Migrations, and DBAL (PostgreSQL)
- symfony/validator, justinrainbow/json-schema
- ramsey/uuid

---

**BareAPI** provides a fully schema-driven CRUD API platform: simply add schemas to get REST endpoints, with no boilerplate required per type.

## Running Commands

All application commands (such as Composer, Symfony console, migrations, tests, and static analysis) must be executed inside the PHP "app" container. For example:

```bash
docker compose run --rm app composer install
docker compose run --rm app bin/console doctrine:migrations:migrate
docker compose run --rm app bin/console cache:clear
docker compose run --rm app composer test-local
docker compose run --rm app composer phpstan-local
```

Other tools (Docker Compose itself, git, host-side utilities) can be run directly on the host.

> **Note:** Any Composer or PHP script must be executed inside the container using `docker compose run --rm app ...`.

### PHPUnit

Install the Symfony PHPUnit Bridge (along with PHPUnit and helpers) in the app container:

```bash
docker compose run --rm app composer require --dev symfony/test-pack --no-interaction
```

Run the test suite using the Composer helper or directly:

```bash
docker compose run --rm app composer test
# or:
docker compose run --rm app php bin/phpunit
```

To run static analysis with PHPStan:

```bash
docker compose run --rm app composer phpstan
```

Functional tests for REST controllers are located in [`tests/Feature/DataCreateControllerTest.php`](tests/Feature/DataCreateControllerTest.php:1), [`tests/Feature/DataShowControllerTest.php`](tests/Feature/DataShowControllerTest.php:1), [`tests/Feature/DataUpdateControllerTest.php`](tests/Feature/DataUpdateControllerTest.php:1), and [`tests/Feature/DataDeleteControllerTest.php`](tests/Feature/DataDeleteControllerTest.php:1), covering create, fetch, update, and delete flows against an in-memory SQLite schema.

Ensure the testing framework is enabled with the following configuration:

```yaml
# config/packages/test/framework.yaml
framework:
  test: true
  session:
    storage_factory_id: session.storage.factory.mock_file
```

## Agents (Controllers)

The following controllers serve as the "agents" of the BareAPI application, each responsible for a specific aspect of CRUD operations and request handling. All controllers are implemented as invokable classes, follow strict typing, and adhere to PSR-12 and project guidelines.

---

### [`DataCreateController`](src/Controller/DataCreateController.php:1)

**Role:** Handles the creation of new meta objects.

**Responsibilities:**
- Receives `POST /data/{type}` requests.
- Validates incoming data against the relevant JSON Schema.
- Persists new objects using the repository.

**Inputs:**
- HTTP POST request with JSON payload.
- `{type}` route parameter.

**Outputs:**
- HTTP 201 response with the created object, or 422 on validation error.

**Interactions:**
- Loads schema from `config/schemas/`.
- Uses `MetaObjectRepository` to save data.
- Returns response to the client.

**Example Implementation:**

```php
<?php declare(strict_types=1);

namespace Bareapi\Controller;

use Bareapi\Repository\MetaObjectRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DataCreateController
{
    public function __construct(
        private MetaObjectRepository $repository,
        private ValidatorInterface $validator
    ) {}

    public function __invoke(Request $request, string $type): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $schema = $this->loadSchema($type);

        $this->validator->validate($payload, $schema, Constraint::CHECK_MODE_APPLY_DEFAULTS);

        if (!$this->validator->isValid()) {
            return new JsonResponse(['errors' => $this->validator->getErrors()], 422);
        }

        $object = $this->repository->createFromPayload($type, $payload);
        $this->repository->save($object);

        return new JsonResponse($object->toArray(), 201);
    }

    private function loadSchema(string $type): array
    {
        // Implementation for loading the schema from config/schemas/
    }
}
```

---

### [`DataDeleteController`](src/Controller/DataDeleteController.php:1)

**Role:** Handles the deletion of meta objects.

**Responsibilities:**
- Receives `DELETE /data/{type}/{id}` requests.
- Locates and deletes the specified object.

**Inputs:**
- HTTP DELETE request.
- `{type}` and `{id}` route parameters.

**Outputs:**
- HTTP 204 response on success, 404 if not found.

**Interactions:**
- Uses `MetaObjectRepository` to find and delete objects.
- Returns response to the client.

**Example Implementation:**

```php
<?php declare(strict_types=1);

namespace Bareapi\Controller;

use Bareapi\Repository\MetaObjectRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

final class DataDeleteController
{
    public function __construct(
        private MetaObjectRepository $repository
    ) {}

    public function __invoke(string $type, string $id): JsonResponse
    {
        $object = $this->repository->find($id);

        if ($object === null || $object->getType() !== $type) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $this->repository->delete($object);

        return new JsonResponse(null, 204);
    }
}
```

---

### [`DataListController`](src/Controller/DataListController.php:1)

**Role:** Lists meta objects of a given type.

**Responsibilities:**
- Receives `GET /data/{type}` requests.
- Applies optional query filters.
- Retrieves and returns a list of objects.

**Inputs:**
- HTTP GET request.
- `{type}` route parameter.
- Optional query parameters for filtering.

**Outputs:**
- HTTP 200 response with an array of objects.

**Interactions:**
- Uses `MetaObjectRepository` to query objects.
- Returns response to the client.

**Example Implementation:**

```php
<?php declare(strict_types=1);

namespace Bareapi\Controller;

use Bareapi\Repository\MetaObjectRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class DataListController
{
    public function __construct(
        private MetaObjectRepository $repository
    ) {}

    public function __invoke(Request $request, string $type): JsonResponse
    {
        $filters = $request->query->all();
        $objects = $this->repository->findByTypeAndFilters($type, $filters);

        return new JsonResponse(array_map(
            static fn($object) => $object->toArray(),
            $objects
        ));
    }
}
```

---

### [`DataShowController`](src/Controller/DataShowController.php:1)

**Role:** Retrieves a single meta object by ID.

**Responsibilities:**
- Receives `GET /data/{type}/{id}` requests.
- Retrieves the specified object.

**Inputs:**
- HTTP GET request.
- `{type}` and `{id}` route parameters.

**Outputs:**
- HTTP 200 response with the object, 404 if not found.

**Interactions:**
- Uses `MetaObjectRepository` to find the object.
- Returns response to the client.

**Example Implementation:**

```php
<?php declare(strict_types=1);

namespace Bareapi\Controller;

use Bareapi\Repository\MetaObjectRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

final class DataShowController
{
    public function __construct(
        private MetaObjectRepository $repository
    ) {}

    public function __invoke(string $type, string $id): JsonResponse
    {
        $object = $this->repository->find($id);

        if ($object === null || $object->getType() !== $type) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        return new JsonResponse($object->toArray());
    }
}
```

---

### [`DataUpdateController`](src/Controller/DataUpdateController.php:1)

**Role:** Updates an existing meta object.

**Responsibilities:**
- Receives `PUT /data/{type}/{id}` requests.
- Validates updated data against the JSON Schema.
- Persists changes.

**Inputs:**
- HTTP PUT request with JSON payload.
- `{type}` and `{id}` route parameters.

**Outputs:**
- HTTP 200 response with the updated object, 422 on validation error, 404 if not found.

**Interactions:**
- Loads schema from `config/schemas/`.
- Uses `MetaObjectRepository` to update data.
- Returns response to the client.

**Example Implementation:**

```php
<?php declare(strict_types=1);

namespace Bareapi\Controller;

use Bareapi\Repository\MetaObjectRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DataUpdateController
{
    public function __construct(
        private MetaObjectRepository $repository,
        private ValidatorInterface $validator
    ) {}

    public function __invoke(Request $request, string $type, string $id): JsonResponse
    {
        $object = $this->repository->find($id);

        if ($object === null || $object->getType() !== $type) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $schema = $this->loadSchema($type);

        $this->validator->validate($payload, $schema, Constraint::CHECK_MODE_APPLY_DEFAULTS);

        if (!$this->validator->isValid()) {
            return new JsonResponse(['errors' => $this->validator->getErrors()], 422);
        }

        $object->updateFromPayload($payload);
        $this->repository->save($object);

        return new JsonResponse($object->toArray());
    }

    private function loadSchema(string $type): array
    {
        // Implementation for loading the schema from config/schemas/
    }
}
```

---

### [`HomeController`](src/Controller/HomeController.php:1)

**Role:** Handles the root endpoint and basic health or information checks.

**Responsibilities:**
- Receives requests to `/`.
- Returns API status or a welcome message.

**Inputs:**
- HTTP GET request.

**Outputs:**
- HTTP 200 response with status or information.

**Interactions:**
- No repository interaction.
- Returns response to the client.

**Example Implementation:**

```php
<?php declare(strict_types=1);

namespace Bareapi\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

final class HomeController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'message' => 'Welcome to BareAPI'
        ]);
    }
}
```

---

## Documentation and Coding Standards

All PHP code in BareAPI strictly follows these standards:

- `declare(strict_types=1);` at the top of every file.
- Explicit type hints for all properties, arguments, and return types.
- PSR-12 code style and naming conventions.
- No loose types or dynamic properties.
- All business logic is covered by behavior-driven tests using PHPUnit.
- Static analysis is enforced with PHPStan in strict mode.
- No commented-out or dead code is permitted in the codebase.

For further details, refer to [`PHP.md`](PHP.md:1).

---

## Summary

BareAPI delivers a robust, schema-driven CRUD API platform. By adhering to strict PHP development guidelines, it ensures maintainability, reliability, and clarity. Add new schemas to instantly expose new REST endpoints—no boilerplate or manual wiring required.
