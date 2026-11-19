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
# Android emulator defaults to the Sail server at http://10.0.2.2/api.
flutter run
```

For a real device, pass `--dart-define=API_URL=https://YOUR_HOST/api` with your reachable server URL. Android defaults to the emulator host alias; other platforms default to `http://localhost/api`. Use HTTPS in production. Android debug builds permit local HTTP; release builds retain HTTPS defaults. iOS requires macOS/Xcode and a reachable HTTPS API. API URL is build configuration, not a saved preference. The existing scaffold includes Android and iOS; this change does not replace the deployed Vue app or Laravel `/teacher/` route.

```bash
flutter analyze
flutter test
flutter build apk --release --dart-define=API_URL=https://YOUR_HOST/api
```

## Storage and sending

Only profile and JWT are written through SharedPreferences, under `halaka-teacher-auth`. No passwords, school data, drafts, queue, preferences or recovery copies are persisted. Every new process fetches school data from Laravel. The Flutter client does not open IndexedDB or register a service worker; the existing browser client owns its legacy storage cleanup.

One unfinished/unacknowledged session is allowed. Student edits stay in process memory; Back preserves inputs without finishing the student. Closing/killing the app loses unsent work. Android back is blocked while a draft exists, but OS termination cannot be prevented. Send successfully before closing.

Finish/send freezes a payload and submission UUID. Transient failures retry the identical payload every 30 seconds while the app is running; mobile OS suspension may pause retries. Edits are locked until acknowledgement or explicit discard. Validation rejection unlocks the draft for corrections. Authentication can be renewed for the same profile without clearing the draft. A 409 never rebases automatically: inspect current server data and the retained local worksheet, then explicitly discard/reopen and re-enter corrections. This minimal version has no automatic conflict merge. After a lost response, refresh history before starting another session if you explicitly discard a queued submission.

All classroom reads must match the same school revision. Existing attempt identities are retained, API-only allowed fields are submitted, missing grades are never zero, decimal/negative finite grades are accepted, and unfinished students send no work. Full 114-surah names come from Laravel; positive ordered ayah ranges have no catalog maximum.

## Verification and limits

Tests cover profile/token-only writes, server logout, immutable retries, allowed attempt fields, absent-student payloads, grade validation, a mocked Laravel teaching flow, narrow/wide worksheet navigation, and unfinished assignment-form protection. Run analysis/tests above. Android debug build/install and the Arabic login screen were verified on the local emulator against the Sail API host. The Sail teacher backend suite passed (30 tests). A complete signed-in device teaching session remains to be checked with a Teacher account. The UI follows the existing Vue teacher app: bundled Tajawal, forest green/gold palette, compact session rows, a fixed create/resume action above the bottom navigation on the sessions screen, bottom action docks for session sending and student completion, gold unfinished-student rows, inline grading, expandable full-width notes, inline next-assignment forms, previous/next student navigation, and temporary dark mode. Automatic conflict merging is not included. Session metadata edits preserve the existing roster. Contextual headers keep back, title, date/progress and edit at the top; primary actions stay at the bottom. Session/student task screens hide the global tabs, and student previous/next controls share the finish dock. Create/edit uses a bottom sheet with separate date and time pickers. Login and profile use the same compact headers and spacing.

The home action strip and tabs share one opaque surface. The action strip does not add a second gesture-area inset; only the navigation bar handles it. Task docks retain their own safe-area padding. Evaluation passages use simple dividers within a section instead of nested bordered cards.
