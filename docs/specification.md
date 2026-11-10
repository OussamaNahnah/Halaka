# Halaka — specification entry point

Start here to understand the requirements before implementing a task. This is an index, not a replacement for the linked specifications.

## Requirement tree

```text
README.md                         Project overview and current status
AGENTS.md                         Instructions for coding agents
 docs/
   specification.md              This entry point
   mvp.md                        MVP scope, architecture, acceptance, delivery order
   class-diagram.md              Authoritative classes, rules, constraints, open decisions
   project-study.md              Broader requirements and school workflow context
   README.md                     Documentation overview
 teacher-prototype/
   README.md                     Teacher prototype usage, limitations, proposed operations
   index.html                    Arabic mobile-first interaction reference
   js/screens/                   Teacher screen implementations
   css/                          Visual reference
 admin-prototype/
   README.md                     Admin prototype usage, fields, proposed operations
   index.html                    Responsive administrator interaction reference
   js/                           Admin screens, demo data, and interactions
   css/                          Visual reference
```

## Which file answers which question?

| Question | Read |
| --- | --- |
| What should be delivered now, and what is deferred? | [MVP scope](mvp.md) |
| Laravel structure, browser offline support, and submission architecture? | [MVP architecture](mvp.md#architecture-decision) |
| Fields, relationships, attendance, grades, repeats, and synchronization states? | [Class diagram and rules](class-diagram.md) |
| Roles and general Quran-school workflow? | [Project study](project-study.md), subject to MVP scope |
| Teacher screen interactions and proposed API operations? | [Teacher prototype](../teacher-prototype/README.md) and its source |
| Administrator forms and proposed operations? | [Administrator prototype](../admin-prototype/README.md) and its source |

## Current implementation status

The repository contains documentation and two plain HTML/CSS/JavaScript prototypes. Their data stores are independent. Laravel, real authentication, shared database, durable offline queue, and server synchronization are planned; do not assume they exist because the documents describe them. Inspect the repository before implementation and update this status when it changes.

## Before coding

Read the scope and model in full, then the relevant prototype and source. Use current scope/model decisions when a prototype's historical documentation differs. Endpoint tables describe required operations; the single-Laravel architecture does not require separate APIs for every admin screen. Check open decisions rather than assuming, for example, that the example grade 12/20 defines the grading scale.

When a requirement changes, edit its owning document and affected references instead of adding another competing specification. Keep the prototypes usable as references while implementing the real application.
