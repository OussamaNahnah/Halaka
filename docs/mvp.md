# Halaka — MVP scope

Deliver a working Arabic teacher application in Laravel: run a Quran class, record student work, save on the device, and submit to the server. The current HTML prototype demonstrates the interface; it is not the implemented MVP.

## Architecture decision

Use **one Laravel application**, one repository, one database, and one deployment for MVP. Do not split the frontend and backend into independent applications.

- **Administration:** server-rendered Laravel pages for managing teachers, students, and groups. A separate REST API for every administration action is not required; prototype endpoint tables describe operations, not a mandatory architecture.
- **Teacher interface:** mobile-first HTML, CSS, and JavaScript served by the same Laravel application. Cache the interface for offline use as described below.
- **Teacher API:** a small same-application API for downloading classroom data, retrieving accepted sessions, and atomically uploading new or edited sessions with retry/duplicate protection. Local teaching work does not require an online request for each field.
- **API authentication:** use JWT bearer authentication for protected API routes, with **no expiration time** (no `exp` claim). No scheduled token expiry or refresh-token flow is required for MVP. Validate token signature, authenticated identity, and permissions on the server. Use the selected JWT implementation's normal server-side logout invalidation mechanism; deleting the browser copy alone must not leave the same token usable after logout. JWT package and browser token storage remain implementation choices. Never store passwords in offline drafts.
- **Authentication and authorization:** one shared authentication system with role-based access for teachers and administrators. Laravel owns validation, permissions, and database writes.
- **Offline storage:** the browser stores drafts and queued submissions; Laravel receives them when the teacher sends after connectivity returns. Offline preparation and initial login require an online connection.

The administrator and teacher prototypes are UI references to integrate into this application, not separately deployed production services. An API is part of Laravel here, not a separate backend project.

## Included

- Mobile-first teacher web application in the browser; offline use is required for MVP, not a later enhancement. The administrator interface remains desktop-first and responsive; administrator offline support is not required.
- After an initial online sign-in and preparation, cache the teacher interface, scripts, styles, font, and icons so it can reopen without internet. Serve the application over HTTPS and use a service worker for the offline application shell. No external font or CDN request may be required for offline operation.

- Administrators assign the teacher's initial password when creating the linked user account and can reset it later. Production Laravel stores only a secure password hash, never a readable password; no invitation-email flow is required for MVP. Teachers may change their password through their profile.
- Real teacher login and logout; authorize access to the teacher's own groups and sessions.
- Basic administrator login and creation/editing of teachers, students (including an optional parent/guardian contact phone), groups, and memberships. A separate administrator UI prototype is available at [admin-prototype](../admin-prototype/README.md).
- Create a session by choosing its date, time, and group; load group members automatically.
- List, open, and edit sessions. No session deletion.
- While the session is in progress, show each student as not evaluated yet or recorded. Finishing a student marks them present. When the teacher finishes the session, students not explicitly finished are recorded as absent; show their names in the submission confirmation so the teacher can correct omissions.
- One student worksheet with today's assessments and next assignments. Support multiple new-memorization and revision passages, separate grades, optional comments, and full or partial repeat assignments preserving earlier grades.
- Finish a student and navigate between students. Save entered work before navigation.
- Use IndexedDB to save teaching work durably on the device. Cache required group, student, Quran catalog, and prior-assignment data before offline use.
- Finish and send a completed session atomically to Laravel; retain pending uploads if offline and offer Retry. Prevent duplicates and preserve unsent edits.
- Clearly distinguish session completion from upload state. Reopen saved records for correction and resubmit changes without deleting earlier work silently.

## Deferred

- App installation prompts, home-screen installation polish, and native Android/iOS applications. Browser use is sufficient for MVP.
- Automatic background synchronization and continuous polling. Send only when requested, with explicit Retry after connection returns.

- Parent registration, parent account linking, and parent screens.
- Telegram bots, subscriptions, notifications, email/SMS reports.
- Student accounts and student-facing screens.
- Advanced administrator analytics and reporting dashboards.
- Advanced analytics, exports, and multi-device conflict-resolution UI. Reject conflicting updates rather than silently overwriting them.

`Parent` and its relationships are excluded from the current class diagram. They can be designed when parent features are introduced. The role model can remain, with teacher and basic administrator access in MVP.

## Confirmed business decisions

- Grading is decided: each assessed passage receives a numeric grade from **0 to 10 inclusive**. Decimal grades are allowed by the model. Blank/unassessed passages do not automatically become zero.
- Attendance rule is decided: students not explicitly finished are absent when the session is completed. Completion is an explicit teacher action, not inferred from the presence of a numeric grade; a first-session student may have no earlier assignment to assess.

## Offline scope and resource use

The first sign-in and classroom download require internet. Cache only the teacher's needed groups, rosters, Quran catalog, and prior assignments. Saving attendance, assessments, and assignments must work while offline. JWT has no automatic expiration. If authentication is invalidated by logout, preserve unsent drafts and require sign-in before sending them. Show unavailable data or failed local saves clearly rather than claiming that preparation or saving succeeded.

Keep the offline cache versioned and avoid replacing it in a way that loses drafts. Browser data can be cleared or evicted, so local storage is not a permanent backup. Store text records only in MVP; no audio/video uploads, location tracking, or recurring network polling. Provide visible local save and upload states.

## MVP acceptance

A teacher can sign in, cache their classroom data, create a grouped session, record attendance, assess multiple passages and assign next work, close and reopen the browser application while still offline without losing the interface or saved drafts, then manually submit after connection returns. Test this with the connection disabled, including cold reopening after closing the tab. Retrying cannot duplicate the session. The teacher can retrieve accepted data from the server and edit/resubmit it. An administrator can create/edit teachers and students and manage group memberships. Parents and Telegram are not needed for this workflow.

## Implementation order

1. Laravel database, teacher authentication, initial school data, and read endpoints.
2. Session submission/retrieval, validation, ownership checks, and duplicate protection.
3. Connect the mobile-first Arabic teacher web interface; add the service-worker application cache, local assets, IndexedDB drafts, upload queue, and manual retry handling.
4. Verify the complete teaching flow, offline recovery, and session corrections.

See the [project study](project-study.md), [class diagram](class-diagram.md), and [prototype guide](../teacher-prototype/README.md) for the broader design and current prototype limits.
