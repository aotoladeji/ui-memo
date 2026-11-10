# University Memo System - Frontend

React + TypeScript frontend for the University of Ibadan Memo System.

## Quick Start

### Demo Mode (Standalone)

```bash
npm install
npm run dev
```

Open `http://localhost:5173` - No backend required!

### With Laravel Backend

```bash
npm install
cp .env.example .env
# Edit .env: set VITE_USE_MOCK=false
npm run dev
```

Ensure Laravel backend is running at `http://localhost:8000`.

## Available Scripts

| Command | Description |
|---------|-------------|
| `npm run dev` | Start development server (demo mode) |
| `npm run build` | Production build → `dist/` |
| `npm run preview` | Preview production build locally |
| `npm run test` | Run Vitest tests |
| `npm run lint` | Lint code with ESLint |
| `npm run typecheck` | TypeScript type checking |

## Environment Configuration

### `.env` File

```env
# Mock mode (true = demo, false = connect to Laravel)
VITE_USE_MOCK=true

# API base URL
VITE_API_BASE=/api/v1
```

### Modes

**Demo Mode** (`VITE_USE_MOCK=true`):
- Self-contained demo
- Mock data in localStorage
- No backend needed
- Perfect for presentations

**API Mode** (`VITE_USE_MOCK=false`):
- Connects to Laravel backend
- Real database persistence
- Full production features

## Project Structure

```
src/
├── app/                    # Application shell
│   ├── layouts/           # AppLayout (nav, sidebar)
│   ├── pages.tsx          # Lazy-loaded page components
│   ├── router.tsx         # React Router setup
│   ├── guards.tsx         # Auth & role guards
│   └── Root.tsx           # App entry point
│
├── features/              # Feature modules
│   ├── admin/            # Organization & user management
│   │   ├── OrgPage.tsx   # Faculties, departments, units
│   │   └── PeoplePage.tsx # User management
│   │
│   ├── auth/             # Authentication
│   │   ├── LoginPage.tsx # Login UI
│   │   └── authStore.ts  # Auth state (Zustand)
│   │
│   ├── inbox/            # Memo list
│   │   └── MemoListPage.tsx
│   │
│   ├── memo-compose/     # Create new memos
│   │   └── ComposePage.tsx
│   │
│   ├── memo-template/    # Template components
│   │   ├── TemplateForm.tsx   # Template editor
│   │   ├── TemplateSheet.tsx  # Rendered template
│   │   ├── TableBuilder.tsx   # Table insertion
│   │   └── StaffAutocomplete.tsx
│   │
│   └── memo-viewer/      # View & annotate memos
│       ├── MemoPage.tsx  # Main viewer
│       ├── components/
│       │   ├── DocumentStage.tsx  # PDF viewer
│       │   ├── TemplateStage.tsx  # Template viewer
│       │   ├── Toolbar.tsx        # Annotation tools
│       │   ├── NoteThreadPanel.tsx # Sticky notes
│       │   ├── ForwardModal/      # Forward dialog
│       │   ├── SendDialog.tsx     # Send draft
│       │   └── layers/            # Canvas layers
│       ├── store/
│       │   ├── editorStore.ts     # Annotation state
│       │   └── loadDocument.ts    # PDF loading
│       └── hooks/
│           └── useAutosave.ts     # Auto-save hook
│
├── shared/                # Shared utilities
│   ├── api.ts            # Axios client & mock router
│   ├── types.ts          # TypeScript types
│   ├── memoTemplate.ts   # Template utilities
│   ├── UserPicker.tsx    # Staff search component
│   ├── ui.tsx            # Reusable UI components
│   └── useDocumentTitle.ts
│
├── mock/                 # Demo data (only loaded if VITE_USE_MOCK=true)
│   ├── db.ts            # In-memory database
│   ├── handlers.ts      # API endpoint handlers
│   └── pdf.ts           # Sample PDF generator
│
└── test/                # Tests
    └── template.test.tsx
```

## Key Technologies

- **React 18** - UI library
- **TypeScript** - Type safety
- **React Router** - Client-side routing
- **TanStack Query** - Server state management
- **Zustand** - Client state management
- **Tailwind CSS** - Styling
- **PDF.js** - PDF rendering
- **Vite** - Build tool
- **Vitest** - Testing

## Features

### Template Memos
- Official University of Ibadan letterhead
- Auto-populated headers (department, logo)
- Editable FROM and signature fields
- Rich text with table support
- Multi-page automatic pagination

### Annotations
- **Pen** - Freehand drawing
- **Highlighter** - Transparent overlays
- **Arrow** - Directional indicators
- **Rectangle** - Area highlighting
- **Sticky Notes** - Positioned comments

### Sticky Notes
- Colored notes (yellow, pink, golden)
- Threaded replies
- Task assignments
- Status tracking (open/resolved)
- Classification (standard/executive/confidential)

### Forwarding
- Select recipients
- Control annotation visibility
- Control note visibility
- Hide classifications
- Add forward message
- Preview before sending

## State Management

### Server State (TanStack Query)

```typescript
// Query example
const { data, error } = useQuery({
  queryKey: ['memos', box],
  queryFn: async () => (await api.get(`/memos?box=${box}`)).data
});

// Mutation example
const send = useMutation({
  mutationFn: (id: number) => api.post(`/memos/${id}/send`),
  onSuccess: () => queryClient.invalidateQueries(['memos'])
});
```

### Client State (Zustand)

```typescript
// Auth store
const me = useAuth(s => s.me);
const login = useAuth(s => s.login);
const logout = useAuth(s => s.logout);

// Editor store (memo viewer)
const tool = useEditor(s => s.tool);
const annotations = useEditor(s => s.annotations);
const addAnnotation = useEditor(s => s.addAnnotation);
```

## Testing

Run tests:

```bash
npm run test
```

Test coverage includes:
- Template memo creation
- Authentication flow
- Role-based access
- Annotation creation
- Sticky note threading
- Forward visibility rules

## Building for Production

```bash
npm run build
```

Output in `dist/` directory. Deploy to any static host (Nginx, Apache, CDN).

### Production Checklist

1. Update `.env.production`:
   ```env
   VITE_USE_MOCK=false
   VITE_API_BASE=https://your-domain.edu.ng/api/v1
   ```

2. Build:
   ```bash
   npm run build
   ```

3. Test build locally:
   ```bash
   npm run preview
   ```

4. Deploy `dist/` folder to web server

## Browser Support

- Chrome/Edge 100+
- Firefox 100+
- Safari 15+

## Troubleshooting

### API Connection Issues

**Problem:** Frontend can't connect to backend

**Check:**
1. Backend running? `http://localhost:8000/api/v1/auth/me`
2. CORS configured in Laravel? Check `config/cors.php`
3. Environment variable set? `VITE_USE_MOCK=false`

### Mock Mode Not Working

**Problem:** Changes not persisting in demo mode

**Solution:** Check browser localStorage. Clear it to reset:
```javascript
localStorage.clear();
```

### PDF Not Rendering

**Problem:** PDF pages show blank

**Solution:** Check browser console for CORS errors. PDFs must be served from same origin or with proper CORS headers.

### Sticky Notes Not Saving

**Problem:** Notes disappear on page reload

**Solution:** Check autosave hook is running. Look for network requests to `/memos/:id/annotations`.

## Contributing

1. Create feature branch
2. Make changes
3. Run tests: `npm run test`
4. Typecheck: `npm run typecheck`
5. Lint: `npm run lint`
6. Build: `npm run build`
7. Submit PR

## License

Proprietary - University of Ibadan
