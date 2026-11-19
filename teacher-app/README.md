# حلقة — تطبيق الأستاذ

Separate Vue 3 / TypeScript / Vite app using the Laravel [teacher API](../docs/teacher-api.md), based on the Arabic RTL [prototype](../teacher-prototype/README.md).

## Run

Use Node 22.12+ and npm. Start Laravel/Sail, then:

```bash
cd teacher-app
npm ci
npm run build
```

Open **http://localhost/teacher/** with a Teacher account created in Filament. The generated `backend/public/teacher/` is ignored by Git; rebuild after checkout/deployment.

For development run `npm run dev`, then open `http://localhost:5173/teacher/`. Vite proxies `/api` to `http://127.0.0.1`; `TEACHER_API_PROXY` can override it. Use HTTPS outside localhost. The landing page links to the teacher app; Filament remains at `/admin`.

## Current storage policy

**Only the active profile and JWT are persisted**, in the single `halaka-teacher-auth` localStorage entry. No passwords are persisted. The migration preserves this login, clears the old IndexedDB data (drafts, queue, school cache, backups and preferences), then deletes the old app database. Other applications’ storage and administrator sessions are untouched.

School data, drafts, upload queue, recovery copies and theme live only in JavaScript memory for the current page. They disappear when the page reloads/closes. A before-unload prompt warns about unsent work. This is intentionally no longer an offline-first app: reading school records, starting a session and recovering after reload require a connection. Previously registered teacher service workers and their caches are retired/removed. Static assets are served normally; API responses are never cached.

A finished session retries automatically while the page remains open and on reconnect, using the same immutable submission ID/payload. Once acknowledged, the queue entry is removed. New edits are kept separate from an in-flight revision. The application cannot resume a lost in-memory queue after closing the page; send successfully before closing. Conflicts and authentication/validation failures require review rather than automatic overwriting.

One unfinished/unacknowledged session blocks creating another. Grades are free numeric values, missing grades are not zero, and each assignment can be assessed once per teacher session. “يحتاج إعادة” defaults unchecked for new evaluations: checked submits `is_passed=false`, unchecked submits `true` after grading; the shared assignment status updates across sessions. New assignments remain pending until assessed. Surahs use all 114 Arabic names; ayah numbers are positive and ordered without a per-surah maximum.

## Verification

```bash
npm test
npm run build
```

Tests cover the profile/JWT-only policy, removal of old IndexedDB data, non-persistent working data, account isolation, transient retry/idempotency, conflict handling, direct evaluation rows, and grading payloads. Test fixtures do not change production school records.
