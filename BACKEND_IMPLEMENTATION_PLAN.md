# University Memo System: Laravel Backend Implementation Plan

## 1. Executive summary

The repository already contains useful Laravel building blocks for authentication, memo routing, forwarding visibility, notes, annotations, admin scopes, and audit logs. The core routing and visibility design has meaningful feature-test coverage.

The current backend cannot yet replace the demo end to end. The React app calls endpoints that Laravel does not define, including template creation and editing, minutes, department logos, and deleting notes. The Laravel memo creation endpoint only accepts file uploads, while the UI creates both template and uploaded memos. Memo detail responses also omit template data and minutes that the frontend expects.

There is a second foundation gap: `api/` contains Laravel application fragments, but its root currently has only `app/`, `database/`, `routes/`, and `tests/`. It has no `composer.json`, `artisan`, `bootstrap/`, or `config/` directory, so it is not a complete runnable Laravel project as checked into this repository. Establishing or restoring that project skeleton is an initial dependency.

## 2. Repository findings

- The frontend selects mock mode through `VITE_USE_MOCK`; otherwise it sends bearer tokens to `VITE_API_BASE`. Vite proxies `/api` to `localhost:8000` in development. See `web/src/shared/api.ts:3` and `web/vite.config.ts:5`.
- The mock backend stores demo state in browser `localStorage`; uploaded files are held in memory. Its mock data describes demo users, roles, units, memos, routes, annotations, notes, and minutes. See `web/src/mock/db.ts:1`.
- The existing Laravel route file covers authentication, search, memo lifecycle, annotations, notes and replies, forwarding, and some administration. See `api/routes/api.php:7`.
- The Laravel backend has service/action boundaries already in use: memo creation, send and forward actions, a routing guard, and chain visibility filtering. See `api/app/Actions/InitiateMemo.php:11`, `api/app/Actions/ForwardMemo.php:9`, and `api/app/Services/ChainVisibility.php:7`.
- The schema contains organization, user, memo, document, route, recipient, annotation, sticky note, reply, visibility rule, token, and audit tables. It has no minute tables and no template payload or department-logo storage fields. See `api/database/schema/memo_system.sql:1`.
- The migration executes raw MySQL DDL, including `ENGINE=InnoDB`, `ENUM`, `JSON_CONTAINS` usage elsewhere, and MySQL-specific foreign-key handling. That conflicts with the integration guide’s PostgreSQL option. See `api/database/migrations/2026_01_01_000000_create_memo_system_tables.php:6` and `api/database/schema/memo_system.sql:1`.
- The integration guide documents a useful API outline, but source code reveals missing routes and contract differences. Treat the guide as a draft, not the contract of record.

## 3. Feature coverage matrix

| Feature | Frontend/mock behavior | Existing Laravel support | Gaps and recommended work |
|---|---|---|---|
| Authentication | Login, restore current user, logout; bearer token stored in local storage. `web/src/features/auth/authStore.ts:12` | Sanctum login/me/logout and bearer tokens are implemented. `api/app/Http/Controllers/Api/V1/AuthController.php:12` | Choose and document token lifecycle, expiry/revocation, and whether the deployment uses bearer tokens or Sanctum’s cookie-based SPA flow. Current frontend expects bearer tokens. |
| Staff search | Search by name or staff number for recipient pickers. `web/src/shared/UserPicker.tsx:8` | `GET /users/search` is implemented. `api/app/Http/Controllers/Api/V1/UserDirectoryController.php:12` | Confirm self/super-admin visibility and minimum query length. The frontend’s “directory” concept currently appears to be search rather than a separate directory page. |
| Inbox, sent, drafts | Fetches a selected box and expects a `data` list with unread status. `web/src/features/inbox/MemoListPage.tsx:41` | `GET /memos?box=...` filters and paginates. `api/app/Http/Controllers/Api/V1/MemoController.php:14` | Laravel returns pagination metadata and does not include the mock’s `unread` field. Align response shape and define whether “sent” means authored or all forwarded memos. |
| Template memo creation/editing | Creates JSON template drafts and edits a draft through `PATCH /memos/{id}/template`. `web/src/features/memo-compose/ComposePage.tsx:69`, `web/src/features/memo-viewer/components/EditTemplateDialog.tsx:27` | No template-specific route or payload storage exists. `POST /memos` requires a file. `api/app/Http/Controllers/Api/V1/MemoController.php:29` | Add template data persistence, validation, create/update rules for drafts, and a stable response contract. The UI’s fields include `to`, `thru`, `cc`, date, body, office line, sender data, and signature. `web/src/shared/memoTemplate.ts:6` |
| Uploaded documents | Accepts PDF, Word, and image files; sends measured page count/dimensions. `web/src/features/memo-compose/ComposePage.tsx:97` | Laravel stores uploaded files and serves them after a recipient check. It currently validates PDF/PNG/JPG only. `api/app/Http/Controllers/Api/V1/MemoController.php:29` | Decide whether Word files are supported in production and, if so, specify conversion and queue behavior. The current API rejects formats accepted by the UI. Add robust MIME/content validation, size limits, storage policy, and cleanup on transaction failure. |
| Memo details and file access | Expects document kind/template, minutes, annotations, notes, replies, and author details. `web/src/shared/types.ts:32` | Detail/file endpoints enforce recipient access and filter notes/annotations. `api/app/Http/Controllers/Api/V1/MemoController.php:51` | Laravel detail omits minutes and minute replies, and does not return template metadata. Its document URL always points to the file endpoint. Match `MemoView` and distinguish template from file documents. |
| Send and routing | Sends drafts to selected recipients; global and internal-unit routing can infer recipient sets in mock behavior. `web/src/mock/handlers.ts:299` | Send and queued recipient fan-out exist. `api/app/Actions/SendMemo.php:14` | Clarify required recipients by routing type, include/exclude sender rules, and delivery semantics. Make state transition plus job dispatch reliable and idempotent. |
| Forwarding and visibility | Preview and forward with recipient list, message, annotation/note modes, classifications, and per-item rules. `web/src/features/memo-viewer/components/ForwardModal/ForwardModal.tsx:49` | Forwarding, preview, and chain filtering are implemented. `api/app/Http/Controllers/Api/V1/ForwardController.php:14`, `api/app/Models/Concerns/ChainScoped.php:13` | Test preview for multiple recipients: it currently previews only the first recipient. Define route selection consistently; current frontend sends route IDs in some calls, while preview/forward uses `from_route_id`. |
| Annotations | Debounced batch save and author delete. `web/src/features/memo-viewer/hooks.ts:6`, `web/src/features/memo-viewer/components/layers/DrawLayer.tsx:65` | Batch upsert and delete exist. `api/app/Http/Controllers/Api/V1/AnnotationController.php:12` | Confirm the UI tool names map cleanly to backend enum values; validate geometry and page bounds; enforce visible-chain access on delete and ownership/route rules on updates. |
| Sticky notes and replies | Create, move/edit, resolve, reply, and delete. `web/src/features/memo-viewer/components/layers/NoteLayer.tsx:77` | Create, patch, and reply exist. `api/app/Http/Controllers/Api/V1/NoteController.php:12` | `DELETE /notes/{id}` is called by the UI but absent from Laravel routes and mock handlers. Add deletion policy and audit behavior. Define whether note color/classification/visibility can be edited after creation. |
| Minutes and replies | Create, move, delete, and reply; detail includes minutes and authors. `web/src/features/memo-viewer/components/layers/MinuteLayer.tsx:89` | No Laravel model, migration, controller, or routes found. | Add minute and minute-reply data model, endpoints, visibility rules, and detail serialization. Establish whether minutes follow the same chain/classification rules as notes or have separate rules. |
| Organization management | Loads the tree and uploads department logos; the UI calls department creation. `web/src/features/admin/OrgPage.tsx:80` | Org tree and faculty/department/unit CRUD exist. `api/routes/api.php:35` | Add `POST /admin/departments/{id}/logo` and `GET /departments/{id}/logo`, with scoped authorization and storage. Confirm which CRUD operations the UI exposes. |
| User administration and permissions | Lists users and toggles global broadcast permission. `web/src/features/admin/PeoplePage.tsx:9`, `web/src/features/permissions/PermissionManager.tsx:10` | User CRUD, scope checks, permission audit, and scope routes exist. `api/app/Http/Controllers/Api/V1/Admin/UserAdminController.php:14` | Verify the UI’s expected response envelope: it reads `{data: [...]}`, while Laravel `paginate()` uses a Laravel pagination object. Add explicit API resources and test the admin scope boundaries. |
| Demo/mock contract | Mock has template creation and basic minutes, but route declarations omit several UI-called operations. `web/src/mock/handlers.ts:114` | — | Treat the UI calls and frontend types as the integration contract to reconcile; the mock is not a complete specification. |

## 4. Proposed API and data model

### Recommended architecture

Keep controllers thin and use:

- Form Requests for validation.
- Policies or focused authorization services for memo, route, note, annotation, organization, and user access.
- Actions/services for memo lifecycle transitions, fan-out, forwarding, visibility previews, and file handling.
- API Resources/DTOs to make frontend response shapes deliberate and stable.
- Transactions for state changes and recipient/route creation; dispatch queued work after commit.
- A shared API error format that preserves Laravel validation errors expected by `errorText()`.

Existing actions and services can be retained while being brought under a complete Laravel application structure.

### Proposed endpoint inventory

All endpoints below should be under `/api/v1`. Authenticated endpoints use the current bearer-token approach unless the authentication decision changes.

| Method and route | Purpose and high-level contract | Access |
|---|---|---|
| `POST /auth/login` | Credentials → token and user profile | Public, throttled |
| `GET /auth/me` | Current user profile with role and org labels | Authenticated |
| `POST /auth/logout` | Revoke current token | Authenticated |
| `GET /users/search?q=` | Search active staff → user-lite list | Authenticated; define eligible users |
| `GET /memos?box=inbox\|sent\|drafts` | Paginated memo summaries including unread/read state and author | Authenticated; recipient/author scoping |
| `POST /memos` | Create draft from either `{kind:"template", template, ...}` or multipart file data → `{id, ref_no}` | Authenticated; validate routing rights |
| `PATCH /memos/{memo}/template` | Update template data and subject on an editable draft | Author, draft, template kind |
| `GET /memos/{memo}` | Memo, selected route, document metadata, visible notes/replies/annotations/minutes/authors | Recipient on selected route |
| `GET /memos/{memo}/file` | Stream/download original document | Recipient; private storage |
| `POST /memos/{memo}/send` | `{recipient_ids}` → status; transition draft and deliver recipients | Author of draft; routing guard |
| `POST /memos/{memo}/complete` | Mark current recipient route done; reject unresolved assigned action notes | Recipient on selected route |
| `POST /memos/{memo}/forward` | Forward payload → `{forward_id}` | Current recipient; only reference visible items |
| `POST /memos/{memo}/forward-preview` | Same payload → visible-item preview, without persistence | Current recipient |
| `PUT /memos/{memo}/annotations` | Batch upsert `{items, route_id}` → client-ID/server-ID map | Recipient; author owns edited items |
| `DELETE /annotations/{annotation}` | Delete annotation | Author and authorized memo route |
| `POST /memos/{memo}/notes` | Create positioned note with classification, visibility, optional assignee | Recipient; assignee must hold memo |
| `PATCH /notes/{note}` | Move/edit or resolve note | Visible note; author edits, author/assignee resolves |
| `DELETE /notes/{note}` | Delete note | Define author/admin policy and audit |
| `POST /notes/{note}/replies` | `{body, route_id}` → reply | User can see note on route |
| `POST /memos/{memo}/minutes` | Create positioned minute | Memo recipient; confirm separate visibility policy |
| `PATCH /minutes/{minute}` | Move/update minute | Define author/recipient policy |
| `DELETE /minutes/{minute}` | Delete minute | Define author/recipient policy and audit |
| `POST /minutes/{minute}/replies` | `{body, route_id}` → reply | User can see minute |
| `GET /admin/org` | Faculty → department → unit tree | Admin within scope; super admin unrestricted |
| `POST/PATCH/DELETE /admin/faculties` | Faculty CRUD | Super admin |
| `POST/PATCH/DELETE /admin/departments` | Department CRUD | Scoped admin/super admin |
| `POST/PATCH/DELETE /admin/units` | Unit CRUD | Scoped admin/super admin |
| `POST /admin/departments/{department}/logo` | Upload validated logo → metadata | Scoped admin/super admin |
| `GET /departments/{department}/logo` | Serve logo or return not found | Authenticated; confirm public/private policy |
| `GET/POST/PATCH/DELETE /admin/users` | List/create/update/deactivate users | Scoped admin/super admin |
| `PATCH /admin/users/{user}/permissions` | Change global broadcast permission and audit it | Super admin |
| `POST /admin/admins/{user}/scopes`, `DELETE /admin/admins/{user}/scopes/{scope}` | Assign/remove faculty or department scope | Super admin |

Use explicit resources to align pagination and field names with `web/src/shared/types.ts`; avoid returning raw Eloquent models as a long-term API contract.

### Data model

Reuse and extend the current tables:

- **Organization and identity:** `faculties`, `departments`, `units`, `users`, `admin_scopes`, and `personal_access_tokens`.
- **Memo lifecycle:** `memos`, `memo_documents`, `memo_routes`, `memo_recipients`, and `memo_forward_visibility_rules`.
- **Collaboration:** `annotations`, `sticky_notes`, and `sticky_note_replies`.
- **Operations:** `audit_logs` and `permission_audit_logs`.

Add or revise:

- `memo_documents`: document `kind`, JSON `template_data`, optional `storage_path` for templates, and clear original/converted-file metadata. The existing document table requires a storage path and has no template fields.
- Department logo metadata/storage: either fields on `departments` or a dedicated media table, with storage kept outside the public web root.
- Minutes and minute replies: tables with memo, route, author, page/location, body, classification, timestamps, and the chosen visibility/soft-delete semantics.
- Delivery/idempotency support if queued fan-out needs retry-safe status and monitoring.
- Constraints and indexes for route-recipient uniqueness, memo/page queries, unread inbox queries, and audit lookup.

Adopt framework migrations rather than relying on a single raw DDL migration if portability or safe incremental evolution matters.

## 5. Phased implementation plan

1. **Restore the Laravel foundation.** Confirm Laravel/PHP versions, add or restore the missing project bootstrap/configuration/dependency manifest, choose the database, and make migrations reproducible. Set environment, CORS, storage, queue, and Sanctum configuration.
2. **Lock the API contract.** Use frontend calls/types to document payloads, response envelopes, status codes, routing semantics, and error shapes. Resolve mock/API mismatches before implementation.
3. **Complete identity and organization.** Validate token behavior, profile shape, directory search, admin scopes, org CRUD, user CRUD, permission auditing, and logo endpoints.
4. **Complete memo creation and lifecycle.** Support both template and file drafts, template editing, file validation/storage, memo listing/detail, send, read state, and completion. Add transactional/idempotent fan-out behavior.
5. **Complete collaboration.** Harden annotation and note operations, add note deletion, then implement minutes and replies. Confirm consistent route and visibility handling.
6. **Harden forwarding.** Verify recursive visibility masks, explicit include/exclude precedence, preview semantics, multi-recipient behavior, and route selection. Preserve the existing privacy-focused tests and add the missing cases.
7. **Cut over and operate.** Test the real frontend against Laravel with `VITE_USE_MOCK=false`, resolve response-shape mismatches, configure production storage and queue workers, monitor failures, then disable mock mode for the production build.

## 6. Security and operational considerations

- Enforce memo access through recipient rows and return 404 for inaccessible memos/items to avoid exposing existence. The current visibility service follows this pattern for memo routes.
- Validate a selected `route_id` against the authenticated user and memo on every route-sensitive write, read, preview, and delete. Do not rely on UI filtering.
- Preserve chain visibility rules for annotations and notes; private items should be visible only to their author unless product requirements say otherwise. Never serialize masked items.
- Validate forward-rule targets against items the sender can currently see. Test ancestor, descendant, and sibling branches.
- Confirm who can edit, resolve, delete, and reassign notes/minutes; validate page numbers against document page count and coordinates against the UI’s normalized range.
- Check the recipient belongs to the memo and is active; enforce internal-unit/global-broadcast policy on both create/send and forwarding.
- Wrap memo transitions and related rows in transactions. Lock or conditionally update drafts to prevent duplicate sends; dispatch fan-out only after commit and make jobs retry-safe.
- Store uploads privately, verify content rather than trusting extensions, constrain size/type, use non-guessable paths, and authorize every stream or temporary download URL. Clean up orphaned files after failed DB writes.
- Use structured audit events for send, forward, view/read, completion, permission changes, and administrative changes. Avoid logging memo contents or credentials.
- Add rate limits to login and high-volume writes, plus queue failure monitoring and backup/retention policy.
- Decide whether production should use MySQL only. The current schema and visibility query are MySQL-specific despite PostgreSQL being mentioned in the integration guide.

## 7. Risks, assumptions, and open decisions

### Repository facts

- The frontend supports template and uploaded-file drafts; Laravel currently requires a file.
- The UI calls minutes CRUD/replies, template editing, note deletion, and logo routes that Laravel does not expose.
- The backend detail response lacks frontend-required minutes and template document data.
- The raw schema is MySQL-specific and has no minutes or template-data fields.
- The Laravel directory lacks the usual root project files needed to run it as a standalone app.

### Assumptions requiring product confirmation

- Template drafts should be stored as structured JSON and rendered by the existing frontend rather than converted to PDF on the backend.
- Word document support should match the UI’s accepted file formats, likely through server-side PDF conversion.
- Minutes should be persisted and visible to memo recipients, but their relationship to forwarding masks and classifications is not specified.
- A note author may delete their note; an admin or recipient may have additional rights only if explicitly required.
- Logo files are institution-managed content and can be served to authenticated users who can view the organization tree.
- Inbox unread state is per user and route/recipient row; completion state is also per recipient.

## 8. Acceptance criteria

- `api/` is a complete, reproducible Laravel project with documented environment setup and migrations for the selected database.
- Every frontend API call has a Laravel route, documented request/response contract, and matching authorization behavior.
- Template and file drafts can be created, viewed, edited while draft, and sent; file retrieval remains private.
- Inbox, sent, and drafts match the UI’s expected pagination and unread fields.
- Memo details return all frontend-required document, author, annotation, note, reply, minute, and minute-reply data without leaking masked content.
- Sending and forwarding enforce routing permissions and create retry-safe recipient records.
- Note/annotation/minute actions enforce route visibility, ownership, assignment, and resolution rules.
- Organization, logo, user, scope, and permission workflows function with scoped admin authorization and audit events.
- Feature tests cover authentication, validation, memo lifecycle, access control, branching visibility, file access, admin scope, and queue behavior.
- The frontend runs against Laravel with mock mode disabled, and its current UI workflows succeed without undocumented contract workarounds.
