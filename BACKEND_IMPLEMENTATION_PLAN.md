# University Memo System: Backend Implementation Status

## Summary

The repository contains a runnable Laravel API in `api/`, the React client in `web/`, and focused feature coverage for memo lifecycle, visibility, administration, uploads, and pagination. The backend improvements in this plan have been implemented; this document records their current behavior and the remaining operational work rather than treating implemented features as missing.

## Current implementation findings

- Memo routes and feature tests are implemented. Memo detail serializes only items visible to the authenticated user on the selected route. Route IDs are checked against both the memo and the user's recipient rows.
- Minutes follow strict route-branch visibility: a minute or reply is visible only on the same route or an ancestor/descendant route, and never on a sibling branch, including when its author holds both sibling routes. An author's visibility override can bypass masks only on a related route path. Existing author visibility overrides for notes and annotations are unchanged. Replies are additionally restricted to visible parent minutes.
- Sending locks and conditionally updates the draft, and commits the sent state, audit event, and unique outbox record together. Recipient fan-out is idempotent and retryable; `memos:retry-outbox` is scheduled once per minute.
- Forward preview returns per-recipient results. Its legacy top-level visibility arrays remain the first selected recipient's result for compatibility.
- Department create/delete and other organization writes use the existing SuperAdmin and scoped-Admin boundaries. A department-scoped Admin can manage only departments explicitly in scope; creating a department requires scope over its faculty.
- Inbox, sent, and draft lists use bounded pagination: default 20, maximum 100, with unread state and existing row fields retained.
- Memo file uploads support PDF, PNG, and JPEG only. Word documents are rejected by both client and API; no Word conversion is provided. Uploaded files are deleted if the memo database transaction fails, and file access remains private and recipient-authorized.
- Laravel migrations and the visibility queries execute on the configured SQLite test/local path. MySQL remains the documented production database; driver-specific visibility SQL is covered by a MySQL SQL-generation test. No PostgreSQL support is claimed.

## Feature and API behavior

| Area | Current behavior |
|---|---|
| Minute visibility and authorization | `GET /memos/{memo}` returns only visible minutes and minute replies. `POST /minutes/{minute}/replies`, `PATCH /minutes/{minute}`, and `DELETE /minutes/{minute}` require a route belonging to the authenticated user's recipient row for that memo; hidden and sibling-branch items return 404. Only the minute author can edit/delete a visible minute. |
| Send and recipient fan-out | `POST /memos/{memo}/send` rejects repeat sends with 409. Draft status, `memo.sent` audit event, and outbox row commit atomically. Fan-out uses unique recipient rows and insert-or-ignore semantics. Pending/failed outbox records are retried by the scheduled `memos:retry-outbox` command. |
| Database | `.env.example` and PHPUnit default to SQLite. MySQL is supported and documented for deployment. Chain visibility uses driver-appropriate string-prefix and JSON expressions. |
| Forward preview | `POST /memos/{memo}/forward-preview` returns `recipients: [{recipient_id, recipient, notes, annotations, replies, minutes, minute_replies}]`. The top-level `notes`, `annotations`, `replies`, `minutes`, and `minute_replies` fields mirror the first selected recipient for older clients. |
| Department administration | SuperAdmin is unrestricted. Admins may only operate within their assigned faculty/department scope. Ordinary staff are denied. Empty departments can be deleted only by an authorized actor; departments with units return 409. |
| Memo-list pagination | `GET /memos?box=inbox|sent|drafts&page=1&per_page=20` returns `{data, meta, links}`. `per_page` below 1 is rejected; values above 100 are clamped. `unread` is calculated from the authenticated user's recipient read state for each page. “Sent” means authored, non-draft memos. |
| Upload validation and cleanup | API and UI accept PDF/PNG/JPEG only, up to 20 MiB. `.doc` and `.docx` must be exported to PDF first. Upload storage is private; a failed memo/document/route/recipient/audit database write rolls back and deletes the stored upload. |

## Verification

Focused Laravel feature tests cover sibling and hidden minute visibility, route selection and unauthorized edits/replies/deletes, repeated sends and retry/idempotency behavior, SQLite visibility execution and MySQL SQL generation, recipient-specific previews, admin scopes, pagination/unread state, upload validation, and cleanup after failed writes.

## Remaining work and operational limits

- Production must run a durable queue worker for asynchronous non-peer recipient fan-out and Laravel's scheduler (`php artisan schedule:run` every minute) so pending outbox rows are retried. The local `.env.example` uses the synchronous queue for development.
- SQLite execution is covered by the test suite and MySQL SQL generation is checked, but an actual MySQL migration/feature run should be included in deployment CI.
- Word document ingestion remains unsupported until a safe conversion and private-storage workflow is implemented end to end.
- Broader API Resource/DTO adoption and page-count validation for note/minute coordinates remain future hardening work; neither is represented as completed by this change.

## Relevant references

- Laravel environment and migration configuration: `api/.env.example`, `api/database/migrations/`
- Chain visibility and route authorization: `api/app/Models/Concerns/ChainScoped.php`, `api/app/Services/ChainVisibility.php`
- Send outbox: `api/app/Actions/SendMemo.php`, `api/app/Services/SendOutboxProcessor.php`, `api/app/Jobs/FanOutRecipients.php`
- API and client response shapes: `BACKEND_INTEGRATION.md`, `web/src/shared/types.ts`
