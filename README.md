# MultiFlexi Web Interface

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg)](https://www.php.net/)
[![Bootstrap 5](https://img.shields.io/badge/Bootstrap-5-7952B3.svg)](https://getbootstrap.com/)

The web front-end for **[MultiFlexi](https://multiflexi.eu/)** — a launcher that
runs a catalogue of tools and jobs against accounting/ERP servers such as
**AbraFlexi** and **Stormware Pohoda**. This package provides the browser UI:
dashboards, company and credential management, job scheduling and history,
executor configuration, administrative tooling, and a GDPR-compliant consent
and data-rights workflow.

> This is the Bootstrap 5 generation of the interface. The Debian package
> `multiflexi-web5` is a drop-in replacement for the older `multiflexi-web`
> (it `Conflicts`/`Replaces`/`Provides` it), so only one web interface can be
> installed at a time.

## Features

- **Dashboard & monitoring** — overview of companies, applications, jobs, and
  recent run results with exit-code status indicators.
- **Company & application management** — register companies, assign apps,
  manage per-company configuration and environment overrides.
- **Credentials** — credential types, prototypes, wizards, and per-company
  credential storage (with Vaultwarden integration available in core).
- **Jobs & scheduling** — run templates, ad-hoc launches, schedules, queues,
  reschedule/delay controls, live job output, and job history graphs.
- **Executors** — configure and inspect execution backends.
- **Event rules & sources** — automate actions in response to events.
- **GDPR tooling** — cookie consent, privacy/cookie policy pages, consent
  preferences API, Article 16 data-rectification workflow, data export,
  deletion requests, data-retention administration, and audit logging.
- **Security** — session hardening, CSRF protection, AES-256 encryption of
  sensitive data, API rate limiting, and IP whitelisting for admin access.
- **Internationalisation** — English and Czech translations (gettext `.mo`).
- **Integrations** — OpenTelemetry tracing, Zabbix, WebSocket live updates,
  and a REST API server.

## Architecture

```
src/
├── *.php                  # ~99 page controllers (index, dashboard, job, …)
├── init.php               # bootstrap: Shared::init(), session, CSRF
├── MultiFlexi/            # application classes (PSR-4: MultiFlexi\)
│   ├── Ui/                #   Bootstrap 5 widgets & page rendering
│   ├── Api/               #   REST API server & auth
│   ├── Security/          #   sessions, CSRF, encryption
│   ├── GDPR/  Consent/  DataExport/  DataRetention/  DataErasure/
│   ├── Audit/  Notifications/  Telemetry/  Email/  Command/
│   └── *Lister.php        #   list/query helpers
├── css/  js/  images/  assets/
└── api/                   # REST API entry point
```

The UI builds on the VitexSoftware **EaseFramework** stack
(`ease-twbootstrap5`, `ease-bootstrap5-widgets`, `ease-fluentpdo`) and the
shared **multiflexi-core** package. Page controllers are thin; most logic lives
in `MultiFlexi\*` classes and in core.

## Requirements

- PHP **8.1+** with `ext-yaml`, `ext-simplexml`, `ext-intl`
- A database supported by core (SQLite, MySQL/MariaDB, or PostgreSQL)
- Composer (for development) or the Debian package (for deployment)
- A web server (Apache configuration is shipped with the package)

## Installation

### Debian / Ubuntu (recommended)

```bash
sudo apt install multiflexi-web5
```

The package installs the UI under `/usr/share/multiflexi-web`, ships an Apache
config exposing it at `/multiflexi`, and reads its configuration from
`/etc/multiflexi/multiflexi.env`.

### From source (development)

```bash
git clone https://github.com/Vitexus/multiflexi-web5.git
cd multiflexi-web5
make vendor                  # composer install (+ copies DataTables assets)
cp debian/conf/.env.template .env   # then edit DB_* and ENCRYPTION_MASTER_KEY
php -S localhost:8080 -t src # serve the src/ directory
```

## Configuration

Configuration is read from the `.env` file (in the package this is
`/etc/multiflexi/multiflexi.env`). Key variables:

| Variable                      | Description                              |
|-------------------------------|------------------------------------------|
| `DB_CONNECTION`               | `sqlite`, `mysql`, or `pgsql`            |
| `DB_HOST` / `DB_PORT`         | Database host and port                   |
| `DB_DATABASE`                 | Database name or SQLite path             |
| `DB_USERNAME` / `DB_PASSWORD` | Database credentials                     |
| `ENCRYPTION_MASTER_KEY`       | Master key for AES-256 field encryption  |
| `APP_DEBUG`                   | `true`/`false` — verbose error output    |
| `MULTIFLEXI_TIMEZONE`         | Timezone (autodetected if unset)         |
| `ENABLE_GOOGLE_ANALYTICS`     | Opt-in analytics (self-hosted advised)   |
| `SESSION_TIMEOUT`             | Session lifetime in seconds (def. 14400) |

## Development

Common `make` targets (run `make help` for the full list):

| Target                      | Purpose                                  |
|-----------------------------|------------------------------------------|
| `make vendor`               | Install Composer dependencies            |
| `make phpunit`              | Run the PHPUnit test suite               |
| `make static-code-analysis` | Run PHPStan                              |
| `make cs`                   | Apply coding standards (php-cs-fixer)    |
| `make debs`                 | Build the Debian package                 |
| `make redeb`                | Rebuild and reinstall the `.deb` locally |
| `make docs`                 | Build the Sphinx HTML documentation      |
| `make gdpr-migration`       | Run the GDPR Article 16 DB migration     |

### Tests

```bash
make phpunit
# or directly:
vendor/bin/phpunit -c phpunit.xml
```

### Coding standards & static analysis

```bash
make cs                    # friendsofphp/php-cs-fixer
make static-code-analysis  # phpstan/phpstan
```

## Packaging notes

The Debian package keeps runtime paths under `multiflexi-web` (so `web5` stays a
true drop-in replacement) while staging into `debian/multiflexi-web5/`. At build
time `debian/rules` stamps `APP_NAME`/`APP_VERSION` into the installed
`autoload.php` from the project-root `composer.json` and `debian/changelog`.
Generated build artifacts are ignored via `debian/.gitignore`.

## Documentation

Full documentation lives in the
[multiflexi-doc](https://github.com/VitexSoftware/multiflexi-doc-en) project and
at [multiflexi.eu](https://multiflexi.eu/).

## License

MIT © [Vítězslav Dvořák](https://vitexsoftware.com) / VitexSoftware.
See [`debian/copyright`](debian/copyright) for full details.
