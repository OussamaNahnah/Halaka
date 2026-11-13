# Halaka — instructions for coding agents

## Read before changing anything

Before editing files, installing dependencies, scaffolding Laravel, or running migrations, read these files in order:

1. `README.md` — repository overview and current status.
2. `docs/mvp.md` — delivery scope and acceptance criteria.
3. `docs/class-diagram.md` — domain fields, relationships, rules, and open decisions.
4. `docs/laravel-plan.md` — proposed implementation plan and review points.
5. For teacher work, read `docs/teacher-plan.md`, then `teacher-prototype/README.md` and inspect the relevant source. For administration work, use `docs/laravel-plan.md` and standard Filament components.

Read-only discovery needed to locate these files is allowed. At the start of implementation, briefly state the requested outcome and the requirements that apply. Do not implement unrelated features merely because they appear in the long-term study.

## Resolve requirements consistently

- Follow the user's current explicit instructions. Update affected specifications when the user changes a decision.
- `docs/mvp.md` owns scope and architecture; `docs/class-diagram.md` owns the data model and business constraints. `README.md` indexes them; `docs/laravel-plan.md` owns Delivery 1 implementation; `docs/teacher-plan.md` owns the proposed Delivery 2 implementation; do not maintain competing copies of the full requirements.
- Prototypes are visual/interaction references, not production implementations. Their README files contain historical descriptions; older flows do not override current MVP/model decisions.
- If authoritative documents contradict each other, or an unresolved decision affects the requested implementation, identify the specific conflict and request only the missing decision. Continue independent work where possible. Do not silently invent business rules.
- Preserve one authoritative Mermaid diagram in `docs/class-diagram.md`; do not regenerate duplicate SVG/Mermaid files unless requested.

## Communication and writing — apply to every task

- Match the user's language and level of detail. Use simple Arabic when appropriate to this conversation; use clear English when the user requests it. Understand typing mistakes without copying them or correcting the user's language unnecessarily.
- Lead with the concrete outcome. Keep updates and final answers short, readable, and useful; omit filler, repeated explanations, and unnecessary technical jargon.
- Write documentation and agent tasks with precise names, explicit scope, required behavior, and verifiable completion criteria. Use short paragraphs, lists, or tables where they improve scanning.
- Keep code identifiers and existing technical terms consistent with the project. Preserve the document's established language unless asked to translate it.
- Maintain a single source for each requirement. Link to the owning document instead of repeating full specifications, and remove stale or contradictory wording when updating a decision.
- Distinguish proposed, implemented, and verified work. Never describe a plan or prototype as a completed production feature.

## Skills

- Use the skills the user installs or associates with this project. Do not automatically search SkillsMP, download skills, or install additional skills; the user will select them.
- The user has installed [UI UX Pro Max](https://chatgpt.com/skills?skill_id=6af72142f7e48191a815a94c819c6a30). Use it for relevant UI/UX tasks when it is exposed in the current session's skill catalog. If unavailable, report that limitation without reinstalling it or claiming it was applied.
- The user has also associated [Frontend Design](https://chatgpt.com/skills?skill_id=6af7258175088191ad8a524a8df2b735). Use it for relevant frontend implementation and visual refinement when available in the session. Read its `SKILL.md` and required references before applying it. Preserve Halaka's established branding, Arabic RTL, minimal interface, and standard Filament components; do not redesign agreed screens or add a frontend framework merely to follow a skill. If unavailable, report the limitation without reinstalling or claiming it was applied.
- The user has also installed [Laravel Specialist](https://chatgpt.com/skills?skill_id=6af7241a952c81918d03e19a3de5d757), including its five reference guides. Use it for relevant Laravel work when available in the session. Read its `SKILL.md` and all five included reference guides before the first Laravel implementation task; reuse that context and revisit affected guides when needed. If unavailable, report the limitation without reinstalling or claiming to have read it. Its guidance does not authorize APIs or other work outside the current delivery.
- Read the relevant `SKILL.md` and required references before applying an available skill. Briefly name the skill when first used. Use only the skills needed for the task.
- Apply a full-stack perspective: keep database relationships, validation, authorization, Filament forms, and user-facing behavior consistent. UI work must preserve clear hierarchy, minimal text, accessible controls, Arabic RTL, responsive layouts, and useful feedback states.
- Skill instructions do not override user decisions, MVP boundaries, or the implementation review gate. Do not assume a skill is installed solely because its expertise would be useful.

## Implementation boundaries

- One Laravel application, repository, database, authentication system, and deployment. Delivery 1 is the data model and Filament panel only, with Laravel session authentication. No APIs, JWT implementation, teacher offline pipeline, or custom admin controllers in this milestone. The teacher API/offline application remains a later MVP delivery inside the same Laravel project.
- Use Filament resources, forms, tables, and relationship management for administration rather than building a custom administrator UI. Keep its standard layout and components, with Arabic RTL and Halaka branding. Do not build a separate admin API unless an actual integration requires it.
- Arabic RTL, Tajawal typography, forest green/gold Halaka branding, minimal forms and lists. Teacher interface is mobile-first. Administrator interface is wide on desktop and responsive on mobile, without a fixed phone frame.
- Follow the user review gate in `docs/laravel-plan.md`: do not start Laravel scaffolding, package installation, or migrations until the user approves implementation. Documentation changes and read-only inspection remain allowed.
- Teacher offline use is required for the later full MVP, not Delivery 1: cached UI/local assets, IndexedDB drafts, explicit sending, durable retries, and duplicate protection. Do not describe local-only data as uploaded.
- Follow the diagram's attendance rule: explicitly finishing a student marks them present; unfinished students become absent at session completion after confirmation. Missing grades are not zero.
- Student IDs are generated automatically. `parent_phone` is an optional student contact field. No Parent entity, Telegram integration, or parent accounts in MVP.
- Filament links sessions, student records, assignments, and assessments. Administrators may correct grades/comments directly on existing QuranAssessment records, without a separate change-log model/table; future uploads must respect these corrections through revision checks; attendance correction is not yet authorized.
- Sessions can be edited but not deleted. Protect saved student work and original grades when handling repeat assignments.
- In the later API delivery only, protected APIs use JWT without an expiration time (`exp` omitted); no refresh-token flow. Use the chosen implementation's normal server-side logout invalidation. Grades are numeric 0–10 inclusive, including decimals. Administrators assign initial teacher passwords and may reset them; production stores only secure hashes. Never store passwords in prototype storage or offline drafts.
- For the default local Laravel administrator account, follow `AdminUserSeeder` in `docs/laravel-plan.md`; preserve its specified demo credentials, hash the password, and restrict this default to development/test environments.
- Prototype login is simulated and never stores passwords. Production authentication must be real; never reuse simulated authorization as security.

## Verify and report

- Keep changes scoped to the task; preserve unrelated user work.
- For prototype JavaScript changes, run `node --check` on changed files and verify the affected flows. The teacher prototype must still open directly through `teacher-prototype/index.html` without a build step.
- When Laravel exists, use its actual repository commands and meaningful tests for changed behavior. Do not claim tests or browser checks you did not run.
- Verify documentation links after moves. Keep scope, diagram, and relevant usage instructions aligned.
- Report what changed, how it was checked, and any remaining limitations. Creating documentation is not implementing the feature.
