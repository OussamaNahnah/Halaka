# Halaka — instructions for coding agents

## Read before changing anything

Before editing files, installing dependencies, scaffolding Laravel, or running migrations, read these files in order:

1. `README.md` — repository overview and current status.
2. `docs/specification.md` — requirements map and source-of-truth rules.
3. `docs/mvp.md` — scope, architecture, offline behavior, and acceptance criteria.
4. `docs/class-diagram.md` — entities, relationships, constraints, and open decisions.
5. `docs/project-study.md` — broader project context; deferred features are not current scope.
6. For interface work, read the relevant `teacher-prototype/README.md` or `admin-prototype/README.md` and inspect the relevant source files.

Read-only discovery needed to locate these files is allowed. At the start of implementation, briefly state the requested outcome and the requirements that apply. Do not implement unrelated features merely because they appear in the long-term study.

## Resolve requirements consistently

- Follow the user's current explicit instructions. Update affected specifications when the user changes a decision.
- `docs/mvp.md` owns scope and architecture; `docs/class-diagram.md` owns the data model and business constraints. `docs/specification.md` indexes them; do not maintain competing copies of the full requirements.
- Prototypes are visual/interaction references, not production implementations. Their README files contain historical descriptions; older flows do not override current MVP/model decisions.
- If authoritative documents contradict each other, or an unresolved decision affects the requested implementation, identify the specific conflict and request only the missing decision. Continue independent work where possible. Do not silently invent business rules.
- Preserve one authoritative Mermaid diagram in `docs/class-diagram.md`; do not regenerate duplicate SVG/Mermaid files unless requested.

## Implementation boundaries

- One Laravel application, repository, database, authentication system, and deployment. Administration uses server-rendered pages; teacher offline synchronization uses a small API inside the same application.
- Arabic RTL, Tajawal typography, forest green/gold Halaka branding, minimal forms and lists. Teacher interface is mobile-first. Administrator interface is wide on desktop and responsive on mobile, without a fixed phone frame.
- Teacher offline use is required for MVP: cached UI/local assets, IndexedDB drafts, explicit sending, durable retries, and duplicate protection. Do not describe local-only data as uploaded.
- Follow the diagram's attendance rule: explicitly finishing a student marks them present; unfinished students become absent at session completion after confirmation. Missing grades are not zero.
- Student IDs are generated automatically. `parent_phone` is an optional student contact field. No Parent entity, Telegram integration, or parent accounts in MVP.
- Sessions can be edited but not deleted. Protect saved student work and original grades when handling repeat assignments.
- Protected APIs use JWT without an expiration time (`exp` omitted); no refresh-token flow. Use the chosen implementation's normal server-side logout invalidation. Grades are numeric 0–10 inclusive, including decimals. Administrators assign initial teacher passwords and may reset them; production stores only secure hashes. Never store passwords in prototype storage or offline drafts.
- Prototype login is simulated and never stores passwords. Production authentication must be real; never reuse simulated authorization as security.

## Verify and report

- Keep changes scoped to the task; preserve unrelated user work.
- For prototype JavaScript changes, run `node --check` on changed files and verify the affected flows. Both prototypes must still open directly through `index.html` without a build step.
- When Laravel exists, use its actual repository commands and meaningful tests for changed behavior. Do not claim tests or browser checks you did not run.
- Verify documentation links after moves. Keep scope, diagram, and relevant usage instructions aligned.
- Report what changed, how it was checked, and any remaining limitations. Creating documentation is not implementing the feature.
