# Halaka — Project Study

Halaka is a school management study project for Quran classes, planned with **Laravel (PHP)**. The repository currently contains system design documents and a working teacher UI prototype. The Laravel application has not been scaffolded.

## Roles and access

- **Administrator:** overview of teachers, students, parents, groups, sessions, attendance, and assessments. Permission to correct teacher records remains an open decision.
- **Teacher:** manage sessions, record attendance, assess recitations, and assign memorization and revision passages.
- **Student:** optional account linked to a student record. Detailed student-facing screens remain to be designed.
- **Parent:** contact record linked to one or more children, with an optional account for access later. Connected parents can view only their children's session records. Notification channels and events remain undecided.

One account can have multiple roles.

## Teaching workflow

1. Create a session with a timestamp, one teacher, and an optional group.
2. Open the student roster and record each student's attendance.
3. In **Today's assessment**, assess previously assigned passages, recording a separate grade and optional comment for each passage.
4. In **Next session's assignments**, add one or more passages, mixing `new_memorization` and `revision`. Each passage identifies a surah and inclusive ayah range.
5. If a passage needs repetition, create a new assignment for the full passage or a smaller range, linked to its original assessment. Keep the original grade and assess the repeat separately later.

Each student has one attendance record per session. Group membership changes must preserve past session records.

## Planned Laravel implementation

- Use Eloquent models for the classes defined in the [class diagram](class-diagram.md), including `QuranAssignment` and `QuranAssessment`.
- Define tables, foreign keys, and unique constraints through migrations.
- Use a `group_student` pivot table for student memberships. Multiple memberships and optional session groups are initial assumptions awaiting confirmation.
- Store each session's date and time in one `timestamp` field.
- Implement authentication, role-based authorization, and validation of student ownership and Quran passage ranges.
- Preserve parent contact details and child relationships when connecting an account later.

## Design reference and open decisions

The [class diagram](class-diagram.md) is the single source of truth for model attributes, relationships, constraints, business rules, and open decisions. Keep unresolved questions there rather than duplicating them in this document. Prototype behavior is illustrative and does not settle open design decisions.

For future development and AI assistance, read this study and the diagram before making changes. Keep the Mermaid diagram and its written rules consistent; do not create duplicate diagram files unless requested.

## View the diagram

Open `docs/class-diagram.md` in VS Code and press **Ctrl+Shift+V**. Diagram rendering requires Mermaid support in the Markdown preview; the source remains editable without it.
