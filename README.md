# Halaka · حلقة

Halaka is a Quran school management project that helps teachers organize classes and follow students' progress in memorization and revision.

The planned application will use **Laravel (PHP)** and support teachers, administrators, students, and parents.

## Status

Currently in the design and prototyping phase. An Arabic teacher prototype is available; Laravel development has not started yet.

## MVP

The first milestone is the Laravel database and Filament administration panel, with no APIs. The full MVP later adds teacher sessions, Quran assessments and assignments, local saving, and server submission. The implementation plan is awaiting review. Parent features and Telegram are deferred. See the [MVP scope](docs/mvp.md).

## Documentation

Read these in order; each owns a distinct part of the specification:

1. [MVP](docs/mvp.md) — scope, delivery milestones, and acceptance criteria.
2. [Class diagram](docs/class-diagram.md) — fields, relationships, constraints, and business rules.
3. [Laravel + Filament plan](docs/laravel-plan.md) — migrations, Eloquent relationships, resources, policies, actions, tests, and review checklist.

4. [Teacher application plan](docs/teacher-plan.md) — Delivery 2 screens, offline storage, API/submission design, and tests.

Review the implementation plan before starting Laravel scaffolding, package installation, or migrations. The first milestone has no APIs.

## Teacher prototype

- [Teacher prototype](teacher-prototype/README.md) — teacher UI and interaction reference.

The teacher prototype uses simulated login and local demo data; it is not the production application. Administration is specified in the Laravel + Filament plan.

To try the prototype, open `teacher-prototype/index.html` in your browser and select **جرّب دون تسجيل**. No installation is required.

## For coding agents

Read [AGENTS.md](AGENTS.md), then follow the documentation order above before implementation.

## Contributing

Contributions and feedback are welcome. Start with the documentation above to understand the design and current scope.
