# Backend Integration Guide
## University Memo System - Laravel API Integration

This guide provides step-by-step instructions for integrating the React frontend with the Laravel backend.

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Backend Setup](#backend-setup)
3. [Frontend Configuration](#frontend-configuration)
4. [API Endpoints Reference](#api-endpoints-reference)
5. [Authentication Flow](#authentication-flow)
6. [File Upload Handling](#file-upload-handling)
7. [Database Schema](#database-schema)
8. [Testing the Integration](#testing-the-integration)
9. [Deployment](#deployment)
10. [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before starting, ensure you have:

- **PHP 8.1+** installed
- **Composer** (PHP dependency manager)
- **Node.js 18+** and npm
- **MySQL 8.0+** or PostgreSQL
- **Laravel 10+** backend in `./api` directory
- Basic knowledge of Laravel and React

---

## Backend Setup

### Step 1: Install Laravel Dependencies

```bash
cd api
composer install
```

### Step 2: Configure Environment

Copy `.env.example` to `.env` and configure:

```bash
cp .env.example .env
```

Edit `.env` with your database credentials:

```env
APP_NAME="University Memo System"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ui_memo
DB_USERNAME=root
DB_PASSWORD=your_password

# Session & CORS
SESSION_DRIVER=database
SESSION_DOMAIN=localhost
SANCTUM_STATEFUL_DOMAINS=localhost:5173

# File Storage
FILESYSTEM_DISK=local
```

### Step 3: Generate Application Key

```bash
php artisan key:generate
```

### Step 4: Create Database

Create the database in MySQL:

```bash
mysql -u root -p
```

```sql
CREATE DATABASE ui_memo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

Or use the provided script:

```bash
mysql -u root -p < scripts/create-databases.sql
```

### Step 5: Run Migrations

```bash
php artisan migrate
```

### Step 6: Seed Demo Data (Optional)

```bash
php artisan db:seed --class=DemoSeeder
```

This creates demo users:
- **Super Admin**: SA001@ui.edu.ng / ChangeMe#2026
- **Vice-Chancellor**: VC001@ui.edu.ng / ChangeMe#2026
- **Staff**: ST001@ui.edu.ng / ChangeMe#2026

### Step 7: Start Laravel Server

```bash
php artisan serve
```

The API will run at `http://localhost:8000`

### Step 8: Start Queue Worker (for background jobs)

In a separate terminal:

```bash
php artisan queue:work
```

---

## Frontend Configuration

### Step 1: Install Frontend Dependencies

```bash
cd web
npm install
```

### Step 2: Configure Environment

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

For **development with Laravel backend**:

```env
VITE_USE_MOCK=false
VITE_API_BASE=/api/v1
```

For **demo mode** (no backend):

```env
VITE_USE_MOCK=true
VITE_API_BASE=/api/v1
```

### Step 3: Update Vite Proxy (if needed)

Open `web/vite.config.ts` and verify the proxy configuration:

```typescript
export default defineConfig({
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
});
```

### Step 4: Start Development Server

```bash
npm run dev
```

The frontend will run at `http://localhost:5173`

---

## API Endpoints Reference

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/login` | Login with email & password |
| GET | `/api/v1/auth/me` | Get current user info |
| POST | `/api/v1/auth/logout` | Logout current user |

### Memos

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/memos` | List memos (inbox/sent/drafts) |
| POST | `/api/v1/memos` | Create new memo (template or upload) |
| GET | `/api/v1/memos/:id` | View single memo |
| POST | `/api/v1/memos/:id/send` | Send draft memo |
| POST | `/api/v1/memos/:id/complete` | Mark memo as done |
| GET | `/api/v1/memos/:id/file` | Download memo file |

### Forwarding

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/memos/:id/forward` | Forward memo to recipients |
| POST | `/api/v1/memos/:id/forward-preview` | Preview forward visibility |

### Annotations

| Method | Endpoint | Description |
|--------|----------|-------------|
| PUT | `/api/v1/memos/:id/annotations` | Save annotations (bulk) |
| DELETE | `/api/v1/annotations/:id` | Delete single annotation |

### Sticky Notes

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/notes` | Create sticky note |
| PATCH | `/api/v1/notes/:id` | Update sticky note |
| POST | `/api/v1/notes/:id/replies` | Reply to note |

### Users

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/users/search` | Search staff by name/number |
| GET | `/api/v1/users/directory` | Get organization directory |

### Admin

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/org` | Get organization structure |
| POST | `/api/v1/admin/faculties` | Create faculty |
| POST | `/api/v1/admin/departments` | Create department |
| POST | `/api/v1/admin/units` | Create unit |
| GET | `/api/v1/admin/users` | List all users |
| POST | `/api/v1/admin/users` | Create new user |
| PATCH | `/api/v1/admin/users/:id` | Update user |
| PATCH | `/api/v1/admin/users/:id/permissions` | Update user permissions |

---

## Authentication Flow

### 1. Login Request

**Frontend sends:**

```typescript
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "ST001@ui.edu.ng",
  "password": "ChangeMe#2026"
}
```

**Backend responds:**

```json
{
  "token": "3|laravel_sanctum_token_here",
  "user": {
    "id": 5,
    "name": "Ada Okafor",
    "email": "st001@ui.edu.ng",
    "role": "staff",
    "title": "Systems Officer",
    "unit_id": 2,
    "is_executive": false,
    "can_send_global_broadcast": false
  }
}
```

### 2. Authenticated Requests

All subsequent requests include the token:

```typescript
GET /api/v1/memos
Authorization: Bearer 3|laravel_sanctum_token_here
```

### 3. Token Storage

The frontend stores the token in localStorage:

```typescript
localStorage.setItem('auth_token', response.data.token);
```

---

## File Upload Handling

### Template Memos (JSON)

**Request:**

```typescript
POST /api/v1/memos
Content-Type: application/json

{
  "kind": "template",
  "subject": "Memo subject",
  "routing_type": "peer",
  "priority": "normal",
  "page_count": 2,
  "page_dims": [{"w": 595, "h": 842}, {"w": 595, "h": 842}],
  "template": {
    "department_id": 1,
    "department_name": "Computer Science",
    "to": "Deputy Vice-Chancellor",
    "thru": "",
    "date": "2026-11-10",
    "office_line": "Office of the Head",
    "body": "Memo content...",
    "from_name": "Ada Okafor",
    "from_staff_no": "ST001",
    "signature_name": "Ada Okafor",
    "signature_title": "Systems Officer",
    "signature_unit": "ICT Unit"
  }
}
```

### File Upload Memos (PDF/Image)

**Request:**

```typescript
POST /api/v1/memos
Content-Type: multipart/form-data

FormData:
  - subject: "Memo subject"
  - routing_type: "peer"
  - priority: "normal"
  - file: [Binary file data]
  - page_count: 3
  - page_dims[0][w]: 612
  - page_dims[0][h]: 792
  - page_dims[1][w]: 612
  - page_dims[1][h]: 792
```

**Backend Processing:**

```php
// In MemoController.php
public function store(Request $request)
{
    if ($request->input('kind') === 'template') {
        // Handle template memo
        return $this->storeTemplateMemo($request);
    } else {
        // Handle file upload
        return $this->storeFileMemo($request);
    }
}
```

---

## Database Schema

### Key Tables

#### `users`
- `id` (PK)
- `staff_no` (unique)
- `name`
- `email` (unique)
- `password`
- `role` (super_admin, admin, unit_head, staff)
- `title`
- `unit_id` (FK → units)
- `is_executive` (boolean)
- `can_send_global_broadcast` (boolean)
- `is_active` (boolean)

#### `memos`
- `id` (PK)
- `ref_no` (unique, e.g., "UI/ICT/2026/0001")
- `subject`
- `created_by` (FK → users)
- `origin_unit_id` (FK → units)
- `routing_type` (internal_unit, peer, global)
- `priority` (normal, urgent)
- `status` (draft, sent, closed)
- `sent_at` (timestamp)

#### `memo_documents`
- `id` (PK)
- `memo_id` (FK → memos)
- `kind` (template, file)
- `mime_type`
- `file_key` (storage path)
- `page_count`
- `page_dims` (JSON array)
- `template_data` (JSON, for template memos)

#### `memo_routes`
- `id` (PK)
- `memo_id` (FK → memos)
- `parent_route_id` (FK → memo_routes, nullable)
- `depth` (integer)
- `path` (e.g., "/1/5/12/")
- `kind` (origin, forward)
- `from_user_id` (FK → users)
- `annotation_mode` (all, none)
- `note_mode` (all, none)
- `hidden_classifications` (JSON array)
- `message` (text, nullable)

#### `memo_recipients`
- `id` (PK)
- `memo_id` (FK → memos)
- `route_id` (FK → memo_routes)
- `user_id` (FK → users)
- `recipient_type` (author, to)
- `read_at` (timestamp, nullable)
- `action_state` (none, pending, done)

#### `annotations`
- `id` (PK)
- `memo_id` (FK → memos)
- `origin_route_id` (FK → memo_routes)
- `author_id` (FK → users)
- `page_number` (integer)
- `tool` (pen, highlighter, arrow, rectangle)
- `geometry` (JSON)
- `style` (JSON)
- `classification` (standard, executive, confidential)
- `visibility_scope` (chain, private)
- `z_index` (integer)

#### `sticky_notes`
- `id` (PK)
- `memo_id` (FK → memos)
- `origin_route_id` (FK → memo_routes)
- `author_id` (FK → users)
- `page_number` (integer)
- `x` (float, 0-1)
- `y` (float, 0-1)
- `body` (text)
- `color` (hex color)
- `classification` (standard, executive, confidential)
- `visibility_scope` (chain, private)
- `requires_action` (boolean)
- `assigned_to` (FK → users, nullable)
- `status` (open, resolved)

#### `sticky_note_replies`
- `id` (PK)
- `note_id` (FK → sticky_notes)
- `origin_route_id` (FK → memo_routes)
- `author_id` (FK → users)
- `body` (text)
- `created_at` (timestamp)

---

## Testing the Integration

### 1. Test Authentication

```bash
# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"ST001@ui.edu.ng","password":"ChangeMe#2026"}'

# Get current user
curl http://localhost:8000/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 2. Test Memo Creation

```bash
# Create template memo
curl -X POST http://localhost:8000/api/v1/memos \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "kind": "template",
    "subject": "Test Memo",
    "routing_type": "peer",
    "priority": "normal",
    "page_count": 1,
    "template": {
      "to": "Test Recipient",
      "date": "2026-11-10",
      "body": "Test content"
    }
  }'
```

### 3. Test Frontend Connection

1. Start both servers:
   ```bash
   # Terminal 1 - Backend
   cd api && php artisan serve

   # Terminal 2 - Frontend
   cd web && npm run dev
   ```

2. Open browser: `http://localhost:5173`
3. Login with demo credentials
4. Create a memo and verify it saves to database

---

## Deployment

### Production Build

#### Frontend

```bash
cd web
npm run build
```

This creates a `dist/` folder with optimized static files.

#### Backend

```bash
cd api
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name memo.youruniversity.edu.ng;
    root /var/www/ui-memo/web/dist;
    index index.html;

    # Frontend routes
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API proxy
    location /api {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    # File uploads
    client_max_body_size 20M;
}
```

### Environment Variables (Production)

**Backend (`.env`):**

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://memo.youruniversity.edu.ng

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_DATABASE=ui_memo_prod
DB_USERNAME=prod_user
DB_PASSWORD=secure_password

QUEUE_CONNECTION=database
```

**Frontend (`.env.production`):**

```env
VITE_USE_MOCK=false
VITE_API_BASE=https://memo.youruniversity.edu.ng/api/v1
```

---

## Troubleshooting

### CORS Errors

**Problem:** Frontend can't connect to API

**Solution:** Update `config/cors.php`:

```php
'allowed_origins' => ['http://localhost:5173', 'https://your-domain.edu.ng'],
'supports_credentials' => true,
```

### 401 Unauthorized Errors

**Problem:** Token not being sent

**Solution:** Check Sanctum configuration in `config/sanctum.php`:

```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:5173')),
```

### File Upload Fails

**Problem:** Large files rejected

**Solution:** Update `php.ini`:

```ini
upload_max_filesize = 20M
post_max_size = 25M
```

### Queue Jobs Not Running

**Problem:** Memo recipients not notified

**Solution:** Ensure queue worker is running:

```bash
php artisan queue:work --daemon
```

For production, use Supervisor to keep it running.

---

## Support

For issues or questions:

1. Check Laravel logs: `api/storage/logs/laravel.log`
2. Check browser console for frontend errors
3. Enable debug mode: `APP_DEBUG=true` in `.env`
4. Review API responses in Network tab

---

**University Memo System** - Built for University of Ibadan
