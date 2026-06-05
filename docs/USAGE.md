# API Usage Guide

BareAPI exposes a product-neutral, schema-driven document store API under `/api/v1`. The older `/api/{type}` CRUD routes are still available as compatibility endpoints.

## Repository Objects

Create an object:

```bash
curl -X POST http://localhost:8000/api/v1/repository/notes \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Release Notes",
    "branch": "main",
    "schemaVersion": "1.0.0",
    "data": {
      "title": "Initial",
      "content": "Initial content"
    }
  }'
```

Response bodies use a JSON:API-style envelope:

```json
{
  "data": {
    "type": "notes",
    "id": "018ff3ae-c558-7ed8-8f68-0242ac120002",
    "meta": {
      "schemaVersion": "1.0.0",
      "branch": "main",
      "name": "Release Notes",
      "revision": 1,
      "createdAt": "2026-06-05T09:00:00+00:00",
      "lastUpdated": "2026-06-05T09:00:00+00:00",
      "revisionCreatedAt": "2026-06-05T09:00:00+00:00"
    },
    "attributes": {
      "title": "Initial",
      "content": "Initial content"
    }
  }
}
```

Supported repository routes:

- `GET /api/v1/repository/{objectType}`
- `POST /api/v1/repository/{objectType}`
- `GET /api/v1/repository/{objectType}/{id}`
- `PATCH /api/v1/repository/{objectType}/{id}`
- `PUT /api/v1/repository/{objectType}/{id}`
- `DELETE /api/v1/repository/{objectType}/{id}`
- `GET /api/v1/repository/{objectType}/revisions`
- `GET /api/v1/repository/{objectType}/{id}/revisions/{revision}`
- `DELETE /api/v1/repository/{objectType}/{id}/revisions/{revision}`

Deletes are soft deletes. Normal reads and lists hide deleted objects and deleted revisions.
Object, revision, and reference writes are wrapped in one transaction for create, update, and delete flows.

## Schemas

Schemas are stored in the database and versioned by object type.

- `GET /api/v1/schema/{objectType}` returns the default schema.
- `GET /api/v1/schema/{objectType}/{version}` returns a specific schema version.
- `bin/console bareapi:schema:import` imports `config/schemas/*.json` into the schema store.

## Filtering

Collection reads support schema-driven filters:

```bash
curl "http://localhost:8000/api/v1/repository/tags?color=red&creator.name=Jan&name%5Border%5D=desc&limit=10&offset=0"
```

JSON field filters must be allowed by schema metadata. If any property has `x-filterable: true`, only explicitly marked fields are filterable. If no property is explicitly marked, primitive properties are filterable by default. Dotted paths such as `creator.name` are supported.

Table fields can also be filtered or ordered:

- `schema_version`
- `branch`
- `name`
- `last_updated`
- `created_at`
- `revision`
- `revision_created_at`

## Authorization

Writes are public unless the default schema declares `x-bareapi.acl`:

```json
{
  "x-bareapi.acl": {
    "create": ["object:create"],
    "update": ["object:update"],
    "delete": ["object:delete"]
  }
}
```

Requests provide permissions with a simple bearer token:

```bash
curl -X POST http://localhost:8000/api/v1/repository/notes \
  -H "Authorization: Bearer object:create" \
  -H "Content-Type: application/json" \
  -d '{"data":{"title":"Protected"}}'
```

This is intentionally generic so production deployments can replace the token source later.

## References

Schemas can declare references using `x-bareapi.references`:

```json
{
  "x-bareapi.references": [
    {
      "property": "tagId",
      "refersTo": "tags",
      "onDelete": "restrict"
    }
  ]
}
```

Supported `onDelete` values are `restrict` and `cascade`.

## Operational Endpoints

- `GET /` returns the service index.
- `GET /health-check` checks database connectivity.
- `GET /api/v1/documentation/openapi.json` returns the OpenAPI document.

## Legacy Compatibility

The original routes remain available:

- `GET /api/{type}`
- `POST /api/{type}`
- `GET /api/{type}/{id}`
- `PUT /api/{type}/{id}`
- `DELETE /api/{type}/{id}`

New integrations should use `/api/v1`.
