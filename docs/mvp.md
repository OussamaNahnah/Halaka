# Halaka — MVP

One Laravel application for Quran-school administration and teacher session recording. **Current status: Laravel/Sail in `backend/` with local MySQL database `halaka`, plus a teacher HTML prototype. Domain models and migrations implement the latest student-owned assignment design with `is_passed`. Filament 5 login/dashboard and administrator-only access are installed; full administrator management is implemented, including nested attendance/assignment/assessment editors and soft deletion/restoration. Teacher/API/offline functionality remains planned.**

## Delivery 1 — build first, after plan review

Use a **Filament administrator panel** in the same Laravel project, with Laravel session authentication. No APIs or JWT implementation in this delivery.

- **Administrators:** manage administrator accounts (name, unique email, initial/reset password) with soft-delete/restore. All have the same panel privileges; the logged-in user cannot delete itself, including in bulk.
- **Teachers:** create/edit linked accounts, names, unique emails, and administrator-assigned passwords; optional password reset.
- **Students:** create/edit names and optional `parent_phone`; database-generated IDs; group memberships. No student accounts.
- **Groups:** create/edit names and student membership; no Group teacher foreign key.
- **Sessions:** create/edit sessions, explicitly select initial roster students, and manage attendance through linked student/session views. Editing group membership or session group never silently rebuilds saved rosters.
- **Student history:** show each student-owned assignment as Pending or Passed, with all assessment attempts, grades/comments, and assessing sessions/teachers. See the class diagram for status and repeat rules.
- **Full administration:** create/edit student assignments and assessment attempts, change `is_passed`, and correct attendance. Assessment references must belong to the same student; passage ranges must be valid.
- **Deletion:** soft-delete/restore teachers, students, groups, sessions, roster records, assignments, and assessments. Keep linked records and memberships intact; no permanent-delete actions or separate change-log table.

Arabic RTL, Halaka wordmark, Tajawal, forest-green/gold branding, standard Filament controls. Wide desktop workspace with responsive mobile layouts. Use standard Filament components; the administrator requirements are defined in the implementation plan.

Read the [Laravel implementation plan](laravel-plan.md) for migrations, relationships, Filament resources, policies, actions, tests, and review points. **The user approved the administration resources. Later teacher delivery still requires its own review.**

## Delivery 2 — teacher application, planned later

Follow the [teacher application plan](teacher-plan.md); the teacher prototype remains the interaction reference. Implement this only after the first delivery and approval of that plan.

- Sign in; select date/time and group; load its students; create and edit sessions without deleting them.
- One student worksheet: assess previously assigned passages, assign several next-session passages, and finish the student. Support both `new_memorization` and `revision`, optional comments, teacher-selected pending/passed status, repeated attempts on the same assignment, and separate assignments for smaller passages.
- Finishing a student sets `evaluated_at` and marks them present. On session completion, confirm the unfinished student list and mark those students absent. Missing grades do not become zero.
- Cache the interface/assets and classroom data after an initial online preparation. Save work durably in IndexedDB; support offline cold reopening.
- Finish and explicitly send the entire session; keep pending submissions during poor connectivity and offer Retry. Distinguish session completion from upload state, prevent duplicate submissions, and reject stale updates without losing local edits.
- Use a small teacher API inside Laravel. The previously agreed JWT requirement is retained for that future API: no `exp` or refresh-token flow, with server-side logout invalidation. No API/JWT code is part of Delivery 1.

Offline teacher operation remains required before the **full MVP** is complete. Completing the Filament foundation alone is the first milestone, not the whole product.

## Shared rules

The [class diagram](class-diagram.md) is the authoritative domain model.

- Grades accept any numeric value, with or without decimals; no fixed range or decimal-place limit. Store secure password hashes only.
- Generated student IDs; optional shared sibling `parent_phone`; no Parent entity.
- Each student/session pair is unique. Group membership changes preserve historical rosters and results.
- Each assignment covers one surah/range/type. Each assessment references an assignment for the same student; it does not copy the passage/type.
- Assignment passing is stored as the teacher-selected boolean `is_passed`, not derived from grades or the existence of assessments. Retain every assessment attempt; assignments have no assigning-session or repeat/source link.
- Administrator changes must not be silently overwritten by future teacher synchronization.

## Deferred

Parent/student login and screens, Telegram and notifications, native apps/install prompts, analytics/exports, automatic background synchronization, and multi-device conflict-resolution UI. Teacher/group access mapping remains unresolved; see model open decisions.

## Acceptance by milestone

**Delivery 1:** administrator-only panel access; atomic administrator/teacher account creation and password reset; self-deletion protection; student/group management; linked session/student/assignment/assessment views; correct Pending/Passed display; full authorized management of attendance, assignments and assessments; soft-delete/restore without losing linked history. All relevant validation/policy/relationship tests pass.

**Full MVP:** teacher completes a real class, closes/reopens the prepared interface offline without losing work, reconnects and submits exactly once, then sees accepted data. Corrections are reflected consistently and stale submissions cannot undo them. No real passwords are stored in browser drafts.
