# System Architecture Overview

## 1. Introduction

This document provides a high-level overview of the system's design. The project is a bare API service for CRUD operations on meta objects.

## 2. Architectural Patterns

The application is designed using the Model-View-Controller (MVC) pattern combined with a layered architecture. This approach ensures separation of concerns, modularity, and scalability.

## 3. Core Components

- **Kernel:** Bootstraps the app. See [`Kernel.php`](src/Bareapi/Kernel.php:1).
- **Controllers:** Handle HTTP requests (e.g., DataCreateController, DataDeleteController, etc.).
- **Entities:** Represent the data model. Example: [`MetaObject.php`](src/Bareapi/Entity/MetaObject.php:1).
- **Repositories:** Manage data persistence. Example: [`MetaObjectRepository.php`](src/Bareapi/Repository/MetaObjectRepository.php:1).

## 4. Data Flow

The following Mermaid diagram illustrates the lifecycle of a typical API request:

```mermaid
graph LR
    A[Request] --> B[index.php]
    B --> C[Kernel.php]
    C --> D[Routing]
    D --> E[Controller]
    E --> F[Repository/Entity]
    F --> G[Response]
```

## 5. Directory Structure

- **src:** Contains application source code (Controllers, Entities, Repositories, etc.).
- **config:** Configuration files for setting up services and dependencies.
- **public:** Web entry point. Contains files such as [`index.php`](public/index.php:1).
- **tests:** Unit and functional tests ensuring code quality.

End of the architecture overview document.
