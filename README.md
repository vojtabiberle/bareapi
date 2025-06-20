# bareapi

A schema-driven JSON API backend built with Symfony.

## Requirements

- Docker
- Docker Compose

### PHP UUID Support
- Symfony Uid component for UUIDv7 generation (installed via Composer).

## Quick Start

Copy the example environment file, then build and start the services (this will install PHP dependencies inside the containers):

```bash
cp .env.example .env
docker-compose up --build
```

The API (served via FrankenPHP) will be available at <http://localhost:8000>, and Adminer (for database management) at <http://localhost:8080>.

## Project Structure

- `config/schemas/`: JSON Schema files defining your API data models.
- `src/Entity/`: Doctrine ORM entities with UUIDv7 primary keys.
- `src/Repository/`: Doctrine repositories for your entities.
- `src/`: PHP source code (controllers, services, etc.).
- `public/`: Web entry point.
- `Dockerfile`: PHP application image.
- `docker-compose.yml`: Docker services for app, database, and Adminer.

## Adding a New Schema

1. Create a new JSON schema file in `config/schemas/`, e.g. `config/schemas/tasks.json`.
2. Define the JSON Schema for your resource (fields, types, validation). Be sure to use:

   ```jsonc
   "id": {"type": "string", "format": "uuid"}
   ```
   for a UUIDv7 primary identifier.
3. Implement corresponding entities (with UUIDv7 IDs), controllers, and services in `src/` to handle the new resource.

## Configuration

Database connection is configured in `.env`:

```dotenv
DATABASE_URL="postgresql://bareapi:bareapi@db:5432/bareapi?serverVersion=15&charset=utf8"
```

Error reporting and CORS are enabled for development by default.