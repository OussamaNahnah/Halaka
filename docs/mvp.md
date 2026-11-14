# Halaka — MVP

One Laravel application for Quran-school administration and teacher session recording. **Current status: Laravel/Sail in `backend/` with local MySQL database `halaka`, plus a teacher HTML prototype. The school domain schema and Filament remain unimplemented.**

## Delivery 1 — build first, after plan review

Use a **Filament administrator panel** in the same Laravel project, with Laravel session authentication. No APIs or JWT implementation in this delivery.

- **Teachers:** create/edit linked accounts, names, unique emails, and administrator-assigned passwords; optional password reset.
- **Students:** create/edit names and optional `parent_phone`; database-generated IDs; group memberships. No student accounts.
- **Groups:** create/edit names and student membership; no Group teacher foreign key.
- **Sessions:** inspect existing/seeded sessions, rosters, attendance, assessments, and assignments using linked student/session views.
- **Student history:** assignment blocks show To do if no assessment exists, or Assessed with all associated grades/comments. Show source session/teacher and independent repeat assignments.
- **Corrections:** administrators may edit existing assessment grades/comments directly on the existing assessment record; no separate change-log table. No attendance correction, assignment-passage editing, assessment creation/deletion, or resource delete actions.

Arabic RTL, Halaka wordmark, Tajawal, forest-green/gold branding, standard Filament controls. Wide desktop workspace with responsive mobile layouts. Use standard Filament components; the administrator requirements are defined in the implementation plan.

Read the [Laravel implementation plan](laravel-plan.md) for migrations, relationships, Filament resources, policies, actions, tests, and review points. **The user will review the plan before implementation.**

## Delivery 2 — teacher application, planned later

Follow the [teacher application plan](teacher-plan.md); the teacher prototype remains the interaction reference. Implement this only after the first delivery and approval of that plan.

- Sign in; select date/time and group; load its students; create and edit sessions without deleting them.
- One student worksheet: assess previously assigned passages, assign several next-session passages, and finish the student. Support both `new_memorization` and `revision`, optional comments, and full/partial repeats linked to the original assessment.
- Finishing a student sets `evaluated_at` and marks them present. On session completion, confirm the unfinished student list and mark those students absent. Missing grades do not become zero.
- Cache the interface/assets and classroom data after an initial online preparation. Save work durably in IndexedDB; support offline cold reopening.
- Finish and explicitly send the entire session; keep pending submissions during poor connectivity and offer Retry. Distinguish session completion from upload state, prevent duplicate submissions, and reject stale updates without losing local edits.
- Use a small teacher API inside Laravel. The previously agreed JWT requirement is retained for that future API: no `exp` or refresh-token flow, with server-side logout invalidation. No API/JWT code is part of Delivery 1.

Offline teacher operation remains required before the **full MVP** is complete. Completing the Filament foundation alone is the first milestone, not the whole product.

## Shared rules

The [class diagram](class-diagram.md) is the authoritative domain model.

- Grades are numeric 0–10 inclusive; decimals allowed. Store secure password hashes only.
- Generated student IDs; optional shared sibling `parent_phone`; no Parent entity.
- Each student/session pair is unique. Group membership changes preserve historical rosters and results.
- Each assignment covers one surah/range/type. Each assessment references an assignment for the same student; it does not copy the passage/type.
- Repeat assignments and their grades remain independent of their source assessment.
- Administrator changes must not be silently overwritten by future teacher synchronization.

## Deferred

Parent/student login and screens, Telegram and notifications, native apps/install prompts, analytics/exports, automatic background synchronization, and multi-device conflict-resolution UI. Administrator attendance correction and teacher/group access mapping remain unresolved; see model open decisions.

## Acceptance by milestone

**Delivery 1:** administrator-only panel access; atomic teacher/account creation and password reset; student/group management; linked session/student/assignment/assessment views; correct To do/Assessed display; authorized grade/comment direct corrections; no delete actions. All relevant validation/policy/relationship tests pass.

**Full MVP:** teacher completes a real class, closes/reopens the prepared interface offline without losing work, reconnects and submits exactly once, then sees accepted data. Corrections are reflected consistently and stale submissions cannot undo them. No real passwords are stored in browser drafts.
