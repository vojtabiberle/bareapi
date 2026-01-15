# RFC: Introduce `x-metastore.refersTo` (uuid-only) + Reference Integrity & Relationship Enrichment

## Why

Today, Metastore has no first-class way to declare and enforce relationships between objects. This leads to:

* Possible **orphaned references** and inconsistent data.
* Clients doing repetitive lookups (N+1) to render related info.
* No systematic way to **block deletes** that would break other objects or to **cascade** cleanly.
* No fast method to answer **“who references this object?”**

We are introducing a new schema keyword — `x-metastore.refersTo` — to declare references, validate them at write time, define deletion behavior, and (optionally) enrich reads with related objects. Design decisions intentionally keep v1 small and robust: **uuid-only**, **depth=1**, **project-scoped**, **model-level ACL respected**.

## What we decided (drivers)

* **Reference key = `uuid` only** (avoid uniqueness/indexing complexity of arbitrary keys).
* **Depth=1** enrichment only (avoid recursion, payload blowups). Delete cascades can follow deeper chains; enrichment stays shallow.
* **Deletion semantics** declared per reference via `x-metastore.onDelete`:

  * `restrict` (default) | `cascade`.
* **Optional enrichment** via `?include=relationships` (+ selective `?relationships=...`).
* **Reverse index table** to power fast inbound lookups, delete plans, and counts.
* **Project/org isolation** enforced in all lookups (align with current ACL).

---

## New Schema Keyword: `x-metastore.refersTo`

### Syntax (field-level)

```json
{
  "type": "string",
  "x-metastore": {
    "refersTo": { "type": "tag", "field": "uuid" },
    "onDelete": "restrict"  // or "cascade"
  }
}
```

### Rules

* `refersTo.type` = target object type (e.g., `"tag"`).
* `refersTo.field` must be `"uuid"` (only supported key in v1).
* `onDelete`:

  * Default: `"restrict"`.
  * `"cascade"`: delete the referrer when target is deleted (soft-delete, consistent with model, and repeats along any downstream cascade chains).
* Empty value (`""` or `null`) is treated as “no reference”.

### Scope

* **Project-scoped only**: target must be in the same project (and organization where applicable).
* Only **non-deleted** targets can be referenced.

---

## Behavior

### 1) Write-time validation (create/update)

* Run standard JSON Schema validation.
* Walk schema for `x-metastore.refersTo`.
* Collect all **uuid** values (depth=1 only), batch-validate they exist & are live in the same project/org.
* On failure: 422 with your standard error format, plus:

  ```json
  "meta": {
    "ref": { "path": "data.tagId", "type": "tag", "uuid": "…" }
  }
  ```

### 2) Reverse index (`meta_refs`) maintenance

* On every successful write, compute current set of refs `{path, to_type, to_uuid}` and **sync** `meta_refs` for that `(project_id, from_type, from_uuid)`.
* Use it for fast: inbound lookups, pre-delete checks, cascade plans, simple analytics (“how many tag-bindings reference tag X?”).
* Add a lightweight **ref integrity job** that can rebuild `meta_refs` for a project/type on demand. It walks stored objects, replays schema evaluation, and repairs rows. We do not need it on day one, but documenting it now keeps future maintenance straightforward once production data exists.

### 3) Delete semantics (`onDelete`)

When deleting a **target** object:

* Fetch inbound referrers via `meta_refs`.
* Build a delete **plan** that lists actions per `(from_type, path)` and stage it in a work queue (e.g., in-memory coroutine or durable job table). The planner transaction only persists the plan and marks the target for deletion.
* Execute the plan atomically within the worker: for each reference path apply `onDelete`:

  * `restrict`: abort the plan with an error if any refs exist (include counts and sample).
  * `cascade`: soft-delete the referrers, enqueue their own delete plans if their schema also declares `cascade`, and only delete the original target once descendant cascades succeed.
* Because cascades can chain arbitrarily, the worker keeps track of visited `(type, uuid)` pairs to avoid loops, and every hop reuses the same logic (depth=∞ for deletes, depth=1 for enrichment).
* The worker refreshes `meta_refs` for every object it touches so the reverse index remains consistent.

### 4) Read enrichment (optional)

* Off by default.
* `?include=relationships=true` turns it on.
* `?relationships=field1,field2` limits to chosen paths; if omitted, **all declared** refs are enriched (depth=1).
* Response adds a `relationships` object keyed by **data path** (normalized):

  * `"data.tagId"` → single object.
  * `"data.items.tagId"` → array of objects (when the source path is array-backed).
* Each relationship entry includes:

  * `data` (the full referenced object, latest revision)
  * `url` (canonical API URL)
  * `meta.sourcePath` (the data path)
* ACL or lifecycle filters may hide a referenced object. In that case the entry is omitted; when the source expects an array, omit each filtered item and return an empty array if no related entities survive.
* Reference payloads never contain duplicate UUIDs (enforced by schema/business logic), so enrichment never emits duplicates.

---

## `meta_refs` Repair / Consistency Checker

Even though the service is not yet in production, we want a future-proof story for rebuilding the reverse index. A lightweight maintenance tool will:

1. Take `(project_id, object_type[, object_uuid])` as input.
2. Load objects in batches, re-run `x-metastore.refersTo` evaluation, and compute `{path, to_type, to_uuid}`.
3. Diff the results against `meta_refs` and upsert/delete rows accordingly.
4. Emit metrics/logs on drift so operators can alert.

We can implement this as both:

* a CLI (`task metastore:verify-meta-refs`) for manual/CI runs, and
* an automated job using **pg_cron**, mirroring the Query service pattern. A Goose migration will create a helper SQL function (calling into our Go repair logic via `SELECT` or direct SQL), guard `CREATE EXTENSION IF NOT EXISTS pg_cron`, and register schedules with `cron.schedule`. Down migrations unschedule. This lets Postgres trigger nightly/weekly repairs without another control plane, while remaining optional when pg_cron isn’t installed.

---

## Error & Logging (for debugging)

* Keep existing error contract; add `meta.ref { path, type, uuid }` on ref failures.
* Log warnings with the same fields; include index positions for arrays in logs (not required in API error).

---

## Observability (minimal v1)

* Counter: how many `tag-binding` reference `tag` (derive from `meta_refs`).
* Optional: counts of validation failures and enriched items per request (later).

---

## Rollout / Compatibility

* This is **new**: no migration needed.
* Feature guarded by schema presence; legacy schemas without `x-metastore.refersTo` are unaffected.
* Documentation & examples added; lint rule optionally warns if deprecated keywords (like `setNull`) show up in schemas.

---

# Actionable PR Tasks

> Keep PRs small and linear; each PR should include tests and docs updates relevant to the change.

### PR-1: Schema Keyword Introduction

**Goal:** Teach the schema layer the new keyword.

* Add support for `x-metastore.refersTo` and `x-metastore.onDelete` at **field level**.
* Validation rules:

  * `refersTo.field` must equal `"uuid"`.
  * `onDelete` ∈ {`restrict`, `cascade`} (default `restrict`).
* Docs: add keyword spec, examples, and call out that `setNull` is intentionally omitted until we have a concrete use case.

### PR-2: DB Migration — Reverse Index

**Goal:** Create `meta_refs` table and basic indices.

* Table with `(project_id, from_type, from_uuid, path, to_type, to_uuid, created_at)`.
* Primary key across those columns; secondary indices for inbound lookups and counts.
* Docs: purpose, example queries (counts, inbound).

### PR-3: Write Path — Reference Validation (uuid-only)

**Goal:** Enforce referential integrity on create/update.

* Traverse validated payload (depth=1) per schema; collect candidate UUIDs by `(to_type, path)`.
* Batch-validate existence (same project/org, not deleted).
* On failure, return 422 with `meta.ref`.
* Respect current ACL filters in SQL.
* Tests: simple, array, nested object at depth=1, empty/null treated as absent.

### PR-4: Write Path — Reverse Index Sync

**Goal:** Keep `meta_refs` exact for latest revision.

* After a successful write, diff `{path, to_type, to_uuid}` vs existing rows for `(project_id, from_type, from_uuid)`; delete missing, insert new (idempotent).
* Tests: insert, update change of target, removal to empty/null.

### PR-5: Delete Planner — `onDelete` Enforcement

**Goal:** Apply `restrict | cascade` on target deletion using the staged plan/worker model.

* Lookup inbound references via `meta_refs`.
* For each `(from_type, path)`:

  * `restrict`: reject with counts & sample.
  * `cascade`: soft-delete referrers (then clean their `meta_refs` and trigger any downstream cascades).
* Planner transaction stores the work items; worker/coroutine executes them atomically.
* Tests: restrict-only errors, cascade chains, mixed modes per path, large counts (batching behavior if applicable), and the staged execution path.

### PR-6: Read API — Relationship Enrichment (depth=1)

**Goal:** Optional embedding of referenced objects.

* Query params:

  * `include=relationships` (bool, default false).
  * `relationships=field1,field2` (optional filter).
* Build `relationships` keyed by normalized data path:

  * Single vs array shape matches source field shape.
* Include `data`, `url`, `meta.sourcePath`.
* Tests: full enrichment, selective enrichment, array paths.

### PR-7: Documentation & Examples

**Goal:** Developer-facing docs.

* Keyword spec with examples for:

  * `restrict` (default) and `cascade` (e.g., tag-binding → tag).
* Example GET with `?include=relationships` and `?relationships=…`.
* Delete behavior table and example responses.

### PR-8: Minimal Analytics (optional now, easy later)

**Goal:** Count references and validation outcomes.

* One “how many tag-bindings → tag” example query using `meta_refs`.
* Optional: increment counters for validation success/fail and enrichment items total.

---

## Example Snippets (non-code, conceptual)

**Schema (tag-binding referencing tag with cascade):**

```json
{
  "type": "object",
  "properties": {
    "tagId": {
      "type": "string",
      "x-metastore": {
        "refersTo": { "type": "tag", "field": "uuid" },
        "onDelete": "cascade"
      }
    }
  },
  "required": ["tagId"]
}
```

**GET Enrichment (requested):**

* Request: `GET /repository/tag-binding/<uuid>?include=relationships`
* Response excerpt:

```json
"relationships": {
  "data.tagId": {
    "data": { "uuid": "…", "objectType": "tag", "name": "Production", "revision": 2, "data": { "color": "red" } },
    "url": "/api/v1/repository/tag/…",
    "meta": { "sourcePath": "data.tagId" }
  }
}
```

**Delete Outcome Summary (restrict/cascade):**

* `restrict`: planner surfaces `{ count: <inbound_count>, examples: [<uuid…>] }` and the job aborts.
* `cascade`: worker soft-deletes referrers (chaining through their schemas if needed) and finally soft-deletes the original target.

---

## Risk Review

* **Payload growth**: mitigated by opt-in `?include=relationships` and depth=1.
* **Query load**: batch validations; enrichment reuses single fetch per target uuid.
* **Complex deletes**: staged work-queue keeps transactions small; cascade chains honor schemas but guard against loops.
* **Index drift**: repair tool plus write-path sync protects `meta_refs` correctness even after failures.
* **ACL**: unchanged; lookups reuse existing project/org filters.

