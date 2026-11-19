# Teacher application — implementation plan

**Delivery 2 backend approved and implemented:** teacher JWT authentication, classroom reads, owned session reads, and atomic versioned submissions. See the [implemented API contract](teacher-api.md). The separate Vue 3/TypeScript web app in `teacher-app/` stores only profile/JWT persistently; school work and automatic retries live in page memory until acknowledgement. See the [frontend README](../teacher-app/README.md). Delivery 1 remains the completed Filament foundation.

[MVP](mvp.md) owns scope; [class diagram](class-diagram.md) owns model rules. Reuse the models, accounts, policies, and database from the [Laravel plan](laravel-plan.md). The [teacher prototype](../teacher-prototype/README.md) is the visual reference; historical prototype behavior does not override those specifications.

## 1. Screens and flow

Arabic RTL, Tajawal, Halaka branding, compact headers with inline logo, accessible controls, and real mobile viewport sizing. No forced desktop phone frame in the production application. Bottom navigation: **Home** and **Profile**.

| Screen | Required interaction |
| --- | --- |
| Login | Email/password assigned by the administrator; real authentication. No demo login in production. First login requires a connection. |
| Home | One compact list of sessions, newest first. Merge local and server records by stable identity. Show completion and sync states separately; sticky create/resume action at the top. Teachers open/edit, never delete; administrator archival/restoration is handled by Filament. |
| Create/edit session | Choose date, time, and group from a dropdown. Create a local draft and snapshot the group's members into its roster. Editing must preserve saved student work; never rebuild historical rosters silently. |
| Session roster | Every student shows Not evaluated yet or Recorded. Open any student directly; no separate attendance gate. Sticky finish-and-send action above the roster. |
| Student worksheet | Today's assessments and next assignments together, with one Finish student action and previous/next navigation. Save before leaving. |
| Profile | Teacher name, temporary dark-mode preference, online password change, and logout. Never cache or queue password form values. |

## 2. Student worksheet

### Today's assessments

- Show the student's pending assignments, including those attempted in earlier sessions, plus assessments already recorded in the current session. Passed assignments leave the pending list; retain every attempt in history.
- Each row shows memorization/revision type, surah, inclusive ayah range, and a separate grade field. Grades accept any numeric value, with or without decimals; empty is unassessed, not zero.
- An optional comment expands to the full row width. Preserve unsaved input when adding a passage, navigating, or refreshing the view.
- After an assessment, let the teacher explicitly set `is_passed` (`false` for pending, `true` for passed); do not infer passing from a grade. Full-passage retries create another assessment on the same pending assignment. Smaller passages use separate pending assignments without a repeat/source link.
- Assignments belong directly to students, with no assigning-session relationship. Validate that each assessment's assignment and roster record belong to the same student.

### Next assignments

- Compact rows with type, surah/range, and pending/passed status.
- “Add assignment” reveals the form on demand: type, surah, first ayah, last ayah. Use the complete validated 114-surah catalog and persist Arabic surah names.
- Allow several new-memorization and revision assignments for the same student. Adding one must not clear assessment fields.
- Keep additions in page memory immediately. Remove only unassessed assignments; preserve assignments already referenced by assessments. Preserve the passage of assignments already assessed.

### Finish and navigate

- Finish student validates and saves entered assessment work and assignments, sets `evaluated_at`, and marks the student present. Return to the roster with Recorded status.
- Previous/next saves work but does not implicitly finish the student. Finish student requires at least one graded assessment. When there are no previous passages to assess, adding a new next assignment is sufficient. Every started assessment requires a numeric grade. Notes and repeat are optional. Clearing the evaluation clears its completion marker.
- Never silently discard partially entered assignment forms: require saving them or an explicit discard decision before navigation.

## 3. Browser storage and connection policy

Persist only the current teacher profile and JWT in `halaka-teacher-auth` localStorage. Passwords are never persisted. Migrate old authentication, then clear/delete the legacy IndexedDB database and remove teacher service workers/caches. Do not clear unrelated applications or administrator cookies.

All classroom data, sessions, drafts, submission queues, temporary recovery copies, and theme preferences live in page memory only. Fetch confirmed history from the server. Refreshing or closing the page loses unsent work; warn before leaving while work is pending. There is no offline cold reopening or persistent school-data cache.

Allow one unfinished/unacknowledged session per open application. New sessions require online classroom preparation. Only finished submissions retry automatically when connectivity returns, with bounded backoff, while the page stays open. Validation/conflict failures require review; authentication failures require login. A closed application cannot retry.

## 4. Completion and sending

1. Keep edits in page memory as `in_progress` / `local_only`.
2. Finish and send confirms unfinished students, marks them absent, and never invents grades.
3. Keep an immutable submission in memory as `pending_upload`, then `uploading`. Keep its payload and idempotency key unchanged for transient retries.
4. Only server acknowledgement changes the submitted revision to `synced`. Keep newer unsent edits separate; remove acknowledged temporary data.
5. Server-side durable idempotency prevents duplicates even after a dropped response. Version conflicts never silently overwrite administrator changes.
6. Pending work and temporary conflict copies survive navigation within the running app, but not page refresh/close. Do not describe them as saved on the device.

## 5. Laravel backend components

| Component | Responsibility |
| --- | --- |
| Separate `teacher-app/` | Vue 3/TypeScript/Vite web app consuming Laravel API; no duplicated admin UI. |
| TeacherAuthController | Login/logout with the agreed JWT policy; no automatic expiry (`exp` omitted), no refresh flow, real server-side logout invalidation; reject archived teacher profiles. |
| TeacherProfileController | Read profile and change password online. |
| TeacherClassroomController | Authorized groups, group roster, pending assignments, and Quran catalog. |
| TeacherSessionController | List/retrieve accepted sessions; accept atomic new/edited session submissions. |
| StoreSessionSubmissionRequest | Validate submission shape, nested fields, identity/version, and domain constraints. |
| SubmitTeacherSession action | Authorize, resolve references, validate relationships, lock/check versions, write the whole submission atomically, and return a stable acknowledgement. |
| API Resources | Small user/session/classroom serializers as needed; never serialize password hashes or unrelated student contact fields. |

Keep controllers thin and reuse existing domain logic. Do not add a CRUD endpoint for every attendance field, assignment, or assessment: submission carries the complete session revision.

### Minimal endpoint plan

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/login` | Teacher login. |
| POST | `/api/logout` | Invalidate current credential. |
| GET | `/api/profile` | Get teacher profile. |
| PUT | `/api/profile/password` | Change own password. |
| GET | `/api/groups` | Accessible groups. |
| GET | `/api/groups/{groupId}/students` | Group roster. |
| GET | `/api/students/{studentId}/assignments` | Earlier assignments and associated assessment context. |
| GET | `/api/quran/surahs` | Surahs and ayah counts. |
| GET | `/api/sessions` | Accepted sessions, paginated. |
| GET | `/api/sessions/{sessionId}` | Session, student work, and server revision. |
| POST | `/api/sessions` | Idempotent first upload of a completed session. |
| PUT | `/api/sessions/{sessionId}` | Idempotent submission of an edited revision with base-version checking. |

Implemented access decision: every active teacher can read all active groups and students; session list/detail/update are restricted to their own Teacher profile. Groups suggest the most recently taught active group. The server determines `teacher_id` and validates all nested references. See [teacher-api.md](teacher-api.md) for the request/response contract.

### Implemented server support

The additive API migration adds nullable unique client UUIDs to sessions/assignments/attempts, a session revision counter, durable `teacher_submissions`, `jwt_revocations`, and `school_sync_state`. The shared school revision includes Filament mutations, archival/restoration, and membership changes. The [API contract](teacher-api.md) documents conservative conflict handling, stable acknowledgements, payload validation, setup, and persistent JWT logout invalidation. The web app persists only profile/JWT in localStorage. Online logout invalidates the JWT on the server; offline revocation can retry only while the page remains open. See the frontend README.

## 6. Separate frontend structure — implemented

```text
teacher-app/
  src/
    screens/       login, sessions, roster, worksheet, profile
    components/    shared header, navigation, forms, feedback
    storage/       profile/JWT persistence, legacy cleanup, memory-only work
    domain/        submission building and worksheet helpers
    api/           authenticated transport and error handling
  public/          local brand assets and legacy worker cleanup
backend/           Laravel API and Filament administration
teacher-prototype/ standalone design/interaction reference
```

Use Vue 3, TypeScript, and Vite for the separate web app. Keep one repository and database; the two applications may be served under the same origin to simplify deployment. The prototype remains unchanged. Do not claim that school work survives page reload or that the application supports offline cold reopening.

## 7. Tests and acceptance

- Real login/logout, protected API access, ownership checks, nested-record access rejection, and no credential/hash exposure.
- Group selection, roster snapshots, saved edits, multiple passages/types, integer and decimal grades, invalid grades/ranges, optional comments, and repeated attempts with teacher-selected pending/passed status.
- Finish student and previous/next behavior; final absent-student confirmation; no silent form loss or zero grades for unassessed passages.
- Profile/JWT persistence, migration and deletion of old storage, user isolation, and loss of in-memory school data after module/page reload.
- Atomic server submission, validation rollback, dependency ordering, dropped response and retry without duplicates, and local/server identifier mapping.
- Edits during upload remain unsynced. Administrator correction causes stale submission rejection. Failed uploads and reauthentication preserve queued work.
- Arabic mobile layout, full-width notes, keyboard focus, accessible labels, feedback states, and no horizontal page overflow.

## 8. Review and execution checklist

- [x] Complete/approve the Laravel + Filament foundation.
- [x] Resolve teacher/group access: all active groups/students, own sessions, latest-group suggestion; no Group teacher field.
- [x] Approve backend implementation with JWT and document the submission/version contract. Frontend credential storage and manual cache refresh/conflict review are implemented; see the frontend README.
- [x] Implement authenticated reads and atomic submission with meaningful backend tests.
- [x] Integrate teacher screens with memory-only work and automatic retries while open.
- [x] Remove legacy school storage and offline caches while preserving authentication.
- [ ] Review the full teaching flow with real mobile interaction and failure scenarios.

Backend implementation is documented in the API contract and tested in `TeacherAuthTest` and `TeacherClassroomAndSubmissionTest`. Frontend storage policy and submission behavior are covered by focused tests; open product questions remain in the class diagram.

Teacher rule: assess each assignment once per session and edit that existing assessment as needed. Reassessment is available in a later session. Preserve historical attempts and administrator privileges; no database uniqueness constraint is added to the assessment table.

The teacher worksheet uses “يحتاج إعادة” (needs repeat), unchecked by default for a new evaluation. A graded passage is explicitly submitted as passed unless repeat is selected; previously recorded repeat decisions are preserved. Untouched/unassessed assignments retain pending status, and no grade threshold determines passing.

Current connection policy: one unfinished/unacknowledged session per open application. Only profile/JWT survive refresh; all unsent work is temporary. See the frontend README.

## Flutter rebuild — current user decision

A minimal Flutter client is approved and implemented in [`teacher_flutter/`](../teacher_flutter/README.md), using the existing API contract and domain rules. This supersedes the Vue-only frontend requirement for the rebuild. The existing web deployment remains available. Persist only profile/JWT; classroom data, draft edits and immutable retry payloads stay in process memory. The Flutter README owns setup, implemented UI scope and verification limitations. Conflict review is intentionally manual: retain the local worksheet, inspect server data, then explicitly reopen and re-enter corrections rather than silently changing the revision.

The Flutter UI now follows the existing `teacher-app/src/screens/` interactions and `teacher-app/src/style.css` branding, using the same Laravel endpoint paths. For the native mobile layout, contextual back/title/date/edit headers stay at the top and create/send/finish actions stay in bottom docks; student navigation shares the finish dock. Task screens hide global tabs. The existing web layout remains unchanged. See the Flutter README for implemented behavior and test coverage.
