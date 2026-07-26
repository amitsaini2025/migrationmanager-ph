# Migration Manager - Immigration CRM System

A comprehensive Laravel-based Customer Relationship Management (CRM) system specifically designed for immigration consultancies and migration agencies to manage clients, applications, appointments, invoices, and all aspects of the immigration process.

## Purpose

- Streamline immigration case management from lead to visa approval
- Centralize client information, documents, and communication in one platform
- Automate appointment scheduling and reminders
- Track visa applications, progress, and important deadlines
- Manage invoices, payments, and financial transactions
- Provide secure client portal access for document submission and status tracking
- Offer comprehensive reporting for business insights and compliance

## Features

- **Client Management**: Complete client profiles with personal information, visa history, and documents
- **Matter & Application Tracking**: Monitor visa applications via matters with workflow stages and status updates
- **Appointment System**: Schedule consultations with calendar integration and automated reminders (Booking system)
- **Invoice & Payment Management**: Generate invoices, track payments, and manage receipts
- **Document Management**: Secure storage, electronic signatures, and organisation of client documents and checklists
- **Lead Management**: Track potential clients from inquiry to conversion with analytics
- **Office Visit Tracking**: Manage walk-in clients and office visit queues
- **Email Integration**: Built-in email management with client correspondence tracking
- **Quotation System**: Create and send professional service quotations
- **Team & Staff Management**: Role-based access control; dedicated `staff` table / `Staff` model with login analytics (CRM login no longer uses `admins` for staff)
- **Reporting & Analytics**: Comprehensive reports on clients, matters, and revenue
- **Client Portal API**: Mobile/API portal (Laravel Sanctum) for clients to view status, upload documents, message staff, and pay invoices/appointments
- **Admin Console**: Separate ops console for system settings, offices/branches, and client/staff administration (`routes/adminconsole.php`)
- **SMS Notifications**: Integrated SMS via Twilio and Cellcast providers
- **Broadcast Notifications**: In-app broadcasts to staff/agents with history; realtime via Laravel Reverb / Echo where configured
- **Cross-access & row-level visibility**: Staff see clients/leads they are allocated to (or hold a time-bound grant); exempt roles are fully audited; quick (15 min) and supervisor-approved (24 h) access from search; approver queue, grants dashboard, and CSV export (`/crm/access/*`)
- **Electronic Signatures**: Full signature workflow with templates and dashboard (`/signatures/*`, public `/sign/{id}/{token}`)
- **Form 956**: Agreement / Form 956 generation and PDF preview under `/forms/*`
- **Task Management**: Assignee/action system for tasks related to cases and clients
- **Company & Employer Sponsorship**: Full employer sponsorship management with company profiles, directors, trading names, Trust entities, nominations, and sponsorship tracking
- **CRM Sheets**: EOI/ROI confirmation & amendment flows, ART sheets, visa-type sheets
- **Appointment sync**: Booking calendar plus Bansal website appointment sync jobs
- **Windows Friendly**: Optimized for XAMPP on Windows environments

## Technology Stack

- **Backend**: Laravel 13.x (PHP 8.3+)
- **Frontend**: Bootstrap (via `app.min.css`), jQuery, DataTables, Tom Select (`mmSelect` bridge), Flatpickr, FullCalendar v6, Alpine.js, Tailwind CSS, Lucide icons (`IconHelper` / `@icon`)
- **Charts**: Chart.js **4.4.0** (jsDelivr CDN, `chart.umd.min.js`) for leads analytics, e-signature dashboards, client insights, staff login analytics, and financial analytics — one version across these pages
- **Build**: Vite 8.x (`laravel-vite-plugin` 3.x)
- **Realtime**: Laravel Reverb + Laravel Echo / Pusher JS (matter messages, broadcasts)
- **Database**: PostgreSQL (Primary), MySQL (optional for migration), SQLite (Development)
- **PDF Generation**: DomPDF for invoices and reports
- **Document Processing**: Python API service (`python_services/`) for DOCX to PDF conversion
- **Email System**: Laravel Mail with SendGrid (primary), SMTP/IMAP integration
- **Payment Integration**: Stripe, PayU payment gateways
- **File Storage**: Local storage with S3 support for attachments
- **Authentication**: CRM session guard `admin` → `Staff` provider; Client Portal API → Sanctum on `Admin` (clients/leads)
- **Development Environment**: XAMPP on Windows

## Prerequisites

- PHP 8.3 or higher (`^8.3` in Composer — any 8.3.x patch satisfies this)
- Composer
- Node.js and npm
- Python 3.x (for document conversion, optional)
- PostgreSQL 12+ (primary database)
- XAMPP (optional, for Apache; PostgreSQL must be installed separately on Windows)

## Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/viplucmca/migrationmanager.git
   cd migrationmanager
   # Or if using migrationmanager2:
   # cd migrationmanager2
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**
   ```bash
   npm install
   ```

4. **Environment setup**
   ```powershell
   copy .env.example .env   # If the repo has no .env.example, create .env manually
   php artisan key:generate
   ```
   - Optional: add **CRM access** variables (see [Configuration](#configuration) → CRM cross-access) for strict allocation, approvers, and grant TTLs.

5. **Database setup**
   - Create a PostgreSQL database
   - Update `.env` file with your database credentials:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=migration_manager
   DB_USERNAME=postgres
   DB_PASSWORD=
   ```
   - Ensure PHP has the `pdo_pgsql` extension enabled (e.g. `php -m` and confirm `pdo_pgsql` is listed, or enable it in `php.ini`)
   - Run migrations and seeders:
   ```bash
   php artisan migrate --seed
   ```

6. **Storage setup**
   ```bash
   php artisan storage:link
   ```
   - Create necessary directories (PowerShell):
   ```powershell
   New-Item -ItemType Directory -Force -Path storage/app/public/agreements, storage/app/public/checklists, storage/app/public/attachments
   ```

7. **Configure mail settings**
   Update `.env` with SendGrid and sender details:
   ```env
   MAIL_MAILER=sendgrid
   MAIL_FROM_ADDRESS=your_sender@yourdomain.com
   MAIL_FROM_NAME="Your Company Name"
   SENDGRID_API_KEY=SG.your_api_key_here
   SENDGRID_FROM_EMAIL=your_sender@yourdomain.com
   SENDGRID_BASE_URL=https://api.sendgrid.com
   ```

8. **Build frontend assets**
   ```bash
   npm run copy:flatpickr    # Flatpickr → public/ (required for date pickers)
   npm run copy:tom-select   # Tom Select → public/
   npm run copy:datatables   # DataTables → public/
   npm run copy:inputmask    # Inputmask → public/
   npm run build
   # Or for development:
   npm run dev
   ```

9. **Install Python dependencies** (optional, for DOCX to PDF document conversion)
   - If using document conversion, run from project root:
   ```bash
   cd python_services && pip install -r requirements.txt
   ```
   - LibreOffice may be required for high-quality DOCX conversion

10. **Configure payment gateways** (Optional)
    Add to `.env`:
    ```env
    STRIPE_KEY=your_stripe_publishable_key
    STRIPE_SECRET=your_stripe_secret_key
    
    PAYU_MERCHANT_KEY=your_payu_merchant_key
    PAYU_SALT=your_payu_salt
    ```

11. **Start the application**
    - If using XAMPP:
      - Point your virtual host to the `public` directory
      - Or access via `http://localhost/migrationmanager2/public` (adjust path if your folder name differs)
    
    - Using PHP's built-in server:
      ```bash
      php artisan serve
      ```
    
    - For queue workers (run in separate terminal):
      ```bash
      php artisan queue:work
      ```

## Development

### Running the application

For development with XAMPP:
1. Start Apache from XAMPP Control Panel; ensure PostgreSQL is running (install separately if needed)
2. Access the application at `http://localhost/migrationmanager2/public` (or `http://migrationmanager.local` if virtual host is configured)

For development with PHP built-in server:
```bash
php artisan serve
```

Access at: `http://localhost:8000`

### Background Jobs

Start the queue worker for processing background jobs:
```bash
php artisan queue:work
```

### Default Login Credentials

After running migrations with seed, use CRM **staff** credentials (session guard `admin` → `staff` table):

**Staff (CRM):**
- Email: admin@admin.com (or as seeded)
- Password: (check `database/seeders/AdminUserSeeder.php` / staff seeders)

Clients and leads live in the `admins` table and authenticate via the **Client Portal API** (`POST /api/login`), not the CRM `/login` form.

### Virtual Host Setup (XAMPP)

Add to `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/migrationmanager2/public"
    ServerName migrationmanager.local
    <Directory "C:/xampp/htdocs/migrationmanager2/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Add to `C:\Windows\System32\drivers\etc\hosts`:
```
127.0.0.1 migrationmanager.local
```

## Usage Guide

### 1) Manage Leads
- Navigate to `Leads` to view all potential clients
- Create new leads with inquiry details, source, and interested services (including company/trading names for employer leads)
- Track lead status: New, Follow-up, Converted, Lost
- Convert a single lead via `POST /leads/convert-single` or bulk via `POST /leads/bulk-convert` (UI on the leads list)
- View lead history, notes, assignee actions, and lead analytics (`/leads/analytics`)

### 2) Client Management
- **Search & access**: Global header search may show records as **locked** if you are not allocated and have no active grant; use **Request access** to open the cross-access modal (quick or supervisor path per role). See `docs/CROSS_ACCESS_IMPLEMENTATION_PLAN.md` for product rules.
- Go to `Clients` to view all active clients (list is allocation/grant-aware when strict access is on)
- Clients are primarily created by converting leads; profiles support individual or company types
- Prefer **per-section AJAX Save** on personal/company edit (native full-form `POST /clients/edit` does not persist — see Known issues)
- For company clients: use Company Edit for employer sponsorship details (trading names, directors, Trust, nominations)
- Upload client documents and visa history; manage EOI/ROI, ART, and visa-type sheets from the client profile
- Track client relationships (spouse, children, dependents)
- View client summary with matters, invoices, and documents
- Manage client portal credentials / `cp_status` from the CRM

### 3) Matter & Application Tracking
- Visa applications are tracked via **Matters** (`client_matters`) on each client profile
- Create matters linked to clients; select visa type / workflow and upload required documents
- Advance workflow stages with checklist gates; track important dates (submission, interview, decision)
- Add notes, assignees, and Form 956 / cost agreements as needed

### 4) Appointment Scheduling
- Go to **Booking → Appointments** (`/booking/appointments` or `/appointments`) for the calendar
- Create/edit appointments, send reminders, update status/consultant/meeting type
- Handle walk-ins via **Office Visits** (`/office-visits/*`)
- Website bookings can sync via Bansal appointment sync jobs

### 5) Invoice & Accounts
- Invoices, office receipts, and client-fund ledgers are managed from the **client Account** tab (not a standalone `/invoices` app section)
- Generate invoices, void invoices, send to client / Hubdoc, track payments
- Client Portal can list billing and record payments via `/api/billing/*` (Stripe)

### 6) Document Management
- Client personal/visa/nomination documents live on the client detail tabs; global document/signature tools under `/documents/*` and `/signatures/*`
- Organize by categories and checklists; track expiry; request and review uploads
- Electronic signing via public `/sign/{id}/{token}` links

### 7) Quotations
- Navigate to `Quotations` to create service quotes (where enabled in your build)
- Customize line items and pricing; send to potential clients; track status through acceptance

### 8) Reports & Analytics
- Access report screens such as visa expiry (`/reports/visaexpires`), lead analytics, staff login analytics, and client insights
- Export where available (PDF, Excel, CSV depending on the report)

## Business Workflows

- **Lead to Client Process**: Capture lead inquiry → Follow up and qualify → Send quotation → Convert to client → Create client profile
- **Client Onboarding**: Create client account → Collect personal information → Upload documents → Assign case manager → Set up client portal access
- **Application Process**: Receive client documents → Review checklist → Prepare application → Submit to immigration → Track progress → Receive decision
- **Invoice & Payment**: Generate invoice → Send to client → Process payment → Issue receipt → Update payment records
- **Appointment Management**: Client requests appointment → Schedule consultation → Send reminders → Conduct meeting → Update client notes
- **Document Processing**: Client uploads document → Staff reviews → Convert DOCX to PDF → Store securely → Track expiry dates
- **Reporting**: Generate reports → Filter by criteria → Export data → Analyze trends → Make business decisions

## Key Modules

### CRM (Staff UI)
- Dashboard with key metrics
- Complete client and lead management
- Matter/case tracking (visa applications / workflows)
- Invoice, receipt, and client-fund ledger management
- Booking appointments, office visits, documents, signatures
- Cross-access grants, broadcasts, assignee tasks
- Staff login analytics and reporting

### Admin Console
- System administration under the adminconsole route group
- Offices/branches, roles, and selected client/staff ops tools
- Distinct from day-to-day CRM client-detail workflows

### Client Portal (API)
- Sanctum-authenticated JSON API under `/api/*` (no Blade `/portal/*` app)
- Dashboard, matters, personal details, documents/checklists, workflow uploads
- Billing list / invoice payment updates
- Appointments (book, pay, status) and matter messaging
- Staff can also use `/api/admin-login` for portal-adjacent staff API access

### Migration Agent (Staff Role)
Staff can be designated as Migration Agents (`is_migration_agent`) with role-based permissions (e.g. verifying workflow stages in the client portal). They use the main CRM interface. A separate Agent portal is not implemented.

## Project Structure

### Key Components

- **Models**: 
  - `Staff` - CRM users; authenticates via guard `admin` / provider `staff` (`staff` table)
  - `Admin` - Clients and leads (and related portal identities) in the `admins` table; Sanctum API auth for the Client Portal — **not** used for CRM staff login
  - `Lead` - Lead tracking and conversion management
  - `ClientMatter` - Matter/case tracking with visa workflow stages
  - `Matter` - Case/matter categories and types
  - `Document` - Document storage and electronic signatures
  - `BookingAppointment` - Appointment scheduling and calendar
  - `AccountAllInvoiceReceipt` / `AccountClientReceipt` - Invoice and receipt records
  - `Company` - Company profiles for employer sponsorship (with trading names, directors, nominations)
  - `CompanyTradingName` - Multiple trading names per company
  - `CompanyDirector` - Company directors with optional client/lead linking
  - `CompanyNomination` - Employer nomination tracking with nominated person linking
  - `ClientEoiReference` - EOI (Expression of Interest) references
  - `Note` - Client/lead notes and assignee tasks
  - `EmailLog` - Email correspondence tracking
  - `ClientAccessGrant` - Cross-access audit trail (quick, supervisor-approved, exempt rows)
  - `Form956` / related form models - Form 956 agreements
  
- **Controllers**: 
  - `Auth\AdminLoginController` - CRM `/login` (Staff session)
  - `ClientsController` - Client list/detail/merge and related CRM actions
  - `ClientPersonalDetailsController` - Per-section AJAX save for client/company details
  - `API\ClientPortalController` - Portal login/refresh/profile and many CRM-side portal/workflow helpers
  - `ClientAccountsController` - Invoice, receipt, ledger, Hubdoc
  - `BookingAppointmentsController` - Appointment scheduling and calendar
  - `DocumentController` / `ClientDocumentsController` / `PublicDocumentController` - Documents and public signing
  - `OfficeVisitController` - Walk-in client management
  - `DashboardController` - CRM dashboard and metrics
  - `LeadController` / `LeadConversionController` / `LeadAnalyticsController` - Lead management
  - `AssigneeController` - Task/action assignment management
  - `ReportController` - Reports and data export
  - `Form956Controller` - Form 956 CRUD/PDF
  - `ClientEoiRoiController` / `EoiRoiSheetController` - EOI/ROI workflows (staff + public confirm links)
  - `BroadcastController` / `BroadcastNotificationAjaxController` - Broadcast notifications
  - `AccessGrantController` - Cross-access meta, quick/supervisor requests, approver queue, mini-queue API, grants dashboard, CSV export
  
- **Services**:
  - `PythonConverterService` - DOCX to PDF via Python HTTP API
  - `EmailConfigService` - Resolves sender from DB (SendGrid)
  - `StripePaymentService` - Stripe payment gateway integration
  - `SignatureService` / `SignatureTemplateService` - Electronic document signing
  - `Sms/UnifiedSmsManager` - SMS via Twilio or Cellcast providers
  - `BroadcastNotificationService` - In-app broadcast notifications
  - `ClientEditService` - Client/company section save logic
  - `DashboardService` / `FinancialStatsService` - Dashboard and financial metrics
  - `S3AttachmentStorageService` / `S3EmailStorageService` - S3 file storage
  - `CrmAccess\CrmAccessService` - Grant lifecycle (request, approve, reject, revoke, expiry), approver notifications
  - `BansalAppointmentSync\AppointmentSyncService` - Sync appointments from the Bansal website
  
- **Support / visibility** (`app/Support/`):
  - `StaffClientVisibility` - `canAccessClientOrLead`, list/query restrictions (clients, leads, documents, bookings), search enrichment for locked rows, exempt daily logging
  
- **Python Services** (`python_services/`):
  - `docx_converter_service.py` - DOCX to PDF conversion via HTTP API
  - `PythonConverterService` (PHP) calls the Python API at `PYTHON_CONVERTER_URL` (default: `http://localhost:5000`)
  
- **Database Migrations**: 
  - Dedicated `staff` table (staff extracted from `admins`)
  - Clients/leads remain in `admins`; matters, finance, documents, booking, access grants
  
- **Policies**: 
  - Role-based access for Staff (CRM) and Admin (client/lead records)
  - Client data privacy via `StaffClientVisibility` and related policies

### Storage Structure

The application organizes files in the following structure:

```
storage/app/public/
├── agreements/           # Client service agreements
├── checklists/          # Document checklists
├── attachments/         # Email and document attachments
└── documents/           # Client uploaded documents

public/
├── assets/              # UI assets and images
├── css/                 # Custom stylesheets (includes flatpickr.min.css, listing-datepicker.css for listing filters)
├── js/                  # JavaScript (flatpickr.min.js, crm-flatpickr.js, scripts.js, dashboard-optimized.js for CRM dashboard)
└── img/                 # Public images
```

### Date Picker (Flatpickr)

All interactive date and datetime inputs use **Flatpickr**. The stack does **not** ship or load Bootstrap Datepicker, jQuery UI datetimepicker, or the legacy **daterangepicker** plugin—those were removed in favour of Flatpickr.

- **`CRM_Flatpickr`** (`public/js/crm-flatpickr.js`): shared helpers after `@include('components.flatpickr-scripts')` on CRM layouts:
  - **initStandard** — single date (`d/m/Y` where used)
  - **initPastDates** — past-only (max: today) for address, travel, employment, and similar historical dates
  - **initDOB** — date of birth with optional age field
  - **initDateTime** — date + time
  - **initRange** — range selection for filters/reports
- **Declarative**: use `data-flatpickr="standard"`, `"past-only"`, `"dob"`, `"datetime"`, or `"range"` on inputs for auto-init (see `crm-flatpickr.js`).
- **Legacy CSS hooks**: classes such as `.datepicker`, `.datetimepicker`, `.daterange`, and DOB variants are **markup names only**; `public/js/scripts.js` (and some feature modules) attach Flatpickr to them. They are not separate jQuery plugins.
- **Listing pages**: `css/listing-datepicker.css` styles filter inputs with class `.datepicker`; the calendar popup is Flatpickr’s `.flatpickr-calendar` (typically on `document.body`).
- **Dashboard**: the CRM dashboard loads **`js/dashboard-optimized.js`**. The file **`js/dashboard.js`** is an unmaintained AdminLTE demo; it is not referenced by Laravel layouts—only include it deliberately (with Flatpickr loaded first) if you reuse that demo.

After `npm install`, run `npm run copy:flatpickr` so Flatpickr assets exist under `public/js/` and `public/css/`.

### Background Jobs & Scheduling

- Use Laravel's scheduler for automated tasks:
  - **`access:expire-grants`** (hourly) — marks time-expired active grants and very old pending supervisor requests as expired (`CrmAccessService::expireStaleGrants`)
  - Appointment reminders
  - Visa expiry notifications
  - Follow-up reminders
  - Invoice payment reminders
- Queue workers handle:
  - Email sending
  - Document processing
  - PDF generation
  - Report generation

### Document Conversion

The system includes Python-based document conversion via `python_services/`:

- **Purpose**: Convert DOCX documents to PDF format
- **Usage**: `PythonConverterService` (PHP) calls the Python API; set `PYTHON_CONVERTER_URL` in `.env` (default: `http://localhost:5000`)
- **Setup**: Run `python_services/start_services.py` or equivalent to start the conversion API
- **Integration**: Documents uploaded as DOCX are converted via the API; conversion can run in background queue

## Main Routes

### Public Routes
- `GET /` - Welcome/Landing page
- `GET /login` - CRM staff login
- `POST /login` - Authenticate staff (`AdminLoginController`, guard `admin` → `Staff`)
- `POST /logout` - Staff logout
- Public signing / document helpers under `/sign/*`, `/documents/{id}/page/*` (see `routes/documents.php`)
- Public EOI confirm/amend sheet links (tokenised) via `EoiRoiSheetController`

### CRM Routes (Protected — Staff, `auth:admin`)
- `GET /dashboard` - CRM dashboard with key metrics
- **Clients** (`routes/clients.php`):
  - `GET /clients` - List clients
  - `GET /clients/{id}` - Client detail (matters, docs, accounts, sheets, portal)
  - `GET /clients/{id}/edit` - Edit client (section AJAX saves)
  - `POST /clients/edit` (`clients.update`) - Legacy form post; does **not** reliably persist (use section Save)
  - Clients are normally created by converting leads
  
- **Matters** (visa/case tracking on client detail):
  - Managed within the client detail / portal workflow APIs; no legacy standalone `applications` routes
  
- **Accounts** (on client):
  - Invoice list, create/void, receipts, client-fund ledger — e.g. `/clients/invoicelist`, account tab POSTs
  
- **Appointments & office visits** (`routes/client_portal.php`):
  - `/appointments`, `/booking/appointments/*` — `BookingAppointmentsController`
  - `/office-visits/*` — waiting / attending / completed queues
  
- **Leads** (`routes/web.php` prefix `leads`):
  - `GET /leads` - List leads
  - `GET /leads/create` | `POST /leads/store` - Create
  - `GET /leads/{id}/edit` | `PUT /leads/{id}` - Update
  - `POST /leads/convert-single` | `POST /leads/bulk-convert` - Convert to client
  - `GET /leads/analytics` - Lead analytics

- **Documents & signatures** (`routes/documents.php`):
  - `/documents/*`, `/signatures/*` (staff); public sign/download helpers as registered

- **Form 956**:
  - `POST /forms`, `GET /forms/{form}`, edit/update/destroy/preview/pdf

- **Cross-access (staff)** — prefix `/crm/access/` (see `routes/clients.php`):
  - `GET /crm/access/meta` — branches, teams, quick reasons, UI flags for the request modal
  - `POST /crm/access/quick` — 15-minute quick grant (throttled)
  - `POST /crm/access/supervisor` — supervisor approval request (throttled)
  - `GET /crm/access/queue` — HTML pending queue (approvers / Super Admin)
  - `GET /crm/access/queue/data` | `GET /crm/access/queue/mini` — JSON pending items (mini for header dropdown)
  - `POST /crm/access/{grant}/approve` | `reject` — approve or reject (approvers)
  - `GET /crm/access/my-grants` — staff’s own grants (HTML + JSON data route)
  - `GET /crm/access/dashboard` — grants dashboard (filters, pending section, table, CSV export link)
  - `GET /crm/access/dashboard/data` | `dashboard/export` — JSON and CSV for audits
  
- **Reports** (examples):
  - `GET /reports/visaexpires` - Visa expiry report
  - Lead analytics under `/leads/analytics`

### Client Portal API (`routes/api.php`, Sanctum)
- Auth: `POST /api/login`, `/api/refresh`, `/api/forgot-password`, `/api/reset-password`; staff API login `POST /api/admin-login`
- `GET /api/dashboard`, `/api/matters`, `/api/profile`, personal-detail update endpoints
- Documents: `/api/documents/*`, workflow checklist uploads
- Billing: `GET /api/billing/list`, `POST /api/billing/invoice-update`
- Appointments: `/api/appointments/*` (including public without-login book/pay helpers)
- Messages: `/api/messages/*`
- Broadcasting auth: `POST /api/broadcasting/auth`

## Configuration

### Environment Variables

Key environment variables in `.env`:

```env
APP_NAME="Migration Manager"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=migration_manager
DB_USERNAME=postgres
DB_PASSWORD=

# Mail Configuration (SendGrid)
MAIL_MAILER=sendgrid
MAIL_FROM_ADDRESS=your_sender@yourdomain.com
MAIL_FROM_NAME="Your Company Name"
SENDGRID_API_KEY=SG.your_api_key_here
SENDGRID_FROM_EMAIL=your_sender@yourdomain.com
SENDGRID_BASE_URL=https://api.sendgrid.com

# Payment Gateways
STRIPE_KEY=your_stripe_key
STRIPE_SECRET=your_stripe_secret
PAYU_MERCHANT_KEY=your_payu_key
PAYU_SALT=your_payu_salt

# File Storage
FILESYSTEM_DISK=local

# Queue Configuration (database or redis)
QUEUE_CONNECTION=database
QUEUE_RETRY_AFTER=90

# Session & Cache
SESSION_DRIVER=file
CACHE_DRIVER=file

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

#### CRM cross-access (`config/crm_access.php`)

Row-level visibility and temporary access grants are controlled in `config/crm_access.php` (parsed lists tolerate empty `.env` values with safe defaults). Add to `.env` as needed:

```env
# Comma-separated role IDs that bypass allocation (default: 1,17 — Super Admin, Admin)
CRM_ACCESS_EXEMPT_ROLE_IDS=1,17

# Optional: staff.id exempt list. Omit or leave blank so exempt mirrors approvers (see config/crm_access.php).
# CRM_ACCESS_EXEMPT_STAFF_IDS=36834,36524,36692,36483,36484,36718,36523,36836,36830

# Comma-separated staff.id values who may approve supervisor requests (plus all active role-1 staff)
CRM_ACCESS_APPROVER_STAFF_IDS=36834,36524,36692,36483,36484,36718,36523,36836,36830

# Roles that may only use quick access, not supervisor path (default: 14 — Calling Team)
CRM_ACCESS_QUICK_ONLY_ROLE_IDS=14

# When true, non-exempt staff only see allocated clients/leads (+ active grants)
CRM_ACCESS_STRICT_ALLOCATION=false

# Grant durations and caps
CRM_ACCESS_QUICK_GRANT_MINUTES=15
CRM_ACCESS_SUPERVISOR_GRANT_HOURS=24
CRM_ACCESS_MAX_PENDING_SUPERVISOR_REQUESTS=5
CRM_ACCESS_PENDING_TTL_DAYS=14
```

Full behaviour, HTTP surface, and QA checklist: **`docs/CROSS_ACCESS_IMPLEMENTATION_PLAN.md`**.

### Database

The application uses **PostgreSQL** as the primary database (default in `config/database.php`). MySQL is supported for legacy migration from existing MySQL installations. For development, you can use SQLite by changing `DB_CONNECTION` to `sqlite` in `.env`.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests to ensure everything works
5. Submit a pull request

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Recent Changes

- **PostgreSQL**: Primary database is now PostgreSQL (default in config); MySQL supported for legacy migration.
- **Laravel 13**: Upgraded from Laravel 12; requires PHP 8.3+.
- **Matter-based tracking**: Legacy `applications` table removed; visa tracking is via `client_matters` (Matter model).
- **Flatpickr**: All date/datetime pickers use Flatpickr; Bootstrap Datepicker and daterangepicker-style plugins are gone. Display formats vary by field (e.g. `Y-m-d` vs `d/m/Y` per screen). README documents class hooks, layouts, and troubleshooting.
- **Company Employer Sponsorship**: Full implementation including Trust entities, trading names, directors (with client/lead linking), nominations, sponsorship tracking, and per-section AJAX save.
- **EOI workflows**: Client confirmation sheets (`eoi-client-confirmation`, `eoi-confirmation-success`, `eoi-roi`) for Expression of Interest visa flows.
- **Assignee action view**: Dedicated action page for assigned tasks.
- **CRM layouts**: Updated `crm_client_detail` and `crm_client_detail_dashboard` with Flatpickr components.
- **Vite build**: Frontend built with Vite; includes FullCalendar, Flatpickr, Signature Pad, Alpine.js, Tailwind.
- **Cross-access & allocated visibility**: `StaffClientVisibility`, `CrmAccessService`, `client_access_grants`, header search locked rows + modal, approver bell mini-queue, grants dashboard and CSV, booking/email/document gates when strict allocation is enabled; scheduled `access:expire-grants`.

For detailed Company Employer Sponsorship implementation notes, see `docs/COMPANY_EMPLOYER_SPONSORSHIP_IMPLEMENTATION_PLAN.md`.

For cross-access product rules, routes, and rollout status, see **`docs/CROSS_ACCESS_IMPLEMENTATION_PLAN.md`**.

## Important Notes

- The application is optimized to work with XAMPP (Apache) on Windows; PostgreSQL must be installed separately
- Run `npm run copy:flatpickr` after `npm install` so date pickers work correctly
- Python API service (`python_services/`) handles DOCX to PDF conversion; optional if not using DOCX uploads
- PostgreSQL is the primary database; ensure `pdo_pgsql` PHP extension is enabled
- The application uses Laravel's built-in authentication with multi-role support
- Document storage is handled locally by default, with optional S3 integration
- Comprehensive logging is available in `storage/logs/laravel.log`
- Email integration supports both SMTP and IMAP protocols
- Payment gateways (Stripe, PayU) need to be configured for online payments
- SMS providers (Twilio, Cellcast) need to be configured for SMS notifications
- Client portal provides secure access for clients to track their applications
- **CRM cross-access**: ensure `php artisan schedule:run` (or cron) runs in production so `access:expire-grants` executes; set `CRM_ACCESS_STRICT_ALLOCATION=true` only after UAT (see implementation plan)

## Troubleshooting

### Database Issues
- **Connection refused**: Ensure PostgreSQL is running (XAMPP does not include PostgreSQL; install separately)
- **pdo_pgsql not loaded**: Enable the `pdo_pgsql` extension in `php.ini`; run `php -m` and confirm `pdo_pgsql` appears in the list
- **Access denied**: Verify database credentials in `.env` file
- **Table not found**: Run `php artisan migrate --seed` to create tables

### PDF / DOCX Conversion Issues
- **DOCX conversion fails**: Ensure the Python conversion service is running (`python_services/`); set `PYTHON_CONVERTER_URL` in `.env` if not using default `http://localhost:5000`
- **Permission denied**: Check storage folder permissions (775 or 777)

### Email Issues
- **Emails not sending**: Verify MAIL_* configuration in `.env`
- **SMTP authentication failed**: Use app-specific passwords for Gmail
- **Emails going to spam**: Configure SPF and DKIM records for your domain

### Date Picker (Flatpickr) Issues
- **Date pickers not appearing**: Run `npm run copy:flatpickr` to copy Flatpickr assets to `public/js/` and `public/css/`
- **`flatpickr is not defined`**: Include `@include('components.flatpickr-assets')` / `@include('components.flatpickr-scripts')` (or equivalent) in the Blade layout before scripts that call `flatpickr`—see `resources/views/layouts/crm_client_detail.blade.php` and `crm_client_detail_dashboard.blade.php`
- **Double calendars / odd behaviour**: Avoid initializing the same input twice; client-detail layouts load `crm-flatpickr.js` before `scripts.js`, which skips duplicate `.datepicker` init on those pages

### Performance Issues
- **Slow page loads**: Run `php artisan optimize` and `npm run build`
- **Queue not processing**: Ensure `php artisan queue:work` is running
- **High memory usage**: Increase PHP memory_limit in php.ini

## Security Best Practices

- **Never commit `.env` file** - Contains sensitive credentials
- **Use strong passwords** - Enforce password policies for users
- **Enable HTTPS** - Use SSL certificates in production
- **Regular backups** - Automated daily database backups recommended
- **Update dependencies** - Run `composer update` and `npm update` regularly
- **Role-based access** - Limit admin access to trusted staff only
- **Two-factor authentication** - Consider implementing 2FA for admin accounts
- **Data encryption** - Sensitive client data should be encrypted at rest

## Backup & Data Management

### What to Backup
- **Database**: PostgreSQL database dumps (daily recommended)
- **Storage folder**: `storage/app/public/` containing:
  - Client documents
  - Agreements
  - Attachments
  - Generated PDFs
- **Environment file**: `.env` (store securely, not in repository)
- **Uploaded files**: All content in `public/` except framework files

### Backup Commands
```powershell
# PostgreSQL database backup (PowerShell)
$date = Get-Date -Format "yyyyMMdd"
pg_dump -U postgres migration_manager | Out-File "backup_$date.sql"

# Storage backup (use robocopy or 7-Zip on Windows)
robocopy storage\app\public "backup_storage_$date" /E
```

### Data Retention
- Keep client records for minimum 7 years (immigration regulations)
- Archive old matters after case closure
- Regularly clean up old logs and temporary files

## FAQ

**Q: Nothing appears after login**
- Ensure migrations have been run: `php artisan migrate --seed`
- Check that PostgreSQL is running (and Apache if using XAMPP)
- Verify `.env` database configuration and `pdo_pgsql` extension

**Q: PDF/DOCX conversion not working**
- Ensure the Python conversion service (`python_services/`) is running and reachable at `PYTHON_CONVERTER_URL`
- Check `storage/logs/laravel.log` for conversion errors

**Q: Client portal not accessible**
- Ensure client has portal access enabled in their profile
- Client must use the email address registered in the system
- Check that routes are properly configured in `routes/web.php`

**Q: Payment gateway errors**
- Verify Stripe/PayU credentials in `.env`
- Ensure SSL is enabled for production payments
- Test with sandbox/test keys before going live

**Q: Can I customize invoice templates?**
- Edit the invoice email template at `resources/views/emails/geninvoice.blade.php`
- Receipt email templates: `resources/views/emails/reciept.blade.php`, `genofficereceipt.blade.php`, `genclientfundreceipt.blade.php`

**Q: How to add new visa types?**
- Go to Admin → Settings → Visa Types
- Add new visa type with required documents checklist

**Q: How to export client data?**
- Use Reports section to generate and export data
- Available formats: PDF, Excel, CSV

**Q: Which date picker library is used?**
- **Flatpickr** only (`crm-flatpickr.js`, `scripts.js`, and feature-specific modules). There is no Bootstrap Datepicker bundle in `public/js/`. If a third-party snippet references `.datepicker()` as a jQuery plugin, replace it with Flatpickr or `CRM_Flatpickr` patterns from this README.