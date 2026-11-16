# POI Manager — Point of Interest Management System

**POI Manager** is an internal web application for managing and organizing Points of Interest (POIs) on an interactive map. It is built with a React frontend, a PHP backend, and a MySQL/MariaDB database.

The original database records and demo credentials have been removed. The provided SQL file now creates an empty database schema without including any user accounts, passwords, or location records.

##  Requirements

Make sure the following software and dependencies are installed:

- **PHP 8.1 or later**, with the `pdo_mysql` and `mbstring` extensions.
- **MySQL 8** or **MariaDB 10.6 or later**.
- **Node.js 20 or later**.
- **pnpm** package manager.

## Local Development Setup

Follow these steps to configure and run the application locally.

### 1. Database Setup

Import the clean database schema into a new database, then create a dedicated application user with restricted permissions.

Do not import the original SQL file or reuse an existing database without a reviewed migration.

```sh
mysql -u root -p < poi_management_system.sql
mysql -u root -p
```

Inside the MySQL prompt, create a dedicated database user. Replace the password placeholder with a long, randomly generated password that is not stored in the project.

```sql
CREATE USER 'poi_app'@'127.0.0.1' IDENTIFIED BY 'YOUR_LONG_RANDOM_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE ON poi_management_system.* TO 'poi_app'@'127.0.0.1';
```

### 2. Environment Configuration

Copy `.env.example` to `.env` and configure the database credentials and the `APP_IP_HASH_KEY` variable using long, randomly generated values.

**Important:** Never commit or upload the `.env` file to GitHub.

You can generate a secure random value using PHP:

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

### 3. Create the Initial Administrator Account

Create the first administrator account directly from the terminal. No default administrator credentials are provided.

```sh
ADMIN_USERNAME=admin ADMIN_PASSWORD='YOUR_UNIQUE_LONG_PASSWORD' pnpm run create-admin
```

**PowerShell alternative:**

```powershell
$env:ADMIN_USERNAME = 'admin'
$env:ADMIN_PASSWORD = 'YOUR_UNIQUE_LONG_PASSWORD'
pnpm run create-admin
Remove-Item Env:ADMIN_PASSWORD
```

Use a password between **12 and 72 bytes**. Never store administrator credentials in the repository or in shared command history.

### 4. Install Dependencies

Install the project dependencies using the frozen lockfile:

```sh
pnpm install --frozen-lockfile
```

### 5. Start the Development Servers

Run the PHP backend and React development server in two separate terminal windows from the project directory.

**Terminal 1 — PHP Backend:**

```sh
php -S 127.0.0.1:8000 -t public public/router.php
```

**Terminal 2 — React Development Server:**

```sh
pnpm start
```

Once both servers are running, open:

```text
http://localhost:3000
```

The React development server communicates with the PHP API through its local development proxy.

## 🚀 Production Build & Local Deployment

To build the production version of the React frontend, run:

```sh
pnpm build
```

Then start the PHP server:

```sh
php -S 127.0.0.1:8000 -t public public/router.php
```

Open the application at:

```text
http://127.0.0.1:8000
```

The production build generates the React frontend files inside `public/site/`.

## Testing

Run the frontend and backend test suites using the following commands:

**Frontend Tests:**

```sh
pnpm test
```

**PHP Backend Tests:**

```sh
pnpm run test:php
```

PHP tests require a running database and an existing test administrator account.

Set `TEST_ADMIN_USERNAME` and `TEST_ADMIN_PASSWORD` in your environment to the credentials of a local test account before running the tests.

**Never use production credentials or production accounts for testing.**

## Security Considerations

The application includes several security measures designed to protect user accounts, sessions, and location data.

- **Password Protection:** User passwords are stored as secure hashes using PHP's `password_hash`, rather than as plain text.
- **Session Security:** Sessions use `HttpOnly` and `SameSite=Strict` cookies. Session identifiers are regenerated upon successful login.
- **CSRF Protection:** All write operations require a valid authenticated session and a CSRF token.
- **Server-Side Authorization:** User roles and resource ownership are validated on the server rather than being trusted from frontend data.
- **HTTPS Requirement:** The application must be deployed over HTTPS for public access.
- **Secure Session Configuration:** Set `SESSION_SECURE=1` and configure `APP_ORIGINS` with the actual website origin before making the application publicly accessible.
- **Database Security:** Use a dedicated database account with limited permissions.
- **Maintenance & Backups:** Maintain secure backups and apply regular security updates.

### Sensitive Data Removal

The original database file contained exposed passwords and location records with geographic coordinates.

The updated project has been cleaned to ensure that it does not include:

- User records or account credentials.
- Stored location records or geographic coordinates.
- The original `.env` configuration file.

**Important:** Do not import or reuse the original SQL database. Always start with the cleaned database schema included in the updated project.

---

**POI Manager** — A React and PHP-based application for managing Points of Interest through an interactive mapping interface, backed by a structured MySQL/MariaDB database.
