# Users Management API (Laravel 12)

This project implements the PHP code test requirements using Laravel `12.x`.

## What Was Implemented

### 1. Create User API
- Endpoint: `POST /api/users`
- Validation:
  - `email`: required, valid email, unique
  - `password`: required, min 8 chars
  - `name`: required, min 3, max 50
- Behavior:
  - Creates a new user in `users`
  - Sends 2 emails:
    - to the newly created user
    - to all active users with role `admin`
  - Returns created user fields only (no password)

### 2. Get Users API
- Endpoint: `GET /api/users`
- Auth: protected by `auth` middleware
- Query params:
  - `search` (optional): match `name` or `email`
  - `page` (optional): pagination page
  - `sortBy` (optional): `name`, `email`, `created_at`
- Behavior:
  - Returns only active users (`active = true`)
  - Includes `orders_count`
  - Includes `can_edit` based on role rules:
    - `admin`: can edit all users
    - `manager`: can edit users with role `user`
    - `user`: can edit only themselves

## Database Updates

### `users` table
Added:
- `role` enum: `admin | manager | user` (default `user`)
- `active` boolean (default `true`)

### `orders` table
Added new table:
- `id`
- `user_id` (FK -> `users.id`, cascade delete)
- timestamps

## Mail Updates

Added mailables:
- `App\\Mail\\UserCreatedMail`
- `App\\Mail\\AdminNewUserNotificationMail`

Added mail text views:
- `resources/views/emails/user_created.blade.php`
- `resources/views/emails/admin_new_user_notification.blade.php`

Added config:
- Seeders create default users on first run with random passwords:
  - admin (`admin@example.com`)
  - manager (`manager@example.com`)
  - user (`user@example.com`)

## Code Structure Updates

- Added API routes file: `routes/api.php`
- Enabled API route loading in `bootstrap/app.php`
- Added API controller: `App\\Http\\Controllers\\Api\\UserController`
- Updated models:
  - `App\\Models\\User`: role/active fillable + orders relation
  - `App\\Models\\Order`: user relation
- Updated `Database\\Factories\\UserFactory` with defaults for `role` and `active`

## Install Guide

## Prerequisites
- PHP `8.2+` (tested with PHP 8.5)
- Composer `2+`
- MySQL `8+` (default setup uses MySQL)

## Setup Steps
1. Clone project and enter directory.
2. Install dependencies:
   ```bash
   composer install
   ```
3. Copy env file:
   ```bash
   cp .env.example .env
   ```
4. Generate app key:
   ```bash
   php artisan key:generate
   ```
5. Configure `.env` (minimum):
   - `DB_CONNECTION=mysql`
   - `DB_HOST=127.0.0.1`
   - `DB_PORT=3306`
   - `DB_DATABASE=users_management`
   - `DB_USERNAME=root`
   - `DB_PASSWORD=...`
   - SMTP: set `MAIL_MAILER=smtp`, `MAIL_SCHEME=tls`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`
6. Create MySQL database:
   ```sql
   CREATE DATABASE users_management;
   ```
7. Run migrations:
   ```bash
   php artisan migrate
   ```
8. Seed initial role users:
   ```bash
   php artisan db:seed
   ```
   Creates default users (first run only):
   - `admin`: `admin@example.com`
   - `manager`: `manager@example.com`
   - `user`: `user@example.com`
   - Passwords are random 16-character values printed in seeder output
9. Start server:
   ```bash
   php artisan serve
   ```

## Running Tests

```bash
php artisan test
```

Feature tests cover:
- create user validation + persistence + email dispatch
- users list auth requirement
- users list search/sort/orders_count
- users list `can_edit` role behavior

## API Usage

### Create User
```bash
curl -X POST http://127.0.0.1:8000/api/users \
  -H "Content-Type: application/json" \
  -d '{
    "email":"john@example.com",
    "password":"password123",
    "name":"John Doe"
  }'
```

### Get Users (authenticated)
Example with session/cookie auth:
```bash
curl "http://127.0.0.1:8000/api/users?search=john&sortBy=created_at&page=1" \
  -H "Accept: application/json"
```

Note: `GET /api/users` is protected by `auth`. If you need token auth (Sanctum), it can be added as next step.

## Notes
- `password` is hashed by Laravel model cast.
- `GET /api/users` returns a simplified payload:
  - `page`
  - `users[]` with `id`, `email`, `name`, `role`, `created_at`, `orders_count`, `can_edit`.
