# University Memo System

A comprehensive digital memo management system for the University of Ibadan, featuring template-based memos, PDF annotations, sticky notes, and collaborative workflows.

![University of Ibadan](./assets/ui_logo.png)

## Features

### Core Functionality
- ✅ **Template Memos** - Official letterhead with auto-filled headers
- ✅ **PDF Upload** - Support for PDF and image documents
- ✅ **Annotations** - Drawing tools (pen, highlighter, arrows, rectangles)
- ✅ **Sticky Notes** - Collaborative notes with assignments and replies
- ✅ **Routing** - Internal unit, peer-to-peer, and global broadcasts
- ✅ **Forwarding** - Selective visibility control for annotations and notes
- ✅ **Classifications** - Standard, Executive, and Confidential marking
- ✅ **Staff Directory** - Searchable organization structure
- ✅ **Table Support** - Insert formatted tables in template memos
- ✅ **Multi-page** - Automatic pagination for long memos

### User Roles
- **Super Admin** - Full system access and user management
- **Admin** - Department/unit management
- **Unit Head** - Unit-level administration
- **Staff** - Create and manage memos

## Quick Start

### Demo Mode (No Backend Required)

```bash
cd web
npm install
npm run dev
```

Open `http://localhost:5173` and login with:
- **Email**: ST001@ui.edu.ng
- **Password**: ChangeMe#2026

### With Laravel Backend

See **[BACKEND_INTEGRATION.md](./BACKEND_INTEGRATION.md)** for complete setup instructions.

Quick setup:

```bash
# Backend
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve

# Frontend (new terminal)
cd web
npm install
cp .env.example .env
# Edit .env: set VITE_USE_MOCK=false
npm run dev
```

## Project Structure

```
ui-memo/
├── api/                    # Laravel backend
│   ├── app/
│   │   ├── Actions/       # Memo business logic
│   │   ├── Http/Controllers/
│   │   ├── Models/
│   │   └── Services/      # Chain visibility, routing guard
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   └── routes/api.php
│
├── web/                    # React frontend
│   ├── public/
│   │   └── ui_logo.png
│   ├── src/
│   │   ├── app/           # Router, layouts, guards
│   │   ├── features/      # Feature modules
│   │   │   ├── admin/     # Organization & user management
│   │   │   ├── auth/      # Login & authentication
│   │   │   ├── inbox/     # Memo list
│   │   │   ├── memo-compose/   # Create memos
│   │   │   ├── memo-template/  # Template editor
│   │   │   └── memo-viewer/    # View & annotate memos
│   │   ├── mock/          # Demo data (development only)
│   │   └── shared/        # Utilities, API client, UI components
│   ├── .env.example
│   └── package.json
│
├── assets/                 # Static assets
├── docs/                   # Documentation
├── scripts/                # Setup scripts
├── BACKEND_INTEGRATION.md  # Integration guide
└── README.md              # This file
```

## Technology Stack

### Frontend
- **React 18** with TypeScript
- **React Router** for navigation
- **TanStack Query** for API state management
- **Zustand** for local state
- **Tailwind CSS** for styling
- **PDF.js** for PDF rendering
- **Vite** for build tooling
- **Vitest** for testing

### Backend
- **Laravel 10+**
- **MySQL 8.0+**
- **Laravel Sanctum** for authentication
- **Queue Jobs** for async processing

## Configuration

### Environment Variables

**Frontend (`web/.env`):**

```env
# Demo mode (no backend)
VITE_USE_MOCK=true
VITE_API_BASE=/api/v1

# Production (with Laravel backend)
VITE_USE_MOCK=false
VITE_API_BASE=https://your-domain.edu.ng/api/v1
```

**Backend (`api/.env`):**

```env
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_DATABASE=ui_memo
SANCTUM_STATEFUL_DOMAINS=localhost:5173
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

## Development

### Frontend Development

```bash
cd web
npm run dev          # Start dev server
npm run build        # Production build
npm run test         # Run tests
npm run lint         # Lint code
```

### Backend Development

```bash
cd api
php artisan serve              # Start server
php artisan queue:work         # Process jobs
php artisan test               # Run tests
php artisan migrate:fresh --seed  # Reset database
```

## Testing

### Demo Accounts

| Role | Email | Password |
|------|-------|----------|
| Super Admin | SA001@ui.edu.ng | ChangeMe#2026 |
| Vice-Chancellor | VC001@ui.edu.ng | ChangeMe#2026 |
| ICT Head | HD001@ui.edu.ng | ChangeMe#2026 |
| Staff (Ada) | ST001@ui.edu.ng | ChangeMe#2026 |
| Staff (Tunde) | ST002@ui.edu.ng | ChangeMe#2026 |

### Test Scenarios

1. **Create Template Memo**
   - Login as staff
   - Go to "New memo"
   - Fill in template fields
   - Preview shows official letterhead
   - Send to specific recipients

2. **Annotate Memo**
   - Open received memo
   - Use toolbar to draw highlights, add arrows
   - Place sticky notes with assignments
   - Notes auto-save

3. **Forward Memo**
   - Open memo
   - Click "Forward"
   - Select recipients
   - Control annotation/note visibility
   - Add forward message

4. **Admin Functions**
   - Login as admin
   - Create departments and units
   - Add users with roles
   - Upload department logos

## Deployment

### Build for Production

```bash
cd web
npm run build
# Output: web/dist/
```

### Deploy Static Files

Copy `web/dist/` contents to your web server or CDN.

### Deploy Laravel Backend

```bash
cd api
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

See **[BACKEND_INTEGRATION.md](./BACKEND_INTEGRATION.md)** for Nginx configuration and production setup.

## Documentation

- **[BACKEND_INTEGRATION.md](./BACKEND_INTEGRATION.md)** - Complete API integration guide
- **[docs/LAUNCH.md](./docs/LAUNCH.md)** - Deployment checklist
- **[api/routes/api.php](./api/routes/api.php)** - API endpoints reference

## Key Concepts

### Routing System

Memos flow through a hierarchical routing tree:

- **Origin route** - Created when memo is sent
- **Forward routes** - Created when users forward to others
- Each route has visibility rules controlling what recipients see

### Visibility Control

When forwarding, users can:

- Show/hide specific annotations
- Show/hide sticky notes
- Hide executive or confidential content
- Add custom visibility rules per item

### Template System

Template memos store structured data:

- Department letterhead (auto-filled)
- Editable FROM fields
- Editable signature block
- Rich text body with table support
- Rendered as paginated A4 sheets

### Annotation Layers

Three independent layers on each page:

1. **Document layer** - PDF content or template rendering
2. **Draw layer** - Vector annotations (SVG)
3. **Note layer** - Positioned sticky notes (absolute positioning)

## Browser Support

- Chrome/Edge 100+
- Firefox 100+
- Safari 15+

## License

Proprietary - University of Ibadan

---

**Built for University of Ibadan** | **2026**
