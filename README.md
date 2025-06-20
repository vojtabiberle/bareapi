# bareapi

A schema-driven JSON API backend built with Symfony.

## Requirements

- Docker
- Docker Compose

## Quick Start

Copy the example environment file and build the services (this will install PHP dependencies inside the container):

```bash
cp .env.example .env
```

```bash
docker-compose up --build
```

The API will be available at <http://localhost:8000> and Adminer (for database management) at <http://localhost:8080>.

## Project Structure

- `config/schemas/`: JSON Schema files defining your API data models.
- `src/`: PHP source code (controllers, entities, etc.).
- `public/`: Web entry point.
- `Dockerfile`: PHP application image.
- `docker-compose.yml`: Docker services for app, database, and Adminer.

## Adding a New Schema

1. Create a new JSON schema file in `config/schemas/`, e.g. `config/schemas/tasks.json`.
2. Define the JSON Schema for your resource (fields, types, validation).
3. Implement corresponding controllers, entities, and services in `src/` to handle the new resource.

## Configuration

Database connection is configured in `.env`:

```dotenv
DATABASE_URL="postgresql://bareapi:bareapi@db:5432/bareapi?serverVersion=15&charset=utf8"
```

Error reporting and CORS are enabled for development by default.