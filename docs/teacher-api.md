# Teacher API contract

This is the implemented backend contract for Delivery 2. The endpoint list is maintained in [teacher-plan.md](teacher-plan.md#minimal-endpoint-plan). The separate [teacher PWA](../teacher-app/README.md) consumes this API and persists only profile/JWT; school work and automatic retries stay in page memory while open, with conflict review.

## Setup and authentication

From `backend/`:

```bash
composer install
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan jwt:secret
./vendor/bin/sail artisan config:clear
```

Generate `JWT_SECRET` once on a new installation; do not regenerate an existing secret. Keep `.env` private and use HTTPS in deployment. Existing school data is retained by the additive migration. Teacher accounts are created in Filament; this API has no registration endpoint.

Authentication uses `php-open-source-saver/jwt-auth` v2.10 with the separate `teacher_api` guard. Filament keeps its `web` session guard. Tokens omit `exp`; there is no refresh route. The package's blacklist is persisted in `jwt_revocations` through a database storage provider, so cache clearing does not reactivate logged-out tokens. Preserve that table in backups; do not truncate it. Password changes/resets also invalidate all older credentials using a signed password fingerprint, without exposing the password hash. Active User, Teacher profile, and Teacher role are checked on every protected request.

Login JSON:

```json
{"email":"teacher@example.com","password":"your-password"}
```

Successful response: `access_token`, `token_type: "Bearer"`, and `data` containing `id`, `teacher_id`, `name`, and `email`. Subsequent requests must send:

```http
Authorization: Bearer <access_token>
Accept: application/json
Content-Type: application/json
```

Query-string/cookie tokens are not accepted. Private API responses use `Cache-Control: private, no-store`. Login is limited to 5 attempts per email/IP per minute and 30 per IP; protected endpoints allow 120 requests per user/IP per minute. Password changes require `current_password`, `password`, and `password_confirmation`; success requires signing in again. The API never stores or queues plaintext passwords.

## Classroom reads and access

Every active teacher can read all active groups and students, including their assignments and assessment context. There is no teacher-group pivot or implicit group restriction. Only the authenticated teacher's sessions can be listed, opened, or edited; another teacher's session returns 404. The server always assigns `teacher_id`; submissions cannot supply it.

- Groups return `data` with `id`, `name`, `students_count`, `suggested`; `meta.suggested_group_id` identifies the group of the teacher's latest non-archived session whose group remains active. It is null if none exists.
- Group rosters return only student `id` and `name`; private parent contact numbers and account details are excluded.
- Student assignments return passage/type/passed state, stable IDs, and active assessment context. All active assignments are returned so clients can derive the pending list without losing history.
- The Quran catalog returns all 114 entries: `id`, Arabic `name`, and `verses`.
- Session lists are paginated, newest first; `per_page` defaults to 20 and is limited to 100. Details include active roster records and attempts, plus archival flags on referenced historical students/groups/assignments.
- Classroom and session responses include `meta.sync_revision`. Read the required records at a matching revision before preparing an upload; if revisions differ between downloads, repeat the preparation. Do not silently replace local drafts.

## Atomic submission

Only completed session revisions are uploaded. `POST /api/sessions` creates the first server version; `PUT /api/sessions/{id}` edits an owned existing session. No teacher session-deletion endpoint exists.

Example first upload (replace example IDs/revision with downloaded values):

```json
{
  "submission_id": "d9178e22-2847-4f7b-bfea-5820fac51e01",
  "client_uuid": "4ff580e7-a033-4d76-a401-d453f29e8071",
  "base_sync_revision": 42,
  "group_id": 1,
  "timestamp": "2026-11-14T10:00:00Z",
  "status": "completed",
  "students": [
    {
      "student_id": 1,
      "evaluated_at": "2026-11-14T10:30:00Z",
      "assignments": [
        {
          "client_uuid": "ac56e598-4e30-4a9f-b460-9df8e20f732a",
          "surah_name": "الملك",
          "from_ayah": 1,
          "to_ayah": 10,
          "type": "new_memorization",
          "is_passed": false
        }
      ],
      "assessments": [
        {
          "client_uuid": "3d50378e-b3e4-49a0-9c6d-73b30ae75d99",
          "quran_assignment_id": 12,
          "grade": 8.125,
          "comment": "مراجعة موفقة"
        }
      ]
    },
    {
      "student_id": 2,
      "evaluated_at": null,
      "assignments": [],
      "assessments": []
    }
  ]
}
```

- Each new assignment/attempt needs a stable `client_uuid`. Existing server records can use `id`; if both ID and UUID are supplied, they must agree. Session UUIDs remain stable across revisions. Legacy admin-created sessions receive their UUID on first teacher edit.
- A new session must contain exactly the selected group's current active roster. Updates must retain exactly the session's saved active student IDs, even if group memberships have since changed. Changing the selected group does not rebuild the roster. Archived records are never restored by submission.
- A non-null `evaluated_at` marks the student present; null marks absent. Unfinished students must have empty assignment/assessment arrays. Clients must confirm unfinished names before sending. Missing grades are not zero; every submitted attempt requires a finite numeric grade, without range or fixed decimal-place limits.
- Assignments are upserts: include `id` or `client_uuid`, passage/type, and explicit `is_passed`. Attempts never infer passing. Teachers cannot change passages/types once any attempt references them, including archived attempts.
- Attempts reference `quran_assignment_id` or `assignment_client_uuid`. The assignment must be active and belong to the roster student. UUIDs resolve new assignments in the same payload and acknowledged earlier submitted sessions. An unresolved dependency returns 422; send and acknowledge the source first.
- Updates include `base_revision` from session detail and all current active attempts, preserving their IDs/UUIDs. Grades/comments and valid assignment links may be corrected; attempts cannot be silently omitted or moved to another student's/session's roster.
- Optional `removed_assignment_ids` under a student soft-deletes only active, unassessed assignments. The same assignment cannot be saved and removed in one submission. Referenced assignments/attempts remain in history.
- The whole revision commits atomically or rolls back, including its revision changes and acknowledgement.

## Acknowledgement, retries, and conflicts

Success returns `submission_id`, complete `data` for the accepted session, `id_mapping.session_id`, UUID-to-server-ID dictionaries in `id_mapping.assignments` and `id_mapping.assessments`, and `meta.sync_revision`. First-upload responses use 201; edits use 200.

A durable record in `teacher_submissions` binds the teacher and submission UUID to a canonical validated payload fingerprint, HTTP operation/session target, and acknowledgement. Retrying the same operation and payload returns the original acknowledgement without another write, even if the server response was lost or school data subsequently changed. If the acknowledged session has since been archived or reassigned to another teacher, replay returns 409 without exposing its data or restoring it. Reusing the submission ID with changed data returns 409. A new edit/rebase requires a new submission ID. Never mark newer local edits synced just because an older revision was acknowledged.

The first backend version deliberately uses **one school-wide revision** in `school_sync_state`. Model events advance it for school/account writes, archival/restoration, and `GroupStudent` pivot changes. A shared database lock serializes Filament mutation requests and teacher API snapshots/submissions. Every upload must match `base_sync_revision`; updates must also match the session counter. Administrator changes therefore cannot be overwritten by a previously prepared teacher revision.

This conservative strategy also rejects unrelated school changes and another successful teacher upload. After a 409, preserve local work, fetch current data, explicitly reconcile it, and submit a new immutable revision/ID. Apply the same preparation after acknowledging source submissions for client-created dependencies. Do not just replace the base revision and retry unchanged stale values. Finer per-record revisions can reduce false conflicts later without weakening conflict handling.

Use Eloquent/domain actions for school writes. Raw SQL/bulk query updates bypass model observers and are outside the API/Filament mutation contract; migrations/imports must explicitly advance the revision and coordinate with the shared lock. Do not accept teacher uploads through a second path that bypasses these checks.

Errors: 401 missing/invalid/revoked credentials; 403 unavailable teacher role/profile; 404 unavailable or foreign session; 409 stale revision or reused identities; 422 field validation; 429 rate limit. Validation errors use dotted student/assignment/attempt paths. Keep local work on all failures. Server acceptance acknowledges only the submitted revision; the teacher app keeps only profile/JWT persistently and provides temporary in-page conflict review.

## Surah names

Assignments persist `surah_name` in Arabic. Filament selects from all 114 Arabic names; API clients send the Arabic `name` returned by the catalog. Recognized legacy English identifiers are normalized before storage, including on assessed-assignment comparisons. The additive normalization migration converts recognized names in active and archived rows without changing their IDs, passages, status, or assessment links, and advances the shared revision. Unknown legacy values are retained for manual correction. Rollback keeps the normalized Arabic spelling because the original alias cannot be recovered losslessly.

Ayah counts are reference metadata only: assignments require positive integer ayah numbers with `from_ayah <= to_ayah`, without enforcing the catalog's per-surah maximum. This applies to Filament, API, and prototype.

Teacher rule: assess each assignment once per session and edit that existing assessment as needed. Reassessment is available in a later session. Preserve historical attempts and administrator privileges; no database uniqueness constraint is added to the assessment table.

## Shared repeat decision

Teacher assessment submissions now include an optional Boolean `needs_repeat` (always sent by the current teacher app): `true` writes `QuranAssignment.is_passed = false`; `false` writes `is_passed = true`. It is an input decision only, not an assessment database column. It takes precedence over any older `is_passed` copy in assignment upserts. Omitted values retain legacy-client behavior. Untouched, unassessed rows are not submitted and remain pending.

Each session detail returns the assignment's current global `is_passed` alongside its assessment. Acknowledgements include `assignment_states` (`id`, `student_id`, `client_uuid`, `is_passed`) so the app updates its shared cache and synchronized historical views. Unsent work keeps its edit intent and still uses revision checks. Older acknowledgements never replace newer known assignment states. Original grades and assessment links remain unchanged.
