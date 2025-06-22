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

## Summary

- All CRUD operations use `/data/{type}` or `/data/{type}/{UUID}` endpoints.
- The `note` resource requires a `title` and optionally accepts `content`.
- All requests and responses use JSON format.
