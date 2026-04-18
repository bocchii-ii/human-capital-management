# WorkDay — Project Plan

A Human Capital Management (HCM) platform for companies to manage **onboarding**, **hiring**, and **employee training**. Built with **Laravel 13** (API) and **ReactJS** (SPA frontend).

---

## 1. Goals

- Provide a single platform that supports the full employee journey: **hire → onboard → train → grow**.
- Be multi-tenant so multiple companies can operate in isolation on a single deployment.
- Expose a clean REST/JSON API from Laravel; consume it from a React SPA.
- Support role-based access (HR Admin, Hiring Manager, Trainer, Employee, Applicant).

## 2. Core Modules

### 2.1 Hiring (ATS — Applicant Tracking)
- Job requisition creation & approval workflow.
- Public careers page + job board listings.
- Applicant registration and resume upload.
- Pipeline stages: Applied → Screening → Interview → Offer → Hired / Rejected.
- Interview scheduling with calendar invites.
- Offer letter generation (PDF) and e-signature capture.
- Candidate-to-employee conversion once an offer is accepted.

### 2.2 Onboarding
- Onboarding checklist templates per role/department.
- Task assignment to new hire, HR, IT, and manager.
- Document collection (ID, tax forms, contracts) with upload + verification.
- E-signature on policies (handbook, NDA, code of conduct).
- Equipment & account provisioning tickets.
- Day-1 / Week-1 / Month-1 milestone tracking.
- Buddy / mentor assignment.

### 2.3 Employee Training
- Course catalog with categories (compliance, technical, soft-skills).
- Course builder: modules → lessons (video, PDF, rich text, quiz).
- Learning paths (sequenced courses for a role).
- Assignments with due dates, auto-assigned on role/department.
- Quizzes with pass/fail thresholds and retake rules.
- Certificates on completion (PDF, verifiable code).
- Progress dashboards for employee and manager.

### 2.4 Core HR (supporting)
- Employee directory & profile.
- Org chart (manager → report hierarchy).
- Departments, job titles, locations.
- Basic leave / time-off requests *(stretch)*.

## 3. User Roles & Permissions

| Role | Key capabilities |
|---|---|
| **Super Admin** | Tenant management, global settings |
| **HR Admin** | Full company-wide access to hiring/onboarding/training |
| **Hiring Manager** | Manage own requisitions, review candidates |
| **Trainer** | Build courses, grade submissions |
| **Employee** | View own profile, complete onboarding/training |
| **Applicant** | Apply to jobs, track application status |

**RBAC is implemented with [`spatie/laravel-permission`](https://spatie.be/docs/laravel-permission)** — the canonical role/permission package for Laravel.

- Roles and permissions are stored in the `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, and `role_has_permissions` tables (published via the package migration).
- Permissions are seeded per module (e.g. `hiring.requisition.create`, `onboarding.template.manage`, `training.course.publish`).
- Enforcement points:
  - Route/controller: `->middleware('permission:hiring.requisition.create')` and `->middleware('role:HR Admin')`.
  - Policies: `Gate`/Policy classes call `$user->can('...')`, which defers to Spatie.
  - Blade/API responses: use `$user->hasPermissionTo(...)` to gate fields.
- Frontend: the API returns the authenticated user's permission list on login; React guards routes and UI elements against that list (no business logic relies on the client-side check — the server is still authoritative).
- Multi-tenant note: scope roles per tenant via the package's `teams` feature (set `teams => true` in `config/permission.php`) so the same role name can exist independently per company.

## 4. Tech Stack

### Backend — Laravel 13
- PHP 8.3+
- MySQL 8 / PostgreSQL 15
- Laravel Sanctum (SPA auth via cookies + CSRF) or Passport (if 3rd-party API consumers).
- **`spatie/laravel-permission`** for RBAC (roles, permissions, middleware, policies — see §3).
- Queue: Redis + Laravel Horizon (emails, PDF generation, video transcoding).
- Storage: S3-compatible (local: Laragon filesystem) for resumes, documents, course media.
- Mail: Mailgun / SES; local uses Mailpit.
- PDF: `barryvdh/laravel-dompdf` for offer letters & certificates.
- Search: Laravel Scout + Meilisearch for candidates and courses.
- Testing: Pest + PHPUnit.

### Frontend — ReactJS
- Vite + React 18 + TypeScript.
- Routing: React Router v6.
- State/data: TanStack Query (server state) + Zustand (client state).
- Forms: React Hook Form + Zod validation.
- UI: Tailwind CSS + shadcn/ui (Radix primitives).
- Charts: Recharts for dashboards.
- Rich text: TipTap (course lesson authoring).
- Video: Plyr / Video.js for course playback.
- Testing: Vitest + React Testing Library + Playwright (E2E).

### DevOps
- Dockerfile + `docker-compose.yml` for app, db, redis, meilisearch, mailpit.
- GitHub Actions: lint, type-check, test, build.
- Environments: local (Laragon) → staging → production.

## 5. High-Level Architecture

```
[ React SPA ] ──HTTPS──> [ Laravel API (Sanctum) ]
                              │
           ┌──────────────────┼──────────────────┐
           │                  │                  │
        [ MySQL ]          [ Redis ]         [ S3 / Disk ]
                              │
                       [ Horizon Workers ]
                              │
                  (mail, PDFs, notifications, video)
```

- **API-first**: Laravel exposes `/api/v1/*`; React is the sole first-party client.
- **Multi-tenant**: single database with `tenant_id` scoping (global scope on Eloquent models).
- **Auth**: Sanctum SPA mode — cookie-based session for the React app.

## 6. Data Model (key entities)

- `tenants`, `users`, `employees`, `roles`, `permissions`
- Hiring: `job_requisitions`, `job_postings`, `applications`, `applicants`, `interviews`, `offers`
- Onboarding: `onboarding_templates`, `onboarding_tasks`, `onboarding_assignments`, `documents`, `signatures`
- Training: `courses`, `modules`, `lessons`, `quizzes`, `questions`, `enrollments`, `progress`, `certificates`
- Core: `departments`, `positions`, `locations`, `notifications`, `audit_logs`

## 7. Delivery Roadmap

> Legend: ✅ Complete · 🚧 In Progress · ⬜ Pending

### Phase 0 — Foundations ✅
- ✅ Laravel 13 API project (`hcm-backend`) created in Laragon.
- ✅ Laravel Sanctum — login, `me`, logout endpoints.
- ✅ `spatie/laravel-permission` v7 with `teams => true` for multi-tenant RBAC.
- ✅ Custom `TenantTeamResolver` scoping roles/permissions per tenant.
- ✅ `Tenant` model + `SetTenant` middleware (resolves from `X-Tenant` header).
- ✅ `RolesAndPermissionsSeeder` — 23 permissions, 5 roles seeded.
- ✅ `DatabaseSeeder` — Demo Company tenant + `admin@demo.com` Super Admin.
- ✅ SQLite in-memory test environment (PHPUnit).
- ✅ `WithTenant` test trait for shared feature-test setup.

### Phase 1 — Core HR & Employee Directory ✅
- ✅ `Department` model + migration (self-referential parent/child, SoftDeletes).
- ✅ `Position` model + migration (SoftDeletes).
- ✅ `Employee` model + migration (SoftDeletes, manager hierarchy).
- ✅ Factories for all three models.
- ✅ API Resources: `DepartmentResource`, `PositionResource`, `EmployeeResource`.
- ✅ Controllers: `DepartmentController` (+ tree), `PositionController`, `EmployeeController` (+ org-chart).
- ✅ Policies: `DepartmentPolicy`, `PositionPolicy`, `EmployeePolicy`.
- ✅ Routes: `/api/v1/departments`, `/api/v1/positions`, `/api/v1/employees` (all protected, tenant-scoped).
- ✅ Unit tests: `TenantTest`, `UserTest`, `DepartmentTest`, `PositionTest`, `EmployeeTest`.
- ✅ Feature tests: `AuthTest`, `DepartmentTest`, `PositionTest`, `EmployeeTest`.

### Phase 2 — Hiring / ATS ✅
- ✅ Migrations: `job_requisitions`, `applicants`, `applications`, `interviews`, `offers` (all SoftDeletes).
- ✅ Models: `JobRequisition`, `Applicant`, `Application`, `Interview`, `Offer` with full relationships.
- ✅ Factories with states (draft/approved/closed, rejected/hired, sent/accepted/declined, pass/fail).
- ✅ API Resources: `JobRequisitionResource`, `ApplicantResource`, `ApplicationResource`, `InterviewResource`, `OfferResource`.
- ✅ Controllers: all 5 with tenant isolation, filter/search, and domain actions:
  - `JobRequisitionController` — approve action (draft → approved).
  - `ApplicationController` — updateStage action (with rejection reason validation).
  - `OfferController` — send (draft → sent, advances application to offer stage) + updateStatus (accepted → hired / declined → rejected).
- ✅ Policies: `JobRequisitionPolicy`, `ApplicantPolicy`, `ApplicationPolicy`, `InterviewPolicy`, `OfferPolicy`.
- ✅ All 5 policies registered in `AppServiceProvider`.
- ✅ Routes: job-requisitions (+ approve), applicants, applications (+ stage patch), interviews, offers (+ send, status patch).
- ✅ Unit tests: `JobRequisitionTest`, `ApplicantTest`, `ApplicationTest`, `InterviewTest`, `OfferTest`.
- ✅ Feature tests: all 5 controllers covered (auth, tenant isolation, permission gates, domain rules).
- ✅ **Total: 212 tests, 426 assertions — all passing.**

### Phase 3 — Onboarding ✅
- ✅ Migrations: `onboarding_templates`, `onboarding_tasks`, `onboarding_assignments`, `onboarding_task_completions` (SoftDeletes on first three).
- ✅ Models: `OnboardingTemplate`, `OnboardingTask`, `OnboardingAssignment`, `OnboardingTaskCompletion` with full relationships.
- ✅ Factories with states (inactive, optional, inProgress, completed).
- ✅ API Resources: `OnboardingTemplateResource`, `OnboardingTaskResource`, `OnboardingAssignmentResource`, `OnboardingTaskCompletionResource`, `UserResource`.
- ✅ Controllers: `OnboardingTemplateController`, `OnboardingTaskController`, `OnboardingAssignmentController`.
  - `OnboardingAssignmentController` — `completeTask` action (marks task done, auto-advances status to `in_progress`).
- ✅ Policies: `OnboardingTemplatePolicy`, `OnboardingTaskPolicy`, `OnboardingAssignmentPolicy`.
- ✅ All 3 policies registered in `AppServiceProvider`.
- ✅ Routes: `onboarding-templates`, `onboarding-tasks`, `onboarding-assignments` (+ task complete patch).
- ✅ Unit tests: `OnboardingTemplateTest`, `OnboardingTaskTest`, `OnboardingAssignmentTest`.
- ✅ Feature tests: all 3 controllers covered (auth, tenant isolation, permission gates, domain rules).
- ✅ **Total: 274 tests, 558 assertions — all passing.**

### Phase 4 — Training / LMS 🚧

#### Phase 4a — Course Authoring ✅
- ✅ Migrations: `courses`, `course_modules`, `lessons` (all SoftDeletes, tenant-scoped).
- ✅ Models: `Course`, `CourseModule`, `Lesson` with full relationships.
- ✅ Factories with states (draft/published/archived/inactive for courses; video/pdf/text/quiz/optional for lessons).
- ✅ API Resources: `CourseResource`, `CourseModuleResource`, `LessonResource`.
- ✅ Controllers: `CourseController` (+ `publish` + `archive` actions), `CourseModuleController`, `LessonController`.
- ✅ Policies: `CoursePolicy` (+ `publish` ability), `CourseModulePolicy`, `LessonPolicy`.
- ✅ All 3 policies registered in `AppServiceProvider`.
- ✅ Routes: `courses` (+ publish/archive), `course-modules`, `lessons`.
- ✅ Unit tests: `CourseTest`, `CourseModuleTest`, `LessonTest`.
- ✅ Feature tests: all 3 controllers covered (auth, tenant isolation, permission gates, domain rules).
- ✅ **Total: 353 tests, 710 assertions — all passing.**

#### Phase 4b — Quiz Engine ✅
- ✅ Migrations: `quizzes` (unique per lesson, pass_threshold, max_attempts), `questions` (single_choice/multiple_choice/true_false), `question_options`, `quiz_attempts` (no SoftDeletes — audit history), `quiz_attempt_answers`.
- ✅ Models: `Quiz`, `Question`, `QuestionOption`, `QuizAttempt`, `QuizAttemptAnswer` with full relationships.
- ✅ Factories with states (singleChoice/multipleChoice/trueFalse for questions; correct for options; inProgress/submitted/passed/failed for attempts).
- ✅ API Resources: `QuizResource`, `QuestionResource`, `QuestionOptionResource` (hides `is_correct` from learners), `QuizAttemptResource`, `QuizAttemptAnswerResource`.
- ✅ Controllers: `QuizController`, `QuestionController`, `QuestionOptionController`, `QuizAttemptController` (+ `submit` action with full grading logic for all question types).
- ✅ Policies: `QuizPolicy`, `QuestionPolicy`, `QuestionOptionPolicy`, `QuizAttemptPolicy` (employee isolation + enrollment.manage override).
- ✅ All 4 policies registered in `AppServiceProvider`.
- ✅ Routes: `quizzes`, `questions`, `question-options`, `quiz-attempts` (except update) + `quiz-attempts/{id}/submit`.
- ✅ Unit tests: `QuizTest`, `QuestionTest`, `QuestionOptionTest`, `QuizAttemptTest`, `QuizAttemptAnswerTest`.
- ✅ Feature tests: all 4 controllers covered including grading correctness for all 3 question types, `is_correct` leak guard, max_attempts enforcement, resubmission block, employee isolation.
- ✅ **Total: 452 tests, 889 assertions — all passing.**

#### Phase 4c–4d ⬜
- ⬜ Enrollment & learning paths.
- ⬜ Progress tracking + completion detection (all required lessons done AND all quizzes passed).
- ⬜ Certificate generation (PDF via `barryvdh/laravel-dompdf`).

### Phase 5 — Reporting & Polish ⬜
- ⬜ Admin dashboards (hires per month, onboarding completion %, training compliance).
- ⬜ Notifications center.
- ⬜ Audit log viewer.
- ⬜ Accessibility & performance pass.
- ⬜ UAT + bugfix.

### Frontend — React SPA ⬜
- ⬜ `hcm-frontend/` project scaffold (Vite + React 18 + TypeScript).
- ⬜ Auth flow (login, token/cookie, permission-aware route guards).
- ⬜ Core HR screens: employee directory, org chart, department/position management.
- ⬜ Hiring screens: requisition board, applicant pipeline kanban, interview scheduler, offer workflow.
- ⬜ Onboarding screens: checklist builder, new-hire task view.
- ⬜ Training screens: course catalog, lesson player, quiz, certificate viewer.
- ⬜ Admin screens: tenant settings, role management, dashboards.

## 8. Non-Functional Requirements
- **Security**: CSRF, rate-limiting, encrypted PII, signed URLs for documents, 2FA for admins.
- **Compliance**: GDPR-style data export & delete per employee.
- **Performance**: API p95 < 300ms on list endpoints; SPA LCP < 2.5s.
- **Accessibility**: WCAG 2.1 AA for all employee-facing screens.
- **Observability**: Laravel Telescope (dev), Sentry (prod), structured JSON logs.

## 9. Repository Layout

```
WorkDay/
├── hcm-backend/           # Laravel 13 API (already created)
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   └── Middleware/
│   │   ├── Models/
│   │   └── Policies/
│   ├── routes/
│   │   └── api.php
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   └── config/
├── hcm-frontend/          # React + Vite SPA (to be created)
│   ├── src/
│   │   ├── features/      # hiring/, onboarding/, training/
│   │   ├── components/
│   │   ├── lib/
│   │   └── main.tsx
│   └── vite.config.ts
├── docker/
├── docker-compose.yml
└── plan.md
```

## 10. Open Questions
- Single-tenant per deployment vs. shared multi-tenant — which do we launch with?
- Do we need mobile apps in v1, or is responsive web sufficient?
- E-signature: build in-house (canvas + hash) or integrate DocuSign / Dropbox Sign?
- Video hosting: self-host (S3 + HLS) vs. Mux / Cloudflare Stream?
- Payroll integration in scope for v1? (Recommend: no — phase 2 product.)
