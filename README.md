# WorkDay HCM

A multi-tenant **Human Capital Management** platform that supports the full employee journey — hire, onboard, and train — built with **Laravel 13** (REST API) and **ReactJS** (SPA, in progress).

---

## Tech Stack

| Layer | Technology |
|---|---|
| **API** | Laravel 13, PHP 8.3+ |
| **Auth** | Laravel Sanctum (SPA cookie + token) |
| **RBAC** | spatie/laravel-permission v7 (teams mode) |
| **Database** | MySQL 8 / PostgreSQL 15 |
| **Queue** | Redis + Laravel Horizon |
| **Storage** | S3-compatible / local disk |
| **Mail** | Mailpit (local), Mailgun/SES (prod) |
| **PDF** | barryvdh/laravel-dompdf |
| **Frontend** | React 18, Vite, TypeScript, Tailwind CSS, shadcn/ui |
| **Testing** | Pest + PHPUnit (backend), Vitest + Playwright (frontend) |

---

## Repository Layout

```
WorkDay/
├── hcm-backend/          # Laravel 13 API
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/
│   │   │   ├── Middleware/           # SetTenant
│   │   │   └── Resources/
│   │   ├── Models/
│   │   ├── Policies/
│   │   └── Providers/
│   ├── database/
│   │   ├── factories/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/
│   │   └── api.php
│   └── tests/
│       ├── Feature/Api/V1/
│       └── Unit/Models/
├── hcm-frontend/         # React SPA (planned)
├── docker-compose.yml    # (planned)
└── plan.md               # Full project plan & roadmap
```

---

## Local Setup (Laragon)

### Prerequisites

- [Laragon](https://laragon.org/) with PHP 8.3+ and MySQL 8
- Composer
- Node.js 20+ (for frontend)

### Backend

```bash
cd hcm-backend

# Install dependencies
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Edit .env — set DB_* values for your Laragon MySQL instance

# Run migrations and seed demo data
php artisan migrate --seed

# Start the dev server
php artisan serve
```

The API is now available at `http://localhost:8000/api/v1`.

**Demo credentials** (seeded automatically):
- Tenant slug: `demo`
- Email: `admin@demo.com`
- Password: `password`

### Running Tests

The test suite uses SQLite in-memory — no additional database setup needed.

```bash
php artisan test
```

Current status: **274 tests, 558 assertions — all passing.**

---

## Authentication

All protected routes require:

1. A valid Sanctum token in the `Authorization: Bearer <token>` header.
2. An `X-Tenant: <slug>` header to scope the request to a tenant.

### Login

```http
POST /api/v1/login
Content-Type: application/json

{
  "email": "admin@demo.com",
  "password": "password",
  "tenant": "demo"
}
```

Response includes a `token`, the user's `roles`, and their `permissions` list.

---

## API Reference

All routes are prefixed with `/api/v1` and require `Authorization` + `X-Tenant` headers unless noted.

### Auth

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/login` | Authenticate, receive token |
| `GET` | `/me` | Current user + permissions |
| `POST` | `/logout` | Invalidate token |

---

### Core HR

#### Departments

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/departments` | Paginated list (filter: `search`, `is_active`) |
| `GET` | `/departments/tree` | Full nested tree |
| `POST` | `/departments` | Create department |
| `GET` | `/departments/{id}` | Show department |
| `PUT` | `/departments/{id}` | Update department |
| `DELETE` | `/departments/{id}` | Soft delete |

#### Positions

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/positions` | Paginated list (filter: `department_id`, `is_active`) |
| `POST` | `/positions` | Create position |
| `GET` | `/positions/{id}` | Show position |
| `PUT` | `/positions/{id}` | Update position |
| `DELETE` | `/positions/{id}` | Soft delete |

#### Employees

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/employees` | Paginated list (filter: `department_id`, `status`, `search`) |
| `GET` | `/employees/org-chart` | Top-level employees with nested direct reports |
| `POST` | `/employees` | Create employee record |
| `GET` | `/employees/{id}` | Show employee with department, position, manager |
| `PUT` | `/employees/{id}` | Update employee |
| `DELETE` | `/employees/{id}` | Soft delete |

---

### Hiring / ATS

#### Job Requisitions

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/job-requisitions` | Paginated list (filter: `status`, `department_id`) |
| `POST` | `/job-requisitions` | Create requisition (starts as `draft`) |
| `GET` | `/job-requisitions/{id}` | Show requisition |
| `PUT` | `/job-requisitions/{id}` | Update requisition |
| `DELETE` | `/job-requisitions/{id}` | Soft delete |
| `POST` | `/job-requisitions/{id}/approve` | Advance `draft` → `approved` |

#### Applicants

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/applicants` | Paginated list (filter: `search`) |
| `POST` | `/applicants` | Create applicant profile |
| `GET` | `/applicants/{id}` | Show applicant |
| `PUT` | `/applicants/{id}` | Update applicant |
| `DELETE` | `/applicants/{id}` | Soft delete |

#### Applications

Pipeline stages: `applied → screening → interview → offer → hired / rejected`

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/applications` | Paginated list (filter: `stage`, `job_requisition_id`) |
| `POST` | `/applications` | Submit application |
| `GET` | `/applications/{id}` | Show with applicant, requisition, interviews, offer |
| `PATCH` | `/applications/{id}/stage` | Move to next stage (`rejection_reason` required when rejecting) |
| `DELETE` | `/applications/{id}` | Soft delete |

#### Interviews

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/interviews` | Paginated list (filter: `application_id`) |
| `POST` | `/interviews` | Schedule interview (`scheduled_at` must be future) |
| `GET` | `/interviews/{id}` | Show interview |
| `PUT` | `/interviews/{id}` | Record result (`pass` / `fail`) and feedback |
| `DELETE` | `/interviews/{id}` | Soft delete |

#### Offers

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/offers` | Paginated list |
| `POST` | `/offers` | Create offer (starts as `draft`) |
| `GET` | `/offers/{id}` | Show offer |
| `PUT` | `/offers/{id}` | Update offer details |
| `DELETE` | `/offers/{id}` | Soft delete |
| `POST` | `/offers/{id}/send` | `draft` → `sent`; advances application to `offer` stage |
| `PATCH` | `/offers/{id}/status` | `accepted` → application `hired` / `declined` → application `rejected` |

---

### Onboarding

#### Onboarding Templates

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/onboarding-templates` | Paginated list (filter: `is_active`, `department_id`, `search`) |
| `POST` | `/onboarding-templates` | Create template |
| `GET` | `/onboarding-templates/{id}` | Show template with tasks |
| `PUT` | `/onboarding-templates/{id}` | Update template |
| `DELETE` | `/onboarding-templates/{id}` | Soft delete |

#### Onboarding Tasks

Tasks belong to a template. Assignee roles: `new_hire`, `hr`, `manager`, `it`.

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/onboarding-tasks` | Paginated list (filter: `onboarding_template_id`, `assignee_role`) |
| `POST` | `/onboarding-tasks` | Create task within a template |
| `GET` | `/onboarding-tasks/{id}` | Show task |
| `PUT` | `/onboarding-tasks/{id}` | Update task |
| `DELETE` | `/onboarding-tasks/{id}` | Soft delete |

#### Onboarding Assignments

Represents a template being run for a specific employee. Statuses: `pending → in_progress → completed`.

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/onboarding-assignments` | Paginated list (filter: `employee_id`, `status`) |
| `POST` | `/onboarding-assignments` | Assign template to employee |
| `GET` | `/onboarding-assignments/{id}` | Show with employee, template, task completions |
| `PUT` | `/onboarding-assignments/{id}` | Update status or start date |
| `DELETE` | `/onboarding-assignments/{id}` | Soft delete |
| `PATCH` | `/onboarding-assignments/{id}/tasks/{taskId}/complete` | Mark a task done (auto-advances to `in_progress`) |

---

## RBAC

Roles and permissions are managed via `spatie/laravel-permission` with `teams` mode enabled, so each role is scoped per tenant.

| Role | Key Permissions |
|---|---|
| **Super Admin** | All permissions |
| **HR Admin** | All permissions |
| **Hiring Manager** | `hiring.*`, `hr.employee.view` |
| **Trainer** | `training.*` |
| **Employee** | `training.course.view`, `hr.employee.view` |

### Permission Groups

| Group | Permissions |
|---|---|
| `hr` | `hr.employee.view`, `hr.employee.manage`, `hr.department.manage` |
| `hiring` | `hiring.requisition.*`, `hiring.application.*`, `hiring.interview.schedule`, `hiring.offer.*` |
| `onboarding` | `onboarding.template.view/manage`, `onboarding.assignment.view/manage`, `onboarding.document.verify` |
| `training` | `training.course.view/publish/manage`, `training.enrollment.manage`, `training.report.view` |

---

## Multi-Tenancy

- All requests must include the `X-Tenant: <slug>` header.
- The `SetTenant` middleware resolves the tenant and binds it to `app('tenant')`.
- Every Eloquent query is scoped to `tenant_id`.
- Roles and permissions are further scoped per tenant via Spatie's teams feature.

---

## Development Progress

| Phase | Status | Scope |
|---|---|---|
| **0 — Foundations** | ✅ Complete | Auth, RBAC, multi-tenancy, test infrastructure |
| **1 — Core HR** | ✅ Complete | Departments, positions, employees, org chart |
| **2 — Hiring / ATS** | ✅ Complete | Requisitions, applicants, pipeline, interviews, offers |
| **3 — Onboarding** | ✅ Complete | Templates, tasks, assignments, task completion tracking |
| **4 — Training / LMS** | ⬜ Pending | Courses, modules, lessons, quizzes, certificates |
| **5 — Reporting** | ⬜ Pending | Dashboards, notifications, audit log |
| **Frontend** | ⬜ Pending | React SPA for all modules |

See [`plan.md`](./plan.md) for the full technical specification and detailed roadmap.
