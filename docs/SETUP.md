# Project Setup Guide

Follow these steps to set up your development environment for this project.

---

## 1. Prerequisites

Ensure you have the following tools installed:

- [Git](https://git-scm.com/)
- [Docker](https://www.docker.com/)
- [Docker Compose](https://docs.docker.com/compose/)
- [Composer](https://getcomposer.org/) (for PHP dependency management)

---

## 2. Cloning the Repository

Clone the repository to your local machine:

```sh
git clone https://github.com/your-username/bareapi.git
cd bareapi
```

---

## 3. Environment Configuration

Copy the example environment file to create development and test environment files:

```sh
cp .env.example .env.dev
cp .env.example .env.test
```

**Note:**  
Edit `.env.dev` and `.env.test` as needed. Key variables you may want to change include:

- `DATABASE_URL` – Database connection string
- `APP_ENV` – Application environment (`dev`, `test`, etc.)
- `APP_SECRET` – Application secret key

---

## 4. Docker Setup

Build and start the services using Docker Compose:

```sh
docker-compose up -d
```

This will build the Docker images and start all required containers in the background.

---

## 5. Dependency Installation

Install PHP dependencies using Composer **inside the PHP container**:

```sh
docker-compose exec app composer install
```

Replace `app` with the name of your PHP service if different.

---

## 6. Database Migration

Run database migrations to set up the schema:

```sh
docker-compose exec app php bin/console doctrine:migrations:migrate
```

---

## 7. Running the Application

Once the containers are running, access the application in your browser:

```
http://localhost:8000
```

Adjust the port if you have mapped it differently in `docker-compose.yml`.

---

## 8. Running Tests

Execute the test suite inside the PHP container:

```sh
docker-compose exec app php bin/phpunit
```

---

Your development environment is now ready.
