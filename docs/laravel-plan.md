# Laravel + Filament implementation plan

**Status: the user installed Laravel and Sail in `backend/`. Local development uses MySQL with database `halaka`. The domain models and migrations were rebuilt for student-owned assignments with boolean `is_passed`, including both pivot tables. Filament 5.11.3 login/dashboard, administrator-only access, RoleSeeder, and development/test AdminUserSeeder are implemented. The administrator-account resource, four school resources, and nested full-management forms are implemented, with soft deletion/restoration for school records, validated assignment ranges and same-student assessment links. Teacher/API submission validation and demo school data remain planned.**

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
      Administrators/ AdministratorResource and list/create/edit pages
      Teachers/       TeacherResource and list/create/edit pages
      Students/       StudentResource and list/create/view/edit pages
      Groups/         GroupResource and list/create/edit pages
      TeachingSessions/ TeachingSessionResource and list/create/view/edit pages
      StudentSessions/ StudentSessionResource and list/create/view/edit pages
      QuranAssignments/ QuranAssignmentResource and list/create/view/edit pages
      QuranAssessments/ QuranAssessmentResource and list/create/view/edit pages
    Pages/            Add only when a standard resource cannot express the workflow
  Actions/
    SaveAdministrator.php, SaveTeacher.php, SaveTeachingSession.php, SaveStudentSession.php
    SaveAssignment.php, SaveAssessment.php, CorrectAssessment.php
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

All domain tables have generated primary keys and normal creation/update timestamps. School record models use SoftDeletes with an additive migration for `deleted_at`. Preserve linked history with restrictive foreign keys; archive only the selected records, without cascading archives/deletions.

| Order | Migration/table | Fields and constraints |
| --- | --- | --- |
| 1 | `users` | `id`, `name`, unique `email`, hashed `password`, framework authentication fields. The diagram's `password_hash` means this hashed storage; map to Laravel's `password` field explicitly, not a second password column. |
| 2 | `roles`, `role_user` | Role ID/name, unique role name; pivot with unique `(user_id, role_id)`. Seed Administrator, Teacher, Student; no student login UI. |
| 3 | `teachers` | `id`, required unique `user_id` referencing users. Name/email stay on User. |
| 4 | `students` | `id`, nullable unique `user_id`, required `name`, nullable string `parent_phone`. No manually entered student number; parent phone is not unique. |
| 5 | `groups`, `group_student` | Group ID/name; membership pivot with unique `(group_id, student_id)`. No teacher foreign key on Group. |
| 6 | `teaching_sessions` | Domain Session: `id`, `teacher_id`, nullable `group_id`, `timestamp`, `status` default `in_progress`. Use this table/model name to avoid collision with Laravel's framework `sessions` table. |
| 7 | `student_sessions` | `id`, `student_id`, `session_id` referencing `teaching_sessions`, `attendance_status` default `not_marked`, nullable `evaluated_at`; unique `(student_id, session_id)`. |
| 8 | `quran_assignments` | `id`, required `student_id`, `surah_name`, positive `from_ayah`/`to_ayah`, `type`, boolean `is_passed` default `false`. No assigning-session or repeat/source foreign key. |
| 9 | `quran_assessments` | `id`, `student_session_id`, `quran_assignment_id`, double `grade`, nullable text `comment`. No fixed grade range; validate that the assignment and assessing roster record belong to the same student in application logic. |
| 10 | Add soft deletes | Add nullable `deleted_at` to teachers, students, groups, teaching_sessions, student_sessions, quran_assignments, and quran_assessments. Keep roles and pivots intact; the separate users migration below adds account archival. |
| 11 | Add user soft deletes | Add nullable `users.deleted_at` for administrator-account archival. Preserve roles, profiles, and teaching history; archived users cannot authenticate. |

Framework `sessions`, cache, or other infrastructure migrations are included only if required by the chosen Laravel configuration. They are not school-domain entities.

**Later synchronization:** the diagram's `client_uuid`, server revision/idempotency support, and device sync metadata are future teacher-delivery requirements. Do not turn `sync_status` or `last_synced_at` into server columns by blindly copying the conceptual diagram. Do not add JWT/queue/submission tables in this first delivery. When the API phase begins, add the required schema and integrate existing assessment corrections with stale-write protection before accepting uploads.

Grades use `double` to accept numbers without a fixed decimal-place limit, as requested by the user. Do not impose a numeric range or default a missing grade to zero. Floating-point values have finite machine precision.

## 4. Eloquent relationships

| Model | Relationships |
| --- | --- |
| User | `roles()` belongsToMany Role; `teacher()` hasOne Teacher; `student()` hasOne Student. |
| Role | `users()` belongsToMany User. |
| Teacher | `user()` belongsTo User; `sessions()` hasMany TeachingSession. |
| Student | Optional `user()` belongsTo User; `groups()` belongsToMany Group; `studentSessions()` hasMany StudentSession; `assignments()` hasMany QuranAssignment. |
| Group | `students()` belongsToMany Student; `sessions()` hasMany TeachingSession. |
| TeachingSession | `teacher()` belongsTo Teacher; optional `group()` belongsTo Group; `studentSessions()` hasMany StudentSession. |
| StudentSession | `student()` belongsTo Student; `session()` belongsTo TeachingSession using `session_id`; `assessments()` hasMany QuranAssessment. |
| QuranAssignment | `student()` belongsTo Student; `assessments()` hasMany QuranAssessment. |
| QuranAssessment | `studentSession()` belongsTo StudentSession; `assignment()` belongsTo QuranAssignment. |

Derive student assignment/history views through these existing relationships; keep the student foreign key on assignments, and do not copy student IDs or surah/type fields to assessments. An assignment's student and an assessment's student must match.

## 5. Filament resources and associations

The five top-level resources use standard Filament forms, tables, relationship managers, and soft-delete/restore actions. An archive filter offers active/all/deleted records; deleted records can be restored. No force-delete actions are registered. Administrator policies cover nested actions as well as pages.

### Administrators

- AdministratorResource operates on Users filtered by the Administrator role, not a separate table. It provides list/create/edit, password reset, soft-delete/restore, and archive filters.
- SaveAdministrator authorizes, validates global email uniqueness, and saves the account and Administrator-role membership atomically. A blank password edit preserves the hash; forms never receive stored hashes. Existing roles are retained.
- UserPolicy denies deleting the currently authenticated User. DeleteBulkAction authorizes each selected record separately so it skips the current user. A User deleting-event guard also rejects self-deletion; no permanent-delete actions are exposed.
- All active administrators have the same privileges. Other administrators may archive/restore an account; archived users cannot log in and are excluded from authenticated-user lookup. Teacher/student User relationships include archived accounts so history remains readable.
- The development seeder searches including archived Users: it never duplicates or automatically restores an archived default account, nor resets an existing password.

### Teachers

- Search linked account name/email; create/edit teacher + User + Teacher role transactionally through SaveTeacher.
- Require an initial password; a blank edit preserves its secure hash. Never expose stored passwords/hashes. Emails remain unique across all users, including archived teacher profiles.
- Soft-delete/restore the Teacher profile, retaining its shared User account and roles for history and restoration.

### Students and groups

- Students have generated IDs, names, optional shared parent-phone numbers, and searchable group membership controls. Groups have names and student membership controls.
- Both forms update the same unique pivot transactionally. Include existing archived membership targets in selectors to avoid silently detaching them when saving.
- Student detail tabs provide session/attendance history, assignments, and assessments. Pending/Passed uses `is_passed`, independently of attempts or grades.
- Create/edit assignments in the student tab: complete local Quran catalog, valid surah/range/type, and explicit passing toggle. Retain all assessment attempts when changing assignment metadata.
- Soft-delete/restore students, groups, assignments, and individual attempts without cascading changes to linked records.

### Sessions and attendance

- Create/edit session teacher, optional group, date/time, and completion state. Creation includes an explicit multi-select roster; changing group later never rebuilds existing student records.
- SaveTeachingSession writes session/initial roster atomically. On completion, unmarked/unfinished active roster entries become absent.
- Session and student detail tabs share a roster manager for creating/editing attendance records, soft deletion, and restoration. Each student/session pair remains unique, including archived rows; restore an existing row instead of recreating it.
- Present attendance keeps or initializes `evaluated_at`; absent/not-marked clears it. Completed sessions require present or absent.

### Assignments and assessments

- Student assignment history shows every attempt with its grade/comment, assessing session/teacher, and a shared grade/comment correction shortcut.
- Student and session assessment tabs allow full create/edit, soft-delete, and restore. A dependent assignment selector follows the selected student's roster record.
- SaveAssessment validates same-student ownership and the displayed parent context before writing. Never duplicate passage/type/student fields on assessments. Grades are numeric with no fixed range or decimal-place limit.
- CorrectAssessment remains the transactional grade/comment-only shortcut. Full link edits use SaveAssessment; both preserve `is_passed` unless the administrator explicitly changes it through the assignment form.
- History uses belongsTo-withTrashed and hasManyThrough-withTrashedParents helpers so archived parents do not break saved references. Only the selected row is archived/restored.

## 6. Controllers, requests, and resources: what is actually needed?

| Component | First delivery decision |
| --- | --- |
| Custom admin CRUD controllers | **None.** Filament handles resource page actions. |
| Custom login/logout controller | **None for the panel.** Use Filament's authentication flow and Laravel sessions. |
| Teacher/API controllers | **None now.** Plan them when teacher implementation is approved. |
| JSON API Resources | **None now.** No API responses in this delivery. |
| Form Request classes | Not needed just to wrap Filament forms. Use shared domain validation/actions where it prevents inconsistent rules. |
| Filament Resources | AdministratorResource, TeacherResource, StudentResource, GroupResource, TeachingSessionResource, StudentSessionResource, QuranAssignmentResource, QuranAssessmentResource. |
| Shared application actions | SaveAdministrator, SaveTeacher, SaveTeachingSession, SaveStudentSession, SaveAssignment, SaveAssessment, and CorrectAssessment. Standard Filament relationship controls synchronize group membership transactionally. |
| Policies and panel access | Required. Enforce administrator-only access and allowed actions server-side. Hidden buttons alone are insufficient. |

Do not generate empty controllers or resources merely to fill a project tree. Keep simple behavior in the appropriate Filament resource; extract shared transactional rules into the shared actions above.

## 7. Seeders and tests

- RoleSeeder: stable role names without duplicating records.
- AdminUserSeeder: in local/development and test environments, create the default Administrator account with email `admin@admin.com` and password `123456789`. Hash the password using Laravel's configured hasher. Make seeding idempotent: do not duplicate the user or reset an existing password on subsequent runs. Do not provision this known default account in production; production credentials must be supplied separately.
- DemoSchoolSeeder: development/test-only teachers, students, memberships, student-owned pending/passed assignments, including pending work with several attempts and separate smaller-passage assignments. Never run demo seeds automatically in production.
- Test panel access: administrator allowed, guest redirected, teacher denied.
- Test atomic teacher creation, unique email, password hashing/reset, blank edit preserving the hash, and no hash leakage.
- Test nullable parent phone, shared sibling phone, generated IDs, both membership edit entry points, and duplicate-pivot prevention.
- Test session/student links, multiple assessments, stored Pending/Passed labels, repeated attempts without overwriting grades, and soft-delete/restore without losing history.
- Test correction authorization, numeric validation, transactional direct updates, and consistent display across entry points.
- Verify migration/rollback and database constraints. The revised assignment design has no circular reference. The user requested a clean restart: the old school migration files were replaced after rolling back their empty tables. Laravel infrastructure migrations remain unchanged. Do not apply this reset procedure to a populated deployment.

## 8. Execution checklist (after review)

- [x] Inspect environment; select Laravel 13, Filament 5, PHP 8.5 in Sail, and local MySQL 8.4.
- [x] Scaffold Laravel in `backend/`, preserving the teacher prototype/docs.
- [x] Create initial migrations and models/relationships, including both pivot tables.
- [x] Rebuild the school models and migrations for the revised class diagram and run them on local MySQL.
- [x] Verify migration rollback/re-run and model relationships, including repeated assessments and both pivots.
- [x] Add idempotent role and development/test administrator seeders.
- [ ] Add demo school seed data.
- [x] Configure `/admin` session authentication and enforce Administrator-role panel access.
- [x] Add administrator-only CRUD/soft-delete/restore policies for all school records, without force-delete actions.
- [x] Implement teacher/student/group resources and both membership form entry points.
- [x] Implement session/student history and shared grade/comment correction from student assignment history and session assessment rows.
- [x] Run resource/access/model tests, including password handling and Arabic RTL page rendering.
- [ ] Complete a manual review of desktop/mobile layouts with real school records.
- [ ] Update implementation status and record the actual setup/test commands.

## 9. Review points

1. Approved and implemented scope: **database + Filament only**, with no teacher API/offline implementation yet.
2. Local development uses MySQL (`halaka`). Confirm the deployment database before writing database-specific constraints.
3. Final teacher/group access mapping remains unresolved; do not invent a Group teacher foreign key. This does not block administrator data management.
4. Full administrator management is authorized, including attendance, assignment passages, assessment links, and soft-delete/restore. Teacher permissions remain separate; no permanent-delete UI is provided.
5. The later teacher phase still needs API/JWT, durable offline storage, revision/idempotency behavior, and conflict handling. The earlier decision was JWT with no `exp`; it does not apply to Filament's session login.

## Current model verification

Run from `backend/`:

```bash
./vendor/bin/sail artisan test
./vendor/bin/sail artisan migrate:status
```

The feature tests use isolated SQLite in memory. They cover repeated whole/decimal assessment grades on one assignment, explicit `is_passed` changes, both pivot relationships, duplicate membership rejection, and preserved session rosters after group membership changes. Local MySQL migrations are checked separately, including rollback and re-run. The separate AdminAccessTest verifies Arabic RTL login, administrator access, teacher/student/roleless rejection even locally, actual login behavior, password hashing and preservation on reseeding, and exclusion of the default account in production. AdminResourcesTest covers rendering all four resources, atomic teacher/account creation and password reset, global email uniqueness, both membership edit paths, historical roster preservation, optional/shared contact numbers, repeated-assessment history and status filters, corrections through all entry points, and server-side denial of non-administrator actions. These tests use SQLite in memory. The panel includes school resources; teacher-domain submission validation and offline/API behavior remain outside this delivery.

### Administration resource behavior

- Sidebar navigation groups: Administration (الإدارة) contains administrators, teachers, students, then groups; Teaching (التعليم) contains sessions, student-session records (طلاب الحصص), assessments, then assignments. The dashboard link stays first and ungrouped.
- The standard dashboard shows counts and links for all eight resources. These are navigation summaries, not analytics reports.
- Student detail tabs show historical sessions/attendance, assignment blocks, and all assessment attempts. The assignment modal includes the shared correction action for each attempt.
- Session detail tabs manage the roster and assessments recorded in that session. Student tabs additionally manage assignments. Create/edit/archive/restore actions are available to administrators only.
- `Student.assessments()` and `TeachingSession.assessments()` are derived `hasManyThrough` helpers for the relationship tables; they add no database columns. Domain fields and ownership remain defined only in the class diagram.
- Teacher account creation/updates authorize and validate before a transaction; a blank password preserves the existing hash. Forms never receive stored hashes.
- Membership forms use standard relationship selectors and database transactions. They update the existing unique `group_student` pivot and preserve historical rosters.

### Full-permission and archive verification

AdminFullPermissionsTest covers session create/edit without roster loss, attendance creation/correction, assignment passing and valid surah ranges, assessment CRUD and same-student validation, soft-delete/restore from top-level and nested tables, preserved accounts/pivots/history, and denied non-administrator/hard-delete actions. The additive soft-delete migration is applied on local MySQL without resetting existing data.

### Administrator-account verification

AdministratorManagementTest covers account creation and hashed passwords, edit/reset and preserved roles, protected self-deletion (policy, forged action, direct model deletion, and mixed bulk selection), archival/restoration, rejected archived-account login, global email uniqueness including deleted users, administrator-only resource scope, and safe reseeding without resurrecting an archived default account.

### Standalone teaching resources

Student-session records, assignments, and assessments have separate searchable lists and create/view/edit pages under Teaching. Existing student/session relationship tabs remain available. Each list supports archive filtering, soft deletion, restoration, and bulk archive/restore. Detail pages link to students, sessions, and assignments; roster and assignment detail pages open the filtered assessment list. No schema changes or data reset are required.

The standalone forms reuse domain actions. Assessment assignment choices follow the selected roster student; mismatched students are rejected on the server. An assignment can be reassigned only when retained assessment links remain consistent, including archived attempts. Validation errors appear next to their form fields. Grades remain unrestricted numeric values.

TeachingResourcesTest verifies all new pages and administrator access, create/edit flows, duplicate roster rejection including archived records, assignment ownership consistency, repeated integer/decimal/negative grades, assessment filters, and soft-delete/bulk restore with linked history retained.
