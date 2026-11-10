# Halaka — Documentation

Detailed project status, planned features, repository structure, and contribution guidance. For the general introduction, see the [main README](../README.md).

A school management study project for Quran classes, planned with **Laravel (PHP)**. Halaka helps teachers organize sessions, track attendance, and follow each student's memorization and revision.

## Project status

The project is in the **system design and UI prototyping phase**. An Arabic teacher prototype is available; the Laravel application has not been scaffolded yet.

## Planned features

- Users with Administrator, Teacher, Student, and Parent roles.
- Student groups, teacher sessions, and attendance tracking.
- Multiple memorization and revision assignments per student per session.
- Separate assessment grades and comments for each passage.
- Repeat assignments that preserve earlier assessment results.
- Parent contact records, optional parent accounts, and future notifications.

## Try the teacher prototype

Open [teacher-prototype/index.html](../teacher-prototype/index.html) locally in a browser and select **جرّب دون تسجيل**. No installation or build step is required. GitHub displays the source file; download or clone the repository to run it locally.

The prototype uses Arabic, a phone-sized layout, sample data, simulated login, and browser storage. It includes session management, attendance, Quran assessments and assignments, and a profile with dark mode. See the [prototype guide](../teacher-prototype/README.md) for details.

## Repository structure

```text
docs/
  project-study.md       # Requirements, roles, workflow, and implementation plan
  class-diagram.md       # Authoritative model and open decisions
teacher-prototype/
  README.md             # Prototype usage and limitations
  index.html            # Browser entry point
  css/                  # Shared styles and screen layouts
  js/                   # Data, components, and screens
README.md               # Repository overview
```

## Documentation

- [Project study](project-study.md)
- [Class diagram and business rules](class-diagram.md)
- [Teacher prototype guide](../teacher-prototype/README.md)

## Contributing

1. Read the project study, diagram, and relevant prototype documentation.
2. Work on a focused branch and describe the change in a pull request.
3. Keep design documents and implementation consistent. Discuss unresolved requirements before treating them as final decisions.
4. For prototype changes, open `teacher-prototype/index.html` and verify the affected screens in Arabic, the mobile layout, and both color themes. Check modified JavaScript files with `node --check <file>` when Node.js is available.

Use sample data in contributions. Do not commit credentials or real student or parent information.
