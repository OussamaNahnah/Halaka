# Halaka — Administrator prototype

Open `index.html` directly in a browser and choose **جرّب دون تسجيل**. No server, package installation, or build step is needed. This Arabic RTL prototype uses the same Halaka wordmark, Tajawal font, forest-green/gold palette, and quiet list/form styling as the teacher prototype. It uses a wide administration workspace on desktop. On mobile, navigation becomes a compact top row, cards and forms stack in one column, and the page fills the available screen width without a fixed phone frame.

## Screens and behavior

- Simulated administrator login/logout. Any valid email and nonempty demo password work; credentials are not stored or transmitted.
- Teachers: list, search, create, and edit names and unique emails.
- Students: list, search, create, and edit names and automatically generated IDs; assign several groups.
- Groups: list, search, create, and edit; select multiple student members.
- Editing membership from a student or group updates the same underlying relationship. Removing a membership does not delete a student.
- Changes persist in this browser under `halaka-admin-prototype-v1`. Storage failures are reported; data then lasts only for the visit.

Teachers, students, and groups share one administrator demo dataset. The teacher prototype currently has its own independent dataset: changes here do not automatically appear there. Both interfaces will use the same Laravel backend in the implemented application. Font loading requires internet; local fallback fonts work offline.

## Source

- `js/data.js`: sample records, persistence, shared data helpers.
- `js/views.js`: login, navigation, lists, and forms.
- `js/app.js`: UI state, validation, membership updates, and events.
- `css/style.css`: shared design tokens, controls, and responsive layouts.

This is a UI prototype, not real authentication or a Laravel API. Creating a teacher does not create a working login or send an invitation. Parent/Telegram features, record deletion, notifications, and reporting dashboards are outside scope.

Model alignment: teacher name/email live in `User`, with `Teacher.user_id` linking the profile. Students use `name`, optional `parent_phone`, and nullable `user_id`; accounts are deferred. Groups have `id` and `name`; the UI's `studentIds` array represents the many-to-many membership, not an extra scalar model attribute. Teachers are assigned to sessions, not groups. Password hashes and generated IDs are server-managed and are not editable form fields. The prototype stores no passwords or hashes.

## Quick verification

Create a teacher, create a student, then create a group and select the student. Edit the student to change memberships, verify the group count updates, and reload to check persistence. Try duplicate emails to check validation. Check the layout on desktop and a narrow screen.

## MVP API endpoints (planned)

Required for the administrator MVP; not implemented yet.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/admin/login` | Administrator login. |
| POST | `/api/admin/logout` | Log out. |
| GET | `/api/admin/profile` | Get administrator profile. |
| GET | `/api/admin/teachers` | List/search teachers. |
| POST | `/api/admin/teachers` | Create a teacher and linked user account. |
| GET | `/api/admin/teachers/{id}` | Get a teacher for editing. |
| PUT | `/api/admin/teachers/{id}` | Update teacher name and email through the linked user. |
| GET | `/api/admin/students` | List/search students. |
| POST | `/api/admin/students` | Create a student; database generates the ID. |
| GET | `/api/admin/students/{id}` | Get a student and group memberships. |
| PUT | `/api/admin/students/{id}` | Update name, optional parent phone, and memberships. |
| GET | `/api/admin/groups` | List/search groups. |
| POST | `/api/admin/groups` | Create a group with selected students. |
| GET | `/api/admin/groups/{id}` | Get a group and its members. |
| PUT | `/api/admin/groups/{id}` | Update group name and student memberships. |

No deletion endpoints are needed for this MVP. Parent accounts and Telegram endpoints are deferred. Teacher endpoints are listed in the [teacher prototype README](../teacher-prototype/README.md).

The student form includes an optional parent/guardian phone number. Siblings may share it. No parent account, separate Parent entity, or Telegram linkage is created.

Student IDs are generated automatically (locally in this demo, by the database in Laravel). The student form has no student-number input.
