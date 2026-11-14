# Halaka · حلقة

Halaka is a Quran school management project that helps teachers organize classes and follow students' progress in memorization and revision.

The planned application will use **Laravel (PHP)** and support teachers, administrators, students, and parents.

## Status

Laravel and Sail are installed in `backend/`, using the local MySQL database `halaka`. The Arabic teacher prototype is preserved. Domain models and migrations follow the student-owned assignment design with boolean `is_passed` and multiple assessment attempts. Filament 5 is installed with an Arabic administrator-only login and dashboard at `/admin`; administrator, teacher, student, group, session, student-session, assignment, and assessment resources are implemented.

## Project structure

```text
Halaka/
├── backend/             Laravel application and Sail
├── docs/                Specifications and implementation plans
├── teacher-prototype/   Standalone teacher UI reference
├── AGENTS.md            Project instructions
└── README.md
```

## Home page

The public `/` route displays an Arabic RTL landing page in `backend/resources/views/welcome.blade.php`, with Halaka branding, a short overview, and links to the administrator panel. It uses standalone CSS and SVG without a frontend build.

## Administrator panel

Start Sail from `backend/` with `./vendor/bin/sail up -d`, then open http://localhost/admin.

For a fresh installation, run from `backend/` after configuring `.env` and installing Composer dependencies:

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
```

Do not regenerate an existing application key. Composer publishes Filament's assets through its `filament:upgrade` hook; no frontend build is needed for the standard panel.

The local/test administrator credentials are defined in [AdminUserSeeder](docs/laravel-plan.md#7-seeders-and-tests). Seeding again preserves an existing password. The default account is not created in production. Only users with the Administrator role can enter the panel; teachers and students are denied.

Arabic RTL and Tajawal are configured with Halaka colors and standard Filament components. Tajawal loads from the font provider when online. The dashboard links to all eight resources. The Teaching menu contains sessions, student-session records (طلاب الحصص), assignments, and assessments, each with separate list/create/view/edit pages. Administrators can create/edit other administrator accounts, teachers and their accounts, students, groups, sessions, rosters/attendance, assignments, and assessments. School records support soft deletion and restoration through the deleted-records filter. Linked history is retained without cascading deletion; no permanent-delete actions are offered. The logged-in user cannot delete its own account, including in bulk selection. Archived administrator accounts cannot log in until restored.

## MVP

The first milestone is the Laravel database and Filament administration panel, with no APIs. The full MVP later adds teacher sessions, Quran assessments and assignments, local saving, and server submission. The user installed the Laravel scaffold; the models and migrations are now created; Filament login/dashboard are installed; administrator, teacher, student, group, session, student-session, assignment, and assessment resources are implemented. Parent features and Telegram are deferred. See the [MVP scope](docs/mvp.md).

## Documentation

Read these in order; each owns a distinct part of the specification:

1. [MVP](docs/mvp.md) — scope, delivery milestones, and acceptance criteria.
2. [Class diagram](docs/class-diagram.md) — fields, relationships, constraints, and business rules.
3. [Laravel + Filament plan](docs/laravel-plan.md) — migrations, Eloquent relationships, resources, policies, actions, tests, and review checklist.

4. [Teacher application plan](docs/teacher-plan.md) — Delivery 2 screens, offline storage, API/submission design, and tests.

Project creation, models, migrations, and Filament panel installation are approved. School management resources are approved and implemented; remaining teacher/API work follows the separate delivery review. The first milestone has no APIs.

## Teacher prototype

- [Teacher prototype](teacher-prototype/README.md) — teacher UI and interaction reference.

The teacher prototype uses simulated login and local demo data; it is not the production application. Administration is specified in the Laravel + Filament plan.

To try the prototype, open `teacher-prototype/index.html` in your browser and select **جرّب دون تسجيل**. No installation is required.

## For coding agents

Read [AGENTS.md](AGENTS.md), then follow the documentation order above before implementation.

## Contributing

Contributions and feedback are welcome. Start with the documentation above to understand the design and current scope.
