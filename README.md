# Lambda Platform – Laravel

[![CI](https://github.com/lambda-platform/lambda-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/lambda-platform/lambda-laravel/actions/workflows/ci.yml)
[![Latest tag](https://img.shields.io/github/v/tag/lambda-platform/lambda-laravel?sort=semver)](https://github.com/lambda-platform/lambda-laravel/tags)

Laravel package for **Lambda Platform** – a low-code toolkit for building admin panels.
Forms, grids, data sources, roles and menus are designed visually (Puzzle builder), stored as JSON
schemas in the database, and served through a generic CRUD API.

## Modules

| Module | Namespace | Purpose |
|---|---|---|
| Agent | `Lambda\Agent` | Login, JWT auth, password reset, user management |
| Puzzle | `Lambda\Puzzle` | Visual builder: schemas (`vb_schemas`), roles, menus, DB schema introspection, page builder, DB backup |
| Dataform | `Lambda\Dataform` | Form engine: validation, store/update with sub-forms, options, file upload, form e-mails |
| Datagrid | `Lambda\Datagrid` | Grid engine: filtering, sorting, paging, aggregation, Excel export, print, row update/delete |
| Krud | `Lambda\Krud` | CRUD HTTP endpoints on top of Dataform / Datagrid |
| DataSource | `Lambda\DataSource` | SQL views created from the builder |
| Notify | `Lambda\Notify` | In-app notifications and push (FCM) |
| Translation | `Lambda\Translation` | Translation management and locale file generation |
| Template | `Lambda\Template` | Base "paper" layout |
| Process | `Lambda\Process` | Process list API |
| `src/io` | Node.js | Optional socket.io notification server |

## Requirements

- PHP 8.1+
- Laravel 9+
- MySQL / MariaDB, PostgreSQL or SQL Server (MongoDB partially supported)
- [`tymon/jwt-auth`](https://github.com/tymondesigns/jwt-auth) (installed as a dependency)

## Installation

```bash
composer require lambda-platform/lambda-laravel
```

The service provider is registered automatically (package auto-discovery).

### 1. Configuration

Publish the config file and fill it in (`config/lambda.php`):

```bash
php artisan vendor:publish --provider="Lambda\LambdaServiceProvider"
```

Main keys:

| Key | Description |
|---|---|
| `app_url` | Base URL used for redirects after login |
| `lambda_access` | Role id allowed to use the builder and user management (e.g. `1` = admin). **Set this** – without it any logged-in user can access them |
| `role-redirects` | `[['role_id' => 1, 'url' => '/agent#/admin/roles'], ...]` – landing page per role |
| `user_data_fields` | User columns exposed to the builder for user-based conditions |
| `user_login_check_active` | `1` = only users with `is_active = 1` can log in |
| `jwt_claims` | Extra user columns added to the JWT payload |
| `static_words` | Per-language texts for password reset e-mails, e.g. `['mn' => [...], 'en' => [...]]` |
| `password_reset_time_out` | Reset code lifetime in minutes (default `30`) |
| `title`, `favicon`, `logo`, `copyright`, `domain` | Branding for login page, e-mails and layout |
| `img_width`, `img_thumb_width`, `img_quality` | Image resize settings for uploads |
| `backup.*` | DB backup: `enable`, `exe_path` (mysqldump), `remote`, `remote_host`, `remote_port`, `remote_username`, `remote_password`, `remote_path` |

Push notifications read the FCM server key from `config('services.fcm.key')`.
Keep secrets in `.env` and reference them from config files only – never call `env()` in application code.

### 2. JWT auth

Configure `tymon/jwt-auth` and an `api` guard using the `jwt` driver (`config/auth.php`), then register
the package middleware in your HTTP kernel:

```php
// app/Http/Kernel.php
protected $routeMiddleware = [
    // ...
    'jwt' => \Lambda\Agent\Middleware\JWT::class,
];
```

`jwt` requires a logged-in user; `jwt:1` additionally requires role `1`.

### 3. Database

The package expects these tables to exist in the application database:
`users`, `roles`, `permissions`, `password_resets`, `vb_schemas`, `vb_schemas_admin`, `krud`,
`notifications`, `notification_status`, `tr_locales`, `tr_components`, `tr_translation`, `api_config`.

## Routes

| Prefix | Middleware | Description |
|---|---|---|
| `auth/*` | – | Login page, login, logout, refresh, password reset |
| `agent/*` | `jwt:{lambda_access}` | User management |
| `lambda/puzzle/*` | `jwt` / `jwt:{lambda_access}` | Builder, schemas, roles, DB schema |
| `lambda/krud/*` | `jwt` | Form/grid CRUD, Excel export/import, print |
| `lambda/notify/*` | `jwt` | Notifications of the logged-in user |
| `lambda/locale/*` | `jwt` | Translations |
| `lambda/filemanager/*` | `jwt` | Editor file manager |

Public (no login) endpoints: `lambda/krud/upload`, `lambda/krud/upload-tinymce`, `lambda/krud/unique`,
`lambda/filemanager/file/{path}`, `api/lm/puzzle/schema/*` and `api/lm/puzzle/get_options`.
Put them behind `jwt` if your application has no public forms.

## Optional: socket.io server

```bash
cd src/io
yarn install
yarn serve   # pm2 start process.json
```

## Development

### CI

Every push and pull request runs [GitHub Actions](.github/workflows/ci.yml):

- **Lint & best practice** on PHP 8.1 / 8.2 / 8.3 – syntax, deprecations, leftover `dd()`/`dump()`,
  `env()` outside config, request input concatenated into raw SQL, hard-coded API keys
  ([script](.github/scripts/best-practice.sh)). Run it locally with:
  ```bash
  bash .github/scripts/best-practice.sh
  ```
- **Secret scan** (TruffleHog) on the pushed commits
- **Dependency review** on pull requests (blocks new high/critical vulnerabilities)
- **Semgrep** SAST – results in the *Security → Code scanning* tab
- **Composer audit** – currently report-only until vulnerable locked dependencies are upgraded

Dependabot opens weekly update PRs against `dev`.

### Branches and releases

- Work on feature branches, open PRs into `dev`, then `dev` → `master`.
- When a PR is **merged into `master`**, a semver tag and GitHub release are created automatically
  ([workflow](.github/workflows/release-tag.yml)); Packagist picks up the new tag.
- The version bump is chosen with a PR label:

  | Label | Example |
  |---|---|
  | *(none)* | `v1.2.9` → `v1.2.10` |
  | `release:minor` | `v1.2.9` → `v1.3.0` |
  | `release:major` | `v1.2.9` → `v2.0.0` |
  | `release:skip` | no tag |

## License

MIT
