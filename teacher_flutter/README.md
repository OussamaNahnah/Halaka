# Halaka Flutter teacher app

Minimal Arabic RTL teacher client using the existing [Laravel teacher API](../docs/teacher-api.md). Login, paginated session history, new sessions with date/time and group, owned-session corrections, student grades/comments/repeat decisions, several next assignments, confirmed absent students, password change and server logout are implemented. Administration stays in Filament.

## Run

Use the installed Flutter/Dart SDK (Dart 3.14 or compatible). Start Laravel with Sail from the repository root:

```bash
cd backend
./vendor/bin/sail up -d
```

Create a Teacher account in Filament at `http://localhost/admin`. Then open another terminal at the repository root:

```bash
cd teacher_flutter
```

From this directory:

```bash
flutter pub get
# Defaults to https://h.oussamanh.com/api on every platform.
flutter run
```

The default API is `https://h.oussamanh.com/api` on all platforms. To override it for local Android emulator development, run `flutter run --dart-define=API_URL=http://10.0.2.2/api`. Use HTTPS in production. Android debug builds permit local HTTP; release builds retain HTTPS defaults. iOS requires macOS/Xcode and a reachable HTTPS API. API URL is build configuration, not a saved preference. The existing scaffold includes Android and iOS; this change does not replace the deployed Vue app or Laravel `/teacher/` route.

```bash
flutter analyze
flutter test
flutter build apk --release
```

## Storage and sending

Storage scope is owned by the [MVP native storage decision](../docs/mvp.md#flutter-teacher-rebuild--approved).

SQLite (`halaka_teacher_work.db`) stores one active snapshot per API URL/account: session fields, roster, grades/comments/repeat decisions, next assignments and partial assignment form, required assignment/catalog/group context, revision counters, conflict flag, and the frozen upload payload/submission UUID. Profile/JWT remain in SharedPreferences. Passwords and general school history are never written to SQLite; theme stays in memory.

Edits autosave in order. The UI reports saving/saved/error; a failed disk write is never described as saved. Reopening restores the active session before attempting online reads, so the saved worksheet is available even when offline. A new session still requires online classroom preparation. Switching account/API host cannot open another owner's snapshot. Logout/password change remain blocked while work is active.

Finish/send writes the immutable queue to SQLite before transmitting. Lost responses and reopening reuse the identical payload and UUID. Retries run while the app is open; reopening resumes pending work with valid authentication. A protected API 401 clears the invalid token and returns directly to the normal login screen; SQLite work remains for the same teacher after login. There is no guarantee of uploads while the app is killed/suspended. Validation failures unlock corrections; conflicts retain work for explicit review without silently changing base revisions.

A matching server acknowledgement clears the SQLite snapshot and releases new-session creation. Before queuing, the Home current-session card offers a confirmed Delete action for the local draft; cancelling local edits to an existing server session does not delete that server session. A database failure during acknowledgement cleanup leaves the original queue available for an idempotent retry. Ordinary queued work cannot be discarded. Conflicted local sessions offer “حذف الحصة من الجهاز” both on the session screen and in its options menu; confirmation removes only the SQLite snapshot and never sends a delete request to Laravel. Other queued/in-flight uploads remain protected. Uninstalling the app or clearing its storage removes local work.

All classroom reads must match the same school revision. Existing attempt identities are retained, API-only allowed fields are submitted, missing grades are never zero, decimal/negative finite grades are accepted, and unfinished students send no work. Full 114-surah names come from Laravel; positive ordered ayah ranges have no catalog maximum.

## Verification and limits

Tests cover profile/token preferences plus SQLite session recovery, server logout, immutable retries, allowed attempt fields, absent-student payloads, grade validation, a mocked Laravel teaching flow, narrow/wide worksheet navigation, and unfinished assignment-form protection. Run analysis/tests above. Android debug build/install and the Arabic login screen were verified on the local emulator against the Sail API host. The Sail teacher backend suite passed (30 tests). A complete signed-in device teaching session remains to be checked with a Teacher account. The UI follows the existing Vue teacher app: bundled Tajawal, forest green/gold palette, compact session rows, a fixed create/resume action above the bottom navigation on the sessions screen, bottom action docks for session sending and student completion, gold unfinished-student rows, inline grading, optional full-width notes toggled with a «ملاحظة» button, inline next-assignment forms, previous/next student navigation, and temporary dark mode. Automatic conflict merging is not included. Changing an unsent session’s group reloads its students and assignment context at a consistent server revision, with a fresh session identity and confirmation only if at least one student was explicitly evaluated. Failed reads leave the previous session intact. Uploaded sessions retain their historical group/roster in the Flutter editor. Contextual headers keep back, title, date/progress and edit at the top; primary actions stay at the bottom. Session/student task screens hide the global tabs, and student previous/next controls share the finish dock. Create/edit uses a bottom sheet with separate date and time pickers. Login and profile use the same compact headers and spacing.

The home action strip and tabs share one opaque surface. The action strip does not add a second gesture-area inset; only the navigation bar handles it. Task docks retain their own safe-area padding. Evaluation passages use simple dividers within a section instead of nested bordered cards. Optional notes are hidden behind a «ملاحظة» button, start at one line and expand automatically as text grows. Hiding a note preserves its saved text.

The home screen always shows the current local session and its state: in progress, uploading, waiting to upload, or conflict. Every grade/comment/assignment edit is saved to SQLite without a Save button; finishing the student is still explicit for attendance. Uploading shows a spinner. New session creation is guarded in the controller and UI until a matching submission/session acknowledgement arrives. HTTP success alone does not release the slot. Drafts and queued submissions survive process termination after successful local writes.

Launcher icons reuse the existing Halaka logo on an ivory background, including Android adaptive icons and all iOS sizes. Regenerate them with `python3 tool/generate_icons.py` from this directory (ImageMagick required).

Accepted history opens a read-only session summary with attendance and expandable student evaluations. Viewing does not create a local draft. Edit is explicit and unavailable while another local session exists. The summary uses a single GET `/api/sessions/{id}` containing `created_assignments`, `assessed_assignments`, and roster assessment attempts; no new Laravel endpoint is required. The summary uses `data.created_assignments` for “New assignments” within the selected session. The server supplies their immutable `created_in_session_id` origin; legacy assignments without an origin are not guessed into a session. Passing state is labelled current. Archived assessed passages remain readable from the same session response. Explicit editing restores that session’s active created assignments into the next-assignment section, preserves assessment identities, and strips read-only metadata before upload.

The read-only summary uses separate attendance totals, compact grade cards with optional notes, and a new-assignment section shown only when assignments exist. Empty/legacy-origin explanations are omitted for the fresh production dataset; no origin is inferred for existing development records. Current assignment status is labelled «الحالة الآن».

Checking «يحتاج إعادة» sets the grade to 0 and locks the grade input. Unchecking keeps 0 and enables editing. No default-grade setting is needed. Database version 3 removes the previous setting while preserving active work. Existing historical grades remain unchanged until edited.

Session history commits page changes only after a successful response, retries the same page after failures, and scrolls to the top after navigation. If records disappear and the current page is no longer valid, the list loads the last available page. Signing in or accepting a submission returns history to page 1.
