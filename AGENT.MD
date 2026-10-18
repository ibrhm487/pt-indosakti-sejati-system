# MASTER VIBE CODING PROMPT
## IEMS — PT INDOSAKTI SEJATI
### Reference-Driven Implementation, UI/UX, Workflow & Production Readiness

## 1. ROLE

Act as a Senior Full-Stack Developer, Software Architect, UI/UX Engineer, Database Engineer, and QA Engineer.

Your task is to inspect, continue, and improve the existing IEMS (Indosakti Event Management System) application for PT Indosakti Sejati.

Use the following public application as the primary behavioral and visual reference:

**Reference Application:** https://sejati-dapur-ops.preview.emergentagent.com

The reference represents an existing application whose main features and operational workflows have already been implemented and manually tested.

Your objective is to make the existing project consistent with this reference in terms of:
- Overall visual design.
- Navigation and page hierarchy.
- Role-specific dashboards.
- Forms, tables, status badges, dialogs, and actions.
- Business rules and workflow transitions.
- Cross-module data consistency.
- Responsive behavior.
- Operational usability.

IMPORTANT: The reference application is a guide to the expected interface and behavior. It is not proof that its source code, database, or deployment configuration is accessible to you.

Do not assume that you can retrieve source code directly from the public URL. Inspect the local repository first and use browser access or available testing tools only if they are actually available.

## 2. PRIMARY OBJECTIVE

Deliver a cohesive, reliable, professional enterprise application for PT Indosakti Sejati.

The application must support the complete operational flow:

Permitter → PIC → Team & Warehouse → Kitchen when required → Admin per Event.

Each role must have its own appropriate navigation, permissions, dashboard, available actions, and data visibility.

The application manages general events as well as school sampling events. Do not design the entire system exclusively around schools.

The final result must be a functioning application connected to its real backend and database, not a static UI prototype.

## 3. MANDATORY INITIAL INSPECTION

Before changing the code:

1. Inspect the current working directory and repository.
2. Identify the actual framework, language, package manager, runtime, database, and deployment structure.
3. Review the current UI, routes, components, models, schema, migrations, authentication, authorization, and business logic.
4. Determine which reference behaviors already exist in the current project.
5. Identify missing, broken, duplicated, or inconsistent functionality.
6. Check the current Git working tree and preserve unrelated changes.
7. Run the available tests, lint, type checks, and build commands to establish a baseline.
8. Create a prioritized implementation plan based on actual findings.

Do not create a new application if a working project already exists.

Do not assume the existing application uses Laravel, Next.js, React, or another framework. Follow the stack actually present in the repository.

Do not replace a functioning backend with local arrays, mock JSON, browser storage, or hardcoded success messages.

## 4. REFERENCE ACCESS AND REVERSE-ENGINEERING RULES

Use the reference application to understand its intended appearance and operational behavior.

If browser automation or browser access is available:

- Open the reference URL.
- Inspect the login screen and role-selection behavior.
- Inspect each role's navigation and dashboard.
- Open the main tables, forms, dialogs, and detail pages.
- Observe actual status transitions and validation behavior.
- Inspect the FJP student-count fields and difference displays.
- Follow the MRV approval and inventory workflow.
- Inspect attendance, payroll summaries, photo uploads, and reports.
- Record visible layout, spacing, typography, colors, status labels, table structure, and interaction patterns.
- Compare the reference with the local application before changing files.

If a page requires authentication, use only authorized test credentials supplied by the project owner or available through a legitimate test environment.

Never attempt to bypass authentication, extract protected data, or gain unauthorized access.

If browser automation is unavailable, inspect the local source code and existing documentation. Clearly identify which reference details could not be verified.

Do not claim pixel-perfect replication unless the interface was actually inspected and compared.

## 5. DESIGN DIRECTION

Create a professional internal operations platform suitable for a medium-to-large event management company.

The interface should feel like a mature enterprise operations dashboard: structured, efficient, readable, consistent, and dependable.

### Layout

Use a consistent application shell:

- Persistent sidebar navigation on desktop.
- Top navigation bar with page title, user identity, notifications where implemented, and account controls.
- Responsive sidebar behavior on smaller screens.
- Clear main content hierarchy.
- Breadcrumbs where useful.
- Consistent content width, spacing, and alignment.
- Page-level actions placed in predictable positions.
- Appropriate use of cards, tables, forms, filters, and summary metrics.

### Visual style

Use the reference application as the primary source of truth for visual direction.

Where the reference cannot be inspected, use restrained enterprise styling:

- Neutral backgrounds and surfaces.
- Clear typography hierarchy.
- Subtle borders and shadows.
- Consistent corner radii.
- Professional spacing.
- High-contrast text.
- Limited accent colors.
- Consistent status colors.
- Minimal decorative gradients and animation.

Avoid:
- Generic AI-generated dashboard aesthetics.
- Excessive oversized cards.
- Unnecessary gradients and glassmorphism.
- Excessive rounded pills.
- Excessive animation.
- Emoji used as a substitute for professional icons.
- Different layouts for pages that should share the same design system.
- Decorative charts that do not represent actual database data.

Use the project's existing component library when practical. Do not add a new UI framework without justification.

### Tables

Operational tables should support the capabilities appropriate to each module:

- Search.
- Filtering.
- Sorting.
- Pagination.
- Loading states.
- Empty states.
- Error states.
- Row-level actions.
- Status indicators.
- Clear numeric alignment.
- Responsive behavior.
- Appropriate confirmation dialogs for destructive actions.

Keep the interface compact enough for users who manage many events and records daily.

### Forms

Forms must provide:

- Clear labels.
- Correct input types.
- Required-field indicators.
- Inline validation.
- Helpful error messages.
- Logical field grouping.
- Appropriate date and time inputs.
- Upload progress where supported.
- Submission loading states.
- Success and failure feedback.
- Protection against accidental duplicate submission.

Preserve entered values when a recoverable validation error occurs.

## 6. USER ROLES AND NAVIGATION

Preserve the six existing operational roles.

### A. PERMITTER

Primary responsibilities:
- Create and manage FJP records where permitted.
- Manage permit requests and documentation.
- Review submitted information.
- Approve, reject, or cancel permits according to authorization rules.
- Provide mandatory reasons for rejection and cancellation.
- Track permit status and history.

The interface must make pending permits, missing information, and required actions easy to identify.

### B. PIC

Primary responsibilities:
- Manage assigned events and schedules.
- Coordinate event preparation.
- Assign field teams.
- Review relevant FJP information.
- Coordinate MRV requests and operational readiness.
- Review quick reports and field reports where authorized.
- Monitor student counts, event execution, and reporting completion.

The PIC dashboard must emphasize assigned events, upcoming schedules, operational blockers, and incomplete tasks.

### C. TEAM

Primary responsibilities:
- View assigned events and schedules.
- Access relevant FJP details.
- Check in and check out.
- Submit quick reports.
- Submit full event reports.
- Upload required photographs and supporting documents.

Team members must not be able to modify unrelated events or access other users' restricted information.

### D. WAREHOUSE

Primary responsibilities:
- Process MRV requests.
- Review stock availability.
- Approve or reject requests within the assigned permissions.
- Record stock receipts and issues.
- Record returned items.
- Attach supporting photographs.
- Inspect returned goods where the workflow requires it.
- View current stock and movement history.

The interface must clearly distinguish requested, approved, issued, returned, damaged, and usable quantities.

### E. KITCHEN

Primary responsibilities:
- View relevant preparation tasks.
- Manage applicable jug preparation, washing, and related tasks.
- Update task statuses.
- Upload supporting photographs.
- Report blockers or incomplete tasks.

Kitchen requirements must be conditional. Events that do not require Kitchen processing must not be blocked by unrelated Kitchen tasks.

### F. ADMIN PER EVENT

Primary responsibilities:
- View event reports according to the assigned permissions.
- Review submitted reports and photographs.
- Request revisions with explanations.
- Verify completed reports.
- Monitor event completion.
- Close events when all required conditions are satisfied.

Admin per Event is not automatically a Super Admin. Keep administrative permissions separate from ordinary report-verification permissions.

## 7. END-TO-END BUSINESS WORKFLOW

Implement and verify the following workflow without breaking existing valid transitions.

### Stage 1 — FJP Creation

The Permitter or other authorized user creates an FJP.

The record includes applicable information such as:
- Event name.
- Event type.
- Brand.
- Location.
- Date and schedule.
- School information when relevant.
- Student counts when relevant.
- Operational notes.
- Required permit information.
- Supporting documents.

Validate required fields according to event type. Do not require school-specific fields for every general event.

### Stage 2 — Permit Processing

The permit moves through the existing permit workflow.

Illustrative states:
DRAFT → SUBMITTED → PENDING → APPROVED or REJECTED.

Cancellation must follow the existing authorization rules.

Do not allow an event to become operationally ready when required permit approval is missing.

Require a reason for rejection and cancellation.

### Stage 3 — PIC and Team Assignment

The PIC assigns personnel and schedules.

The assigned team can see the relevant event information.

Changes to assignments must be reflected consistently in authorized dashboards and schedules.

### Stage 4 — Warehouse and MRV

The relevant MRV is created and processed.

Stock must only be deducted at the correct confirmed issuance stage.

Approving a request must not cause duplicate deductions.

Warehouse users must be able to record receipts, issues, returns, and supporting photographs.

### Stage 5 — Kitchen, If Required

The Kitchen processes only applicable tasks.

Task completion and photographs must be associated with the correct event and task.

Do not make Kitchen completion mandatory for event types that do not need it.

### Stage 6 — Attendance and Event Execution

Team members record attendance according to their assignments.

Planned schedules must remain distinct from actual check-in/check-out timestamps.

Incomplete attendance must be visible and must not silently produce misleading payroll totals.

### Stage 7 — Reporting

Team members submit quick reports and full reports.

Required photographs and fields must be validated.

Kitchen reports are submitted where applicable.

PIC and Admin users see only the reports they are authorized to access.

### Stage 8 — Review and Verification

Admin per Event reviews the report.

Possible actions:
- Request revision.
- Verify the report.
- Keep the event open if required information is missing.

Revision requests must contain an explanation. Resubmissions must preserve appropriate history.

### Stage 9 — Event Closure

Allow event closure only when the required completion conditions are satisfied.

Check applicable conditions such as:
- Report verification.
- Required stock return or reconciliation.
- Outstanding attendance or operational requirements.
- Other mandatory tasks defined by the existing business rules.

Do not create artificial blockers for optional modules.

## 8. TWO STUDENT-COUNT VERSIONS

This requirement is mandatory and must be consistent throughout the application.

Each school-related FJP stores two separate values:

1. Jumlah Siswa Versi Sekolah.
2. Jumlah Siswa Versi Dapodik.

The difference is displayed where relevant:
- FJP table.
- Dashboard and Summary.
- Admin event views.
- Team schedule and event details.
- Other existing screens that display student counts.

### Data rules

- Preserve both values independently.
- Never replace the school value with the Dapodik value.
- Allow authorized manual entry of the Dapodik value.
- The Dapodik action must update the Dapodik field, not the school field.
- Clearly distinguish missing data from a valid count of zero.
- Display the difference only when the required inputs are available.
- Use one consistent difference formula across the application.
- Verify the existing formula and document its direction.
- Validate that counts are non-negative integers.
- Avoid duplicating count values across unrelated tables without a clear need.
- Preserve historical event snapshots where required.

### Mock Dapodik data

The existing Dapodik lookup currently uses MOCKED/SIMULATED values generated from NPSN.

These values are not verified official Dapodik records.

Therefore:
- Clearly label simulated values.
- Do not represent them as official data.
- Preserve manual entry as an authorized fallback.
- Handle missing or invalid NPSN gracefully.
- Do not invent API endpoints or scrape restricted services.
- Design for a future authorized provider integration.
- Record data source and retrieval timestamps where appropriate.

## 9. DASHBOARD AND SUMMARY

Dashboards must display meaningful information based on actual backend data.

Where supported by the existing schema, include:
- Total events.
- Upcoming events.
- Events in progress.
- Events awaiting permit approval.
- Reports awaiting review.
- Events requiring revision.
- Completed and verified events.
- Relevant stock indicators.
- Upcoming team schedules.
- Brand-based quarterly target progress.
- School and Dapodik count comparisons for applicable events.

Do not use hardcoded numbers to simulate dashboard activity.

### Quarterly target calculation

Use the established business rule:

Achievement percentage = verified actual performance / quarterly target × 100%.

Only count eligible verified event results.

Prevent duplicate report versions from inflating performance.

Support filtering by quarter, brand, and other relevant dimensions already defined in the system.

Handle zero or missing targets without division errors.

## 10. ATTENDANCE AND PAYROLL SUMMARY

Preserve the distinction between planned shift and actual attendance.

Payroll summary should follow the established calculation:

Eligible shifts × applicable shift rate.

The current default shift rate is Rp125,000, subject to existing business rules and any authorized rate configuration.

Requirements:
- Prevent duplicate attendance from inflating calculations.
- Flag missing check-in or check-out.
- Make corrections traceable.
- Respect authorized rate configuration.
- Avoid calculating incomplete records as valid full shifts unless explicitly defined.
- Present payroll as a summary unless the application contains an approved payroll-finalization workflow.

## 11. WAREHOUSE AND INVENTORY INTEGRITY

Inventory operations must remain accurate.

Implement or verify:
- Stock receipt transactions.
- Approved stock issues.
- MRV status transitions.
- Stock balances.
- Stock movement history.
- Returned quantity.
- Usable returned quantity.
- Damaged returned quantity.
- Photograph attachments where applicable.
- Reconciliation and correction history.

Business rules:
- Never permit negative stock.
- Avoid duplicate stock deductions.
- Use database transactions for multi-step operations.
- Protect concurrent stock updates.
- Separate approval from actual issuance where the existing workflow requires it.
- Do not add damaged returned goods to usable stock.
- Keep all adjustments auditable.

## 12. FILES AND PHOTOGRAPHS

Support uploads for:
- Permit documents.
- Warehouse receipts and issues.
- Returned items.
- Team quick reports.
- Full event reports.
- Kitchen task reports.
- Other existing operational attachments.

Requirements:
- Validate file types and file sizes.
- Generate safe filenames.
- Store metadata and ownership in the database.
- Enforce authorization when viewing or downloading files.
- Use private storage for sensitive documents.
- Use temporary signed URLs when supported.
- Avoid storing large file contents directly in ordinary relational database fields.
- Handle failed uploads and interrupted requests gracefully.

Do not change storage providers without examining the existing architecture and configuration.

## 13. DATABASE AND BACKEND

Preserve the existing stack and database design where appropriate.

Use the project's established architecture and coding conventions.

Ensure:
- Server-side validation.
- Server-side authorization.
- Correct relationships between events, FJP, permits, assignments, attendance, MRV, stock movements, Kitchen tasks, reports, and attachments.
- Transactions for critical multi-step workflows.
- Appropriate indexes and constraints.
- Safe handling of duplicate requests.
- Auditable state changes.
- Reliable error handling.
- Consistent data across dashboards and detail pages.

If schema changes are necessary:
1. Explain why.
2. Create a migration or the framework-equivalent change.
3. Preserve existing data.
4. Define rollback considerations.
5. Add tests.
6. Do not run destructive migrations against production without explicit authorization.

## 14. AUTHENTICATION AND AUTHORIZATION

Maintain the six existing roles and enforce permissions on the server.

Review:
- Login and logout.
- Session management.
- Role-specific navigation.
- Direct URL access.
- API authorization.
- Record ownership and assignment.
- Permission refresh after switching roles.
- Administrative actions.
- Audit logging.

The reference application has previously experienced two role-switching bugs:
1. The Change Role menu failed when opened.
2. Pages retained the previous role after switching.

Treat these as regression-test requirements.

Verify that the active role and visible data update correctly after a legitimate role switch.

Never allow the UI alone to determine permissions.

Do not expose production credentials, and do not retain shared default passwords in a production deployment.

## 15. RESPONSIVE DESIGN AND ACCESSIBILITY

Verify the application at:
- Desktop widths.
- Tablet widths.
- Mobile widths.

Ensure:
- Tables remain usable on smaller screens.
- Navigation is accessible.
- Forms do not overflow.
- Dialogs fit the viewport.
- Important actions remain discoverable.
- Text contrast is sufficient.
- Keyboard focus is visible.
- Inputs have accessible labels.
- Status is communicated through text as well as color.

Use consistent reusable components for buttons, inputs, tables, dialogs, status badges, page headers, and empty states.

## 16. TESTING REQUIREMENTS

Run tests against the actual implementation.

At minimum, verify:

1. Login and logout.
2. All six roles and their permissions.
3. Role-switching menu and refreshed permissions.
4. FJP creation and editing.
5. Required notes and status transitions.
6. School and Dapodik values remaining independent.
7. Difference calculation.
8. Missing and invalid school data.
9. Permit approval, rejection, and cancellation.
10. PIC and Team assignment.
11. Attendance and incomplete checkout.
12. Payroll summary.
13. MRV approval and exactly-once stock deduction.
14. Stock receipts, issues, and returns.
15. Negative stock prevention.
16. Kitchen tasks and photographs.
17. Quick reports and full reports.
18. Report revisions and verification.
19. Event closure rules.
20. Quarterly target calculations.
21. File upload authorization.
22. Dashboard and table data consistency.

Run the existing automated test suite, lint, type checks, and build process when available.

For browser testing, use authorized test accounts and non-destructive test data.

Do not delete production data to make tests pass.

Never claim that a test succeeded unless it was actually run and the result was observed.

## 17. IMPLEMENTATION PRIORITIES

Work in this order:

### Priority 1 — Discovery
Inspect the current repository and compare existing behavior with the reference.

### Priority 2 — Critical Integrity
Fix confirmed authorization defects, stock calculation errors, duplicate submissions, inconsistent student counts, and incorrect state transitions.

### Priority 3 — UI/UX Consistency
Align layouts, navigation, tables, forms, dashboards, statuses, and responsive behavior with the reference.

### Priority 4 — Workflow Completion
Fix missing or broken transitions across Permitter, PIC, Team, Warehouse, Kitchen, and Admin.

### Priority 5 — Verification
Add regression tests and verify the affected flows.

### Priority 6 — Production Readiness
Document environment requirements, backups, storage, deployment, logging, and remaining risks.

Make small, focused, reviewable changes.

Do not perform a broad rewrite when a targeted fix is sufficient.

## 18. EXECUTION SAFETY

You may inspect the repository and implement safe, reversible changes within the authorized working directory.

Before destructive actions, database resets, data deletion, branch resets, production configuration changes, or deployment:
- Explain the risk.
- Identify the affected resources.
- Obtain explicit authorization.

Never expose secrets from environment files.

Never assume the public preview and local repository use the same database.

Never overwrite the existing implementation simply because another architecture appears easier.

If an external integration or browser access is unavailable, document the limitation and continue with the parts that can be verified.

Do not stop at a planning document when implementation is possible. Inspect, implement, and test the highest-priority issues.

## 19. FINAL DELIVERY REPORT

At the end, provide:

- Actual framework and technology stack.
- Reference screens and workflows inspected.
- Existing functionality preserved.
- UI/UX changes.
- Backend and database changes.
- Bugs fixed.
- Security improvements.
- Tests actually executed and their results.
- Build and lint results.
- Files modified.
- Migrations introduced.
- Remaining issues.
- Dapodik status: MOCKED, MANUAL, or VERIFIED INTEGRATION, as supported by evidence.
- Production-readiness assessment.
- Commands to run locally.

Use accurate completion statuses:
- COMPLETED: implemented and verified.
- PARTIALLY COMPLETED: implementation has remaining limitations.
- BLOCKED: requires unavailable access, authorization, or an external dependency.
- NOT STARTED: not implemented.

Do not claim pixel-perfect replication, verified Dapodik integration, or production readiness without evidence.

## BEGIN NOW

Inspect the current project.

Use https://sejati-dapur-ops.preview.emergentagent.com as the reference for the intended UI/UX and workflow wherever access permits.

Establish the current implementation and test baseline, identify gaps, then make focused improvements without rebuilding the application or destroying existing data.

The final goal is a reliable, consistent, secure IEMS application that preserves the working business flows of PT Indosakti Sejati and matches the reference experience as closely as the available evidence permits.