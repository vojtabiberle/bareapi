# API Usage Guide

This API follows RESTful principles, providing a consistent interface for managing resources. Each resource type is accessible via predictable endpoints, and standard HTTP methods are used for Create, Retrieve, Update, and Delete (CRUD) operations.

## CRUD Lifecycle Example: `note` Resource

The `note` resource uses the following schema:

```json
{
  "id": "UUIDv7",
  "title": "string",
  "content": "string"
}
```

### 1. Create a New Note

**Endpoint:**  
`POST /data/note`

**Request Body:**

```json
{
  "title": "Meeting Notes",
  "content": "Discuss project milestones and deadlines."
}
```

**Example cURL:**

```bash
curl -X POST http://localhost:8000/data/note \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Meeting Notes",
    "content": "Discuss project milestones and deadlines."
  }'
```

**Response Example:**

```json
{
  "id": "018f8e2e-7b6c-7b1a-8e2e-7b6c7b1a8e2e",
  "title": "Meeting Notes",
  "content": "Discuss project milestones and deadlines."
}
```

---

### 2. Retrieve the Note

**Endpoint:**  
`GET /data/note/{UUID}`

**Example cURL:**

```bash
curl http://localhost:8000/data/note/018f8e2e-7b6c-7b1a-8e2e-7b6c7b1a8e2e
```

**Response Example:**

```json
{
  "id": "018f8e2e-7b6c-7b1a-8e2e-7b6c7b1a8e2e",
  "title": "Meeting Notes",
  "content": "Discuss project milestones and deadlines."
}
```

---

### 3. Update the Note

**Endpoint:**  
`PUT /data/note/{UUID}`

**Request Body:**

```json
{
  "title": "Updated Meeting Notes",
  "content": "Discuss project milestones, deadlines, and budget."
}
```

**Example cURL:**

```bash
curl -X PUT http://localhost:8000/data/note/018f8e2e-7b6c-7b1a-8e2e-7b6c7b1a8e2e \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Updated Meeting Notes",
    "content": "Discuss project milestones, deadlines, and budget."
  }'
```

**Response Example:**

```json
{
  "id": "018f8e2e-7b6c-7b1a-8e2e-7b6c7b1a8e2e",
  "title": "Updated Meeting Notes",
  "content": "Discuss project milestones, deadlines, and budget."
}
```

---

### 4. Delete the Note

**Endpoint:**  
`DELETE /data/note/{UUID}`

**Example cURL:**

```bash
curl -X DELETE http://localhost:8000/data/note/018f8e2e-7b6c-7b1a-8e2e-7b6c7b1a8e2e
```

**Response Example:**

```json
{
  "status": "deleted"
}
```

---

## Tags and Tag Bindings

This section explains how to use the `Tag` and `TagBinding` data models to categorize and link resources.

### Tag Model Overview

The `Tag` object allows you to create labels that can be applied to other resources. Each tag has a unique identifier, a name, a color for display purposes, and information about its creator.

**Schema:**
```json
{
  "id": "UUIDv7",
  "name": "string",
  "color": "string",
  "creator": {
    "id": "UUIDv7",
    "name": "string"
  }
}
```

### TagBinding Model Explanation

The `TagBinding` model provides a flexible way to associate a `Tag` with any other object, whether it's an internal resource within our system or an external one. This is a polymorphic association, meaning a single `TagBinding` can link a tag to different types of objects. This is achieved through the `objectId` field, which can hold a UUID for an internal resource or any unique string identifier for an external one.

**Schema:**
```json
{
  "tagId": "UUIDv7",
  "objectId": "string"
}
```

### Usage Examples

Here are some examples of how to use `TagBinding`.

#### Binding to Internal Notes

To link a `Tag` to an internal `Note` object, you populate the `objectId` field of the `TagBinding` with the `id` of the target `Note`.

**Example:**

Let's say you have a `Tag` with `id: "018f8e3a-5b1a-7c2b-8e3a-5b1a7c2b8e3a"` and a `Note` with `id: "018f8e2e-7b6c-7b1a-8e2e-7b6c7b1a8e2e"`.

You would create a `TagBinding` like this:

```json
{
  "tagId": "018f8e3a-5b1a-7c2b-8e3a-5b1a7c2b8e3a",
  "objectId": "018f8e2e-7b6c-7b1a-8e2e-7b6c7b1a8e2e"
}
```

#### Binding to External Resources

To link a `Tag` to an external resource, you use a unique string identifier for that resource in the `objectId` field. This could be a URI, an ID from another system, or any other unique key.

**Example:**

To tag an external article, you could use its URL as the `objectId`.

```json
{
  "tagId": "018f8e3a-5b1a-7c2b-8e3a-5b1a7c2b8e3a",
  "objectId": "https://example.com/articles/important-topic"
}
```
---
## Summary

- All CRUD operations use `/data/{type}` or `/data/{type}/{UUID}` endpoints.
- The `note` resource requires a `title` and optionally accepts `content`.
- All requests and responses use JSON format.
