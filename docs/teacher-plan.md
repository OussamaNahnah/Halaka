# Teacher application — implementation plan

**Delivery 2: proposed for review, not authorization to implement.** Begin after the Laravel + Filament foundation and approval of this teacher plan. Delivery 1 still has no API/JWT/offline implementation.

[MVP](mvp.md) owns scope; [class diagram](class-diagram.md) owns model rules. Reuse the models, accounts, policies, and database from the [Laravel plan](laravel-plan.md). The [teacher prototype](../teacher-prototype/README.md) is the visual reference; historical prototype behavior does not override those specifications.

## 1. Screens and flow

Arabic RTL, Tajawal, Halaka branding, compact headers with inline logo, accessible controls, and real mobile viewport sizing. No forced desktop phone frame in the production application. Bottom navigation: **Home** and **Profile**.

| Screen | Required interaction |
| --- | --- |
| Login | Email/password assigned by the administrator; real authentication. No demo login in production. First login requires a connection. |
| Home | One compact list of sessions, newest first. Merge local and server records by stable identity. Show completion and sync states separately; create-session action at the bottom. Open/edit, never delete. |
| Create/edit session | Choose date, time, and group from a dropdown. Create a local draft and snapshot the group's members into its roster. Editing must preserve saved student work; never rebuild historical rosters silently. |
| Session roster | Every student shows Not evaluated yet or Recorded. Open any student directly; no separate attendance gate. Finish-and-send action below the roster. |
| Student worksheet | Today's assessments and next assignments together, with one Finish student action and previous/next navigation. Save before leaving. |
| Profile | Teacher name, local dark-mode preference, online password change, and logout. Never cache or queue password form values. |

## 2. Student worksheet

### Today's assessments

- Show earlier assignments awaiting assessment and assessments already recorded in this session. Work assessed in an earlier session belongs in history; assigning an explicit repeat creates fresh work.
- Each row shows memorization/revision type, surah, inclusive ayah range, and a separate grade field. Grades accept any numeric value, with or without decimals; empty is unassessed, not zero.
- An optional comment expands to the full row width. Preserve unsaved input when adding a passage, navigating, or refreshing the view.
- Repetition: save the assessment first, then select the same surah's full range or a smaller contained range. Create a new assignment linked to that assessment. Keep the original grade and all later repeat grades independent.
- No assignment from the current session can be assessed in that same session. Validate same-student ownership for every link.

### Next assignments

- Compact rows with type, surah/range, and repeat/source link when relevant.
- “Add assignment” reveals the form on demand: type, surah, first ayah, last ayah. Use the complete validated Quran catalog, not the prototype's seven-surah sample.
- Allow several new-memorization and revision assignments for the same student/session. Adding one must not clear assessment fields.
- Save additions locally immediately. Remove only unassessed assignments; preserve assignments already referenced by assessments. Editing/removal must retain valid repeat links.

### Finish and navigate

- Finish student validates and saves entered assessment work and assignments, sets `evaluated_at`, and marks the student present. Return to the roster with Recorded status.
- Previous/next saves work but does not implicitly finish the student. A student with no earlier passages can still be finished.
- Never silently discard partially entered assignment/repeat forms: require saving them or an explicit discard decision before navigation.

## 3. Local storage and offline preparation

Use IndexedDB, scoped by authenticated user, rather than the prototype's shared localStorage dataset.

| Store | Contents |
| --- | --- |
| Classroom cache | Accessible groups, students, memberships, Quran catalog, earlier assignments/assessments, and accepted session versions. |
| Session drafts | Session metadata, roster snapshot, student completion, attendance, assignments, and assessments with stable client identifiers. |
| Submission queue | Immutable submitted revision, idempotency key, base server version, dependencies, and delivery state. |
| Preferences | Theme and non-secret UI preferences. |

- Prepare required data online, with visible success/failure; missing cache means unavailable, not an empty classroom.
- Save draft changes transactionally, including input edits before navigation. Show Saved on device only after success; surface quota/storage failures.
- Cache an offline application shell with a service worker. Host fonts/icons/scripts/styles locally and use HTTPS in deployment. Never put private API responses into a shared public cache.
- Cold reopening offline must work after preparation. Version updates must preserve drafts and not reload a teacher's active worksheet unexpectedly.
- Browser storage can be cleared/evicted; it is not a backup. A shared device must not expose another teacher's cached data. Preserve drafts on logout but require their owner to sign in again to access/submit them.
- Initial login, changing a password, and new classroom downloads need connectivity. No background polling, media uploads, location tracking, or automatic background synchronization in MVP.

## 4. Completion and sending

1. Creating/editing a draft saves locally as `in_progress` / `local_only`.
2. Finish and send displays the names of unfinished students for confirmation. They become absent; explicitly finished students remain present. Never invent grades.
3. Save a completed immutable revision in the durable queue as `pending_upload` before sending. A failed local save prevents claiming the submission is queued.
4. Send as `uploading`; no connection or temporary failure keeps it pending with Retry. On restart, recover interrupted uploads as pending.
5. Mark only the acknowledged revision `synced`; retain newer local edits as `local_only`. A completed session may still be unsent.
6. Keep the same submission ID/payload on retry. For edited work, create a new revision/submission ID while preserving the session's client UUID.
7. Resolve dependencies before submission: an assessment may reference an assignment from an earlier offline session. Send and acknowledge that source first; never send unresolved child links.
8. Server validation failure leaves work locally available for correction. Reauthentication must not discard queued work. Stale-version conflicts preserve local edits and do not overwrite newer administrator corrections.

## 5. Proposed Laravel components — later delivery only

| Component | Responsibility |
| --- | --- |
| TeacherAppController | Serve the teacher application shell through a web route; no duplicated admin UI. |
| TeacherAuthController | Login/logout with the agreed JWT policy; no automatic expiry (`exp` omitted), no refresh flow, real server-side logout invalidation. |
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

All reads/writes must enforce teacher ownership and classroom access, including nested IDs. The server determines `teacher_id`. Do not implement these endpoints in the Filament milestone.

### Additional schema to design in this delivery

- Unique session `client_uuid` and a server revision counter; preserve domain IDs from the foundation.
- A durable submission acknowledgement/idempotency record, including teacher, submission identity, payload fingerprint, and returned local-to-server ID mapping. Exact names and indexes belong to the API implementation review.
- Stable local identities for new child records and server resolution of repeat/assignment references.
- Wire Filament grade/comment corrections into the same session revision check before uploads are enabled. Device-only `sync_status` and `last_synced_at` are not automatically server columns.
- Choose the JWT package, secure browser credential persistence, and logout invalidation design before coding authentication. No plaintext passwords in caches or queues.

## 6. Suggested frontend structure

```text
resources/views/teacher/app.blade.php
resources/js/teacher/
  app.js                 routing, navigation, and startup
  screens/               login, sessions, roster, worksheet, profile
  components/            shared header, navigation, forms, feedback
  storage/               IndexedDB access and upgrades
  sync/                  durable queue, revision mapping, manual retry
  api/                   authenticated transport and error handling
resources/css/teacher/
  theme.css, components.css, screens.css
public/teacher-sw.js      application shell caching and version handling
```

Adapt to the actual Laravel asset pipeline. Do not introduce a separate frontend framework or application deployment without need/approval. Keep the standalone HTML prototype usable as reference.

## 7. Tests and acceptance

- Real login/logout, protected API access, ownership checks, nested-record access rejection, and no credential/hash exposure.
- Group selection, roster snapshots, saved edits, multiple passages/types, integer and decimal grades, invalid grades/ranges, optional comments, and independent repeats.
- Finish student and previous/next behavior; final absent-student confirmation; no silent form loss or zero grades for unassessed passages.
- Durable local writes, storage failure, user isolation, tab-close recovery, and offline cold reopening with fonts/assets available.
- Atomic server submission, validation rollback, dependency ordering, dropped response and retry without duplicates, and local/server identifier mapping.
- Edits during upload remain unsynced. Administrator correction causes stale submission rejection. Failed uploads and reauthentication preserve queued work.
- Arabic mobile layout, full-width notes, keyboard focus, accessible labels, feedback states, and no horizontal page overflow.

## 8. Review and execution checklist

- [ ] Complete/approve the Laravel + Filament foundation.
- [ ] Resolve teacher/group access mapping; do not invent a Group teacher field.
- [ ] Approve this plan and specify JWT storage/logout, submission/version contract, and cache refresh boundaries.
- [ ] Implement authenticated reads and atomic submission with meaningful backend tests.
- [ ] Integrate the teacher screens with IndexedDB saving and manual sending.
- [ ] Add offline shell caching/local assets; test cold reopening and migration of drafts.
- [ ] Review the full teaching flow with real mobile interaction and failure scenarios.

Open product questions remain in the class diagram. This plan describes proposed implementation, not completed functionality.
