# Quran teacher prototype

Open `index.html` directly in a browser. No server, installation, or build step is required. The Tajawal Arabic font loads from Google Fonts when online; offline, the interface uses a local fallback font. All other assets are local.

Choose **جرّب دون تسجيل**, or enter any correctly formatted email and any nonempty demo password. Login is simulated: credentials are neither stored nor transmitted. Sign-out clears the login screen state; demo teaching records remain in browser local storage. Use **إعادة ضبط البيانات التجريبية** on the session list to start over. If storage is blocked, changes remain in memory until the page closes.

## Walk through

1. Choose **بدء حصة**, select a group from the dropdown, then press **التالي**. The session starts at the current time and opens the group roster. Every student defaults to absent; mark those who attend as present or late to enable evaluation.
2. Mark attendance in the roster. Tap a student's name to open evaluation.
3. In **Today's assessment**, grade each previously assigned passage separately and optionally add a comment. For a seeded example, open the session dated today, then Yusuf Ahmed.
4. Save an assessment, then choose **Assign a repeat**. Select the full range or a smaller range. Its original grade remains unchanged.
5. In **Next assignments**, add multiple passages with a surah, ayah range, and memorization/revision type. Each is saved immediately. Create a later session with the same student to assess these passages.

## Prototype choices and limits

- Arabic is the default interface language, with right-to-left layout and Arabic date formatting. Internal enum values and saved surah identifiers remain unchanged for compatibility; responsive phone and desktop layouts, labeled forms, keyboard focus, and tab arrow-key navigation.
- Sample groups and students are fixed. The dropdown selects the group; Next loads its members. A group is required. Students left unchanged remain absent. Students with saved attendance, assignments, or assessments cannot be removed from that session.
- A sample catalog of seven surahs validates each passage's ayah range.
- Grades are numeric with no fixed grading scale. Example: enter 12 for the illustrative 12/20 result; the UI does not impose a denominator.
- Repeats link to the assessment that prompted them and keep separate grades. Only assignments from earlier sessions appear for assessment. One editable assessment per assignment per session is used in this prototype.
- Deleting a session removes its demo records and assessments of its removed assignments; surviving repeat assignments lose links to deleted assessments. This is a prototype cleanup rule, not a proposed production data-retention policy.
- No real authentication, Laravel backend, parent notifications, or student/group administration is implemented.

Only this folder is used by the prototype. The authoritative model is documented in [the class diagram](../docs/class-diagram.md); see also the [project study](../docs/project-study.md).

The prototype is displayed in a phone-sized canvas (390 × 844px on desktop, reduced to fit the available viewport), including on desktop. On smaller devices it fills the available screen width; desktop shows a rounded phone frame. Screens and cards remain in a single column.

Bottom navigation provides Home (sessions) and Profile. Profile offers a persistent dark-mode preference, a simulated password-change form, and sign-out. Passwords are never stored or sent; changing a password only shows a demo confirmation.

## Source structure

- `index.html`: entry point; loads CSS and ordered classic JavaScript files.
- `css/theme.css`: shared design tokens (colors, typography).
- `css/components.css`: base styles and shared controls.
- `css/screens.css`: phone layouts, screen-specific styles, and dark-mode variants.
- `js/data.js`: sample students, groups, Quran catalog, and local storage.
- `js/state.js`: current screen and selection state.
- `js/helpers.js`: escaping and display formatting.
- `js/components.js`: shared app shell, bottom navigation, theme handling, toast, and assignment form.
- `js/screens/`: login, session list, session form, roster, evaluation, and profile screens.
- `js/app.js`: routing, delegated event handlers, and startup.

Edit a screen in its matching file. Reuse the shared components and CSS classes to keep screens consistent. These are classic scripts sharing one global scope: retain the load order in `index.html` and avoid duplicate top-level names. No modules, bundler, or package installation is needed. Existing local storage keys are preserved.

Sessions can be marked completed with unchanged students recorded as absent, or reopened for editing. No minimum of two present students is imposed: actual attendance may include only one present student. Completion does not publish records or notify parents in this prototype. Parent access and production status rules remain future work.

Student evaluation separates memorization and revision in both tabs. Today's assessment shows pending earlier assignments and assessments recorded in the current session; work already assessed in an earlier session stays in that historical session. Repeat work is explicitly assigned as a new passage. Next assignments uses a compact “إضافة واجب” form for multiple independent passages.

## Current simplified teaching flow

Create a session by choosing date/time and a group. Its student list shows “لم يُقيّم بعد” until the teacher explicitly finishes that student. Open any student directly; the page shows today's assessments followed by next assignments, without tabs. “إنهاء الطالب” saves entered assessment grades, marks the student recorded, and returns to the list. Previous/next buttons navigate the roster without marking students complete. Save an open assignment or repeat form before finishing or navigating. Finishing marks the student present in this demo. This flow supersedes the earlier attendance-first and automatic-timestamp descriptions above; production model rules remain in the design documents.

The student screen now uses one compact worksheet: today's assessments and next assignments appear together, with memorization/revision labels on each passage. Assignment entry is collapsed until needed. One “إنهاء الطالب” button saves entered grades and completes the student. Adding assignments also preserves entered assessment grades. This replaces the earlier two-step interface.
