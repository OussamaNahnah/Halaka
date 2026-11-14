# Laravel + Filament implementation plan

**Status: the user installed Laravel and Sail in `backend/`. Local development uses MySQL with database `halaka`. Domain models, relationships, and migration files are created. Seeders, application validation, and Filament remain planned.**

This is the technical delivery plan. [MVP](mvp.md) owns scope; [class diagram](class-diagram.md) owns domain fields and constraints.

## 1. First delivery: Laravel foundation and Filament

Build one Laravel application, one database, and an administrator panel at `/admin`. Use Filament's standard pages, resources, forms, tables, and relationship components. Reuse the same User/Role models for the future teacher application.

**No API in this delivery:** no `routes/api.php` endpoints, JWT package, JSON API Resources, teacher synchronization controllers, or offline upload pipeline. Filament uses Laravel session authentication. The existing HTML teacher prototype remains unchanged. Offline teacher operation is still a later MVP requirement, not removed from the product.

Check the actual PHP/Composer/database environment and select compatible supported Laravel/Filament versions before installation. Do not pin versions from memory or install unrelated frameworks. Keep the teacher prototype and documents intact while keeping the Laravel application in `backend/`. All application paths below are relative to `backend/`.

## 2. Proposed application structure

```text
app/
  Models/
    User.php, Role.php, Teacher.php, Student.php, Group.php
    TeachingSession.php, StudentSession.php
    QuranAssignment.php, QuranAssessment.php
  Filament/
    Resources/
      Teachers/       TeacherResource and list/create/edit pages
      Students/       StudentResource and list/create/view/edit pages
      Groups/         GroupResource and list/create/edit pages
      Sessions/       TeachingSessionResource and list/view pages
    Pages/            Add only when a standard resource cannot express the workflow
  Actions/
    SaveTeacher.php
    SyncGroupMembers.php
    CorrectAssessment.php
  Policies/
    TeacherPolicy.php, StudentPolicy.php, GroupPolicy.php
    TeachingSessionPolicy.php, QuranAssignmentPolicy.php, QuranAssessmentPolicy.php
  Providers/Filament/
    AdminPanelProvider.php
routes/
  web.php
  # Filament registers its own panel routes; no custom API routes in phase 1.
database/
  migrations/
  seeders/
    RoleSeeder.php, AdminUserSeeder.php, DemoSchoolSeeder.php
tests/Feature/
  AdminAccessTest.php, TeacherManagementTest.php
  StudentGroupManagementTest.php, StudentHistoryTest.php
  AssessmentCorrectionTest.php
```

Names/paths for generated Filament files may vary with the selected version; preserve these responsibilities rather than forcing an incompatible folder layout.

## 3. Migration list and order

All domain tables have generated primary keys and normal creation/update timestamps. Preserve historical records with restrictive foreign-key deletion behavior, not cascading deletion of teaching history.

| Order | Migration/table | Fields and constraints |
| --- | --- | --- |
| 1 | `users` | `id`, `name`, unique `email`, hashed `password`, framework authentication fields. The diagram's `password_hash` means this hashed storage; map to Laravel's `password` field explicitly, not a second password column. |
| 2 | `roles`, `role_user` | Role ID/name, unique role name; pivot with unique `(user_id, role_id)`. Seed Administrator, Teacher, Student; no student login UI. |
| 3 | `teachers` | `id`, required unique `user_id` referencing users. Name/email stay on User. |
| 4 | `students` | `id`, nullable unique `user_id`, required `name`, nullable string `parent_phone`. No manually entered student number; parent phone is not unique. |
| 5 | `groups`, `group_student` | Group ID/name; membership pivot with unique `(group_id, student_id)`. No teacher foreign key on Group. |
| 6 | `teaching_sessions` | Domain Session: `id`, `teacher_id`, nullable `group_id`, `timestamp`, `status` default `in_progress`. Use this table/model name to avoid collision with Laravel's framework `sessions` table. |
| 7 | `student_sessions` | `id`, `student_id`, `session_id` referencing `teaching_sessions`, `attendance_status` default `not_marked`, nullable `evaluated_at`; unique `(student_id, session_id)`. |
| 8 | `quran_assignments` | `id`, `assigned_in_student_session_id`, `surah_name`, positive `from_ayah`/`to_ayah`, `type`, nullable `repeat_of_assessment_id`. Create the last column without its foreign key until assessments exist. |
| 9 | `quran_assessments` | `id`, `student_session_id`, `quran_assignment_id`, double `grade`, nullable text `comment`. No fixed grade range; validate passage ownership and chronology in application logic. |
| 10 | Add repeat foreign key | Link `quran_assignments.repeat_of_assessment_id` to `quran_assessments.id`; reverse/drop this constraint before dropping these tables during rollback. |

Framework `sessions`, cache, or other infrastructure migrations are included only if required by the chosen Laravel configuration. They are not school-domain entities.

**Later synchronization:** the diagram's `client_uuid`, server revision/idempotency support, and device sync metadata are future teacher-delivery requirements. Do not turn `sync_status` or `last_synced_at` into server columns by blindly copying the conceptual diagram. Do not add JWT/queue/submission tables in this first delivery. When the API phase begins, add the required schema and integrate existing assessment corrections with stale-write protection before accepting uploads.

Grades use `double` to accept numbers without a fixed decimal-place limit, as requested by the user. Do not impose a numeric range or default a missing grade to zero. Floating-point values have finite machine precision.

## 4. Eloquent relationships

| Model | Relationships |
| --- | --- |
| User | `roles()` belongsToMany Role; `teacher()` hasOne Teacher; `student()` hasOne Student. |
| Role | `users()` belongsToMany User. |
| Teacher | `user()` belongsTo User; `sessions()` hasMany TeachingSession. |
| Student | Optional `user()` belongsTo User; `groups()` belongsToMany Group; `studentSessions()` hasMany StudentSession. |
| Group | `students()` belongsToMany Student; `sessions()` hasMany TeachingSession. |
| TeachingSession | `teacher()` belongsTo Teacher; optional `group()` belongsTo Group; `studentSessions()` hasMany StudentSession. |
| StudentSession | `student()` belongsTo Student; `session()` belongsTo TeachingSession using `session_id`; `assignments()` hasMany QuranAssignment using `assigned_in_student_session_id`; `assessments()` hasMany QuranAssessment. |
| QuranAssignment | `assignedInStudentSession()` belongsTo StudentSession; `assessments()` hasMany QuranAssessment; optional `repeatOfAssessment()` belongsTo QuranAssessment. |
| QuranAssessment | `studentSession()` belongsTo StudentSession; `assignment()` belongsTo QuranAssignment; `repeatAssignments()` hasMany QuranAssignment using `repeat_of_assessment_id`. |

Derive student assignment/history views through these existing relationships; do not add duplicate student IDs or copied surah/type fields to assessments. An assignment's student and an assessment's student must match.

## 5. Filament resources and associations

### Teachers

- List/search by linked User name/email; create/edit without deleting.
- Create User + Teacher + Teacher-role membership in one transaction.
- Required initial password on create; optional password reset on edit. Blank preserves the current hash; existing passwords/hashes are never displayed.
- Validate email uniqueness against all users, not only teachers.

### Students

- List/search generated ID, name, parent phone, and memberships.
- Create/edit name, optional parent phone, and selected groups. Do not expose account creation or editable IDs.
- View page: **Sessions** and **Assignments and assessments** sections, with pagination.
- Sessions show date/time, teacher, group, attendance, and links to the session.
- Assignment blocks show surah/range, memorization or revision, and assigning session/teacher. Filter All / To do / Assessed and type.
- With no linked assessment: To do, no invented zero grade. Otherwise: Assessed with all linked grades/comments and assessing sessions. This does not imply a passing threshold.
- Repeats appear as independent blocks linked to the source assessment; their grade never replaces the source grade.

### Groups

- List name and member count. Create/edit name and membership via searchable multiple selection or relationship controls.
- Membership changes from Student and Group update the same pivot. Removing a membership does not remove student/session history.

### Sessions

- List/view server-stored sessions; filter by date, group, teacher, and status.
- Session roster links to student records and student-session context.
- For each student-session, separate **assessed here** (`QuranAssessment.student_session_id`) from **assigned here** (`QuranAssignment.assigned_in_student_session_id`).
- Assessments link to the source assignment, which may belong to an earlier session.
- No session create/delete or attendance-edit action in this panel. Teacher session creation belongs to the later teacher delivery. Existing/seeded records support history review now.

### Grade/comment correction

- Open one shared edit modal from either a student assignment block or session assessment row.
- Only `grade` and `comment` are editable. Student, passage, type, and session are read-only context.
- `CorrectAssessment` authorizes the administrator, validates a numeric grade, locks the current assessment in a transaction, updates its grade/comment directly, and keeps its relationships unchanged. No change-log model, table, or audit-history records are required.
- Both entry points update the same record. Do not delete assessments, change foreign keys, or edit assignment passages through this action.
- No separate top-level assignment/assessment resource navigation is needed. Use nested Filament relationship views/actions.

## 6. Controllers, requests, and resources: what is actually needed?

| Component | First delivery decision |
| --- | --- |
| Custom admin CRUD controllers | **None.** Filament handles resource page actions. |
| Custom login/logout controller | **None for the panel.** Use Filament's authentication flow and Laravel sessions. |
| Teacher/API controllers | **None now.** Plan them when teacher implementation is approved. |
| JSON API Resources | **None now.** No API responses in this delivery. |
| Form Request classes | Not needed just to wrap Filament forms. Use shared domain validation/actions where it prevents inconsistent rules. |
| Filament Resources | TeacherResource, StudentResource, GroupResource, TeachingSessionResource. |
| Shared application actions | SaveTeacher, SyncGroupMembers, CorrectAssessment; no generic repository/service framework. |
| Policies and panel access | Required. Enforce administrator-only access and allowed actions server-side. Hidden buttons alone are insufficient. |

Do not generate empty controllers or resources merely to fill a project tree. Keep simple behavior in the appropriate Filament resource; extract shared transactional rules into the actions above.

## 7. Seeders and tests

- RoleSeeder: stable role names without duplicating records.
- AdminUserSeeder: in local/development and test environments, create the default Administrator account with email `admin@admin.com` and password `123456789`. Hash the password using Laravel's configured hasher. Make seeding idempotent: do not duplicate the user or reset an existing password on subsequent runs. Do not provision this known default account in production; production credentials must be supplied separately.
- DemoSchoolSeeder: development/test-only teachers, students, memberships, earlier assignments, assessed/unassessed work, and repeats. Never run demo seeds automatically in production.
- Test panel access: administrator allowed, guest redirected, teacher denied.
- Test atomic teacher creation, unique email, password hashing/reset, blank edit preserving the hash, and no hash leakage.
- Test nullable parent phone, shared sibling phone, generated IDs, both membership edit entry points, and duplicate-pivot prevention.
- Test session/student links, multiple assessments, To do/Assessed derivation, independent repeats, and no deletion actions.
- Test correction authorization, numeric validation, transactional direct updates, and consistent display across entry points.
- Verify schema rollback across the assignment/assessment circular reference and database constraints.

## 8. Execution checklist (after review)

- [ ] Inspect environment; select Laravel/Filament/database versions.
- [x] Scaffold Laravel in `backend/`, preserving the teacher prototype/docs.
- [x] Create ordered migrations and models/relationships, including both pivot tables.
- [ ] Add role and safe development seed data.
- [ ] Configure `/admin` session authentication and administrator policies.
- [ ] Implement teacher/student/group resources.
- [ ] Implement session/student history and grade/comment correction.
- [ ] Run relevant tests and review Arabic RTL, desktop/mobile layouts, and password handling.
- [ ] Update implementation status and record the actual setup/test commands.

## 9. Review points

1. Approve the first delivery as **database + Filament only**, with no teacher API/offline implementation yet.
2. Local development uses MySQL (`halaka`). Confirm the deployment database before writing database-specific constraints.
3. Final teacher/group access mapping remains unresolved; do not invent a Group teacher foreign key. This does not block administrator data management.
4. Administrator attendance correction remains unauthorized; assignment passage editing and assessment creation/deletion are not included.
5. The later teacher phase still needs API/JWT, durable offline storage, revision/idempotency behavior, and conflict handling. The earlier decision was JWT with no `exp`; it does not apply to Filament's session login.
