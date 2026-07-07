# Task 1: Online Store API

A lightweight, robust PHP RESTful API that handles flash sale transactions under high concurrency without letting product inventory drop below zero (preventing race conditions).

## Key Features
- **Pessimistic Locking (`SELECT ... FOR UPDATE`)**: Implemented within database transactions to ensure that stock checks and updates are thread-safe and isolated.
- **RESTful CRUD Endpoints**:
  - **Products**: GET `/products`, GET `/products/{id}`, POST `/products`, PUT `/products/{id}`, DELETE `/products/{id}`
  - **Orders**: GET `/orders`, GET `/orders/{id}`, POST `/orders` (creates order + items), PUT `/orders/{id}`, DELETE `/orders/{id}`
  - **Order Items**: GET `/order-items`, GET `/order-items/{id}`, POST `/order-items` (adds item to order and deducts stock), PUT `/order-items/{id}` (modifies quantity and adjusts stock accordingly), DELETE `/order-items/{id}` (removes item and restores stock)
- **Zero-Dependency Core Fallback**: Can run without installing any Composer dependencies.

---

## Installation & Setup

1. **Clone/Navigate to project**:
   ```bash
   cd fomo-assessment-test-task1
   ```

2. **Configure Environment Variables**:
   - Copy `.env.example` to `.env`.
   - Update `DATABASE_URL` with your PostgreSQL connection string.
   - Example: `DATABASE_URL=postgresql://postgres:postgres@localhost:5432/fomo_store`

3. **Initialize Database Schema and Seed Data**:
   Run the self-contained database seeder command:
   ```bash
   php db_seed.php
   ```
   *Note: This script drops existing tables (if any), creates the schema, seeds initial products, and can be deleted immediately afterwards.*

---

## Running the API

Start the PHP built-in web server:
```bash
php -S localhost:8000 -t public/
```
The API will be available at `http://localhost:8000`.

---

## Running the Concurrency / Race Condition Test

A command-line script is provided to test the API's behavior under concurrent requests:
1. Ensure the API server is running (`php -S localhost:8000 -t public/`).
2. Run the test script in a separate terminal:
   ```bash
   php tests/RaceConditionTest.php
   ```
This script initializes a product with exactly **5 items** in stock, triggers **20 parallel order requests** using curl multi, and asserts that:
- Exactly 5 orders succeed (HTTP 201).
- 15 orders fail (HTTP 422 - Out of stock).
- Final product stock in database is exactly 0.
- No duplicate or excess orders are created.
