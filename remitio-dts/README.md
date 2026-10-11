# Remitio DTS — Document Tracking System

Web-based document tracking system for **Remitio & Remitio Law Offices**, Davao City.
Records clients, cases, and documents; tracks every hand-off of a document; keeps file versions;
and provides search, reports, an activity log, and backup & restore.

**Stack:** Laravel 12 · PHP 8.2+ · MySQL (XAMPP) · Blade · Tailwind CSS · Alpine.js
**Team:** Kenneth Manos (Programmer) · Hero Camillus Obillo (Project Manager / Systems Analyst / QA)

---

## 1. Requirements

| | Minimum |
|---|---|
| OS | Windows 10/11 (64-bit) |
| CPU / RAM | Intel Core i3 2.0 GHz / 4 GB (8 GB recommended) |
| Disk | 20 GB free (SSD recommended) |
| Software | XAMPP (PHP 8.2+, MySQL), Composer, Node.js LTS, Git |

In `C:\xampp\php\php.ini`, make sure these lines have **no** `;` in front:

```ini
extension=fileinfo
extension=zip
extension=intl
upload_max_filesize = 12M
post_max_size = 64M
```

Check with: `php -m | findstr /i "fileinfo zip intl"`

## 2. Installation (developer or first-time setup)

```bash
git clone https://github.com/<account>/remitio-dts.git
cd remitio-dts
composer install
npm install
npm run build
copy .env.example .env
php artisan key:generate
```

Create a MySQL database named `remitio_dts` (phpMyAdmin → New → `utf8mb4_unicode_ci`), then set in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=remitio_dts
DB_USERNAME=root
DB_PASSWORD=
```

### Option A — Demo data (development, presentations)

```bash
php artisan migrate --seed
```

Demo accounts (password: `password`): `admin@remitio.test`, `attorney@remitio.test` (Administrator) ·
`secretary@remitio.test`, `clerk@remitio.test` (Staff). **Never use these at the firm.**

### Option B — Real use at the firm (no demo data)

```bash
php artisan migrate --force
php artisan db:seed --class=DocumentTypeSeeder
php artisan dts:create-admin
```

Then sign in as that administrator and add the staff accounts under **Administration → Users**.

## 3. Running the system

Double-click **`start-dts.bat`** (keep its window open). Or run:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

- This computer: <http://localhost:8000>
- Other office computers: `http://<this-PC's-IP>:8000` (find the IP with `ipconfig`).
  If they can't connect, allow **PHP** through Windows Defender Firewall (Private networks).
- MySQL must be running. In the XAMPP Control Panel, tick **Svc** next to MySQL to start it with Windows.

### Production settings (at the firm)

In `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://<this-PC's-IP>:8000
```

Then: `php artisan optimize`. (After any code update: `php artisan optimize:clear` then `php artisan optimize`.)

## 4. Backup & restore

- **Manual:** Administration → **Backup & Restore** → *Back Up Now* → *Download*. Copy the file to a USB drive or cloud storage.
- **Automatic:** Windows **Task Scheduler** → *Create Basic Task* → Daily → *Start a program* → `backup-dts.bat`.
  Keeps the 10 most recent automatic backups. Log: `storage\logs\backup.log`.
- **Restore:** Backup & Restore → *Restore a Backup* → choose or upload the `.zip` → type `RESTORE` → your password.
  A safety copy of the current data is made first; everyone is signed out afterwards.
- A backup contains all records **and** uploaded files. Command line: `php artisan dts:backup`.

## 5. Updating to a new version

```bash
php artisan dts:backup
git pull
composer install      # only if composer.json/lock changed
npm install           # only if package.json changed
php artisan migrate
npm run build
php artisan optimize:clear
```

## 6. Automated tests

Create an empty MySQL database `remitio_dts_test`, then in `phpunit.xml` set:

```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="remitio_dts_test"/>
```

(Or keep the SQLite in-memory defaults if `pdo_sqlite` is enabled in php.ini.) Run:

```bash
php artisan test
```

Covers: access control and roles, document tracking, file versioning, duplicate prevention, backup & restore.

## 7. Troubleshooting

| Symptom | Fix |
|---|---|
| Page has no styling | `npm run build` |
| "Unknown column" / crash after an update | `php artisan migrate` (check `php artisan migrate:status`) |
| "intl extension is required" | enable `extension=intl` in php.ini |
| Backups unavailable | enable `extension=zip` in php.ini |
| Uploads fail / "too large" | raise `upload_max_filesize` and `post_max_size` in php.ini |
| Other PCs can't connect | start with `start-dts.bat` (uses `--host=0.0.0.0`) and allow PHP in the firewall |
