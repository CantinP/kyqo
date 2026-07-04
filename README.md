# Kyqo Framework

> A modern, expressive PHP framework built for developers who want full control without magic.

**[English Documentation](docs/en/README.md)** · **[Documentation Française](docs/fr/README.md)**

---

## Quick Start

```bash
git clone https://github.com/CantinP/kyqo.git my-app
cd my-app
composer install
cp .env.example .env
php kyqo migrate
php kyqo serve
```

## What's included

| Package | Description |
|---|---|
| `kyqo/core` | IoC container, Application, Service Providers, helpers |
| `kyqo/http` | Router, Request, Response, Middleware, Kernel, CSRF |
| `kyqo/database` | QueryBuilder, ORM, Migrations, Schema, SQLite support |
| `kyqo/auth` | Guards (session, token), middleware |
| `kyqo/validation` | 25+ rules, dot-notation, custom rules |
| `kyqo/mail` | SMTP / log driver, Mailable, attachments |
| `kyqo/notifications` | Mail, database, Slack channels, Notifiable trait |
| `kyqo/broadcasting` | Pusher (no SDK), log, null drivers |
| `kyqo/websocket` | Native PHP RFC 6455 WebSocket server + client |
| `kyqo/queue` | sync, database, redis drivers, failed jobs |
| `kyqo/cache` | array, file, redis drivers |
| `kyqo/session` | file, database, redis drivers |
| `kyqo/view` | Template engine with layouts, sections, includes |
| `kyqo/storage` | Local disk, streaming, URL generation |
| `kyqo/console` | 23+ Artisan-style commands |
| `kyqo/testing` | TestCase (HTTP), DatabaseTestCase (SQLite :memory:) |

## Requirements

- PHP 8.2+
- PDO extension
- One of: MySQL 8+, PostgreSQL 14+, SQLite 3+

## License

MIT — see [LICENSE](LICENSE).
