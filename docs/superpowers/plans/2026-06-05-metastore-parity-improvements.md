# BareAPI Document Store Improvements Plan

Goal: bring BareAPI closer to the newer document-store design while keeping it product-neutral. Keboola-specific concepts such as Organization, Project, organization admins, project admins, and scoped roles are intentionally excluded.

## Completed Slices

- [x] Add JSON:API-style repository response envelope.
- [x] Add `/api/v1/repository/{objectType}` create route.
- [x] Add versioned repository object routes for read, patch, put, delete, and revision read/delete.
- [x] Store versioned schemas in PostgreSQL.
- [x] Add schema read endpoints.
- [x] Import existing file schemas into the schema store.
- [x] Add metadata columns to `meta_objects`.
- [x] Add immutable `meta_object_revisions`.
- [x] Soft delete objects and revisions.
- [x] Add optional generic schema authorization through `x-bareapi.acl`.
- [x] Add schema-driven filters, dotted JSON paths, table filters, ordering, limit, and offset.
- [x] Add reference integrity through `x-bareapi.references` and `meta_refs`.
- [x] Add restrict and cascade delete behavior for references.
- [x] Add `/health-check`.
- [x] Add `/api/v1/documentation/openapi.json`.
- [x] Update `/` service index.
- [x] Preserve legacy `/api/{type}` routes as compatibility routes.

## Remaining Hardening

- Move create/update object and reference writes into one database transaction.
- Add a dedicated schema write API if runtime schema deployment is required.
- Add revision collection listing if clients need `GET /api/v1/repository/{objectType}/revisions`.
- Replace the test bearer-token permission source with a production identity provider.
- Broaden OpenAPI schemas from endpoint coverage to full request/response component schemas.
