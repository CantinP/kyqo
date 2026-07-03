# 🚀 Kyqo Framework

> A powerful, ambitious full-stack framework merging the best of **Laravel** (backend), **React**, **Vue.js** and **Angular** (UI). Built for PHP and JavaScript/TypeScript developers alike.

---

## ✨ Philosophy

Kyqo is designed to be the **only framework you'll ever need**. Whether you're a PHP developer, a JavaScript developer, or both — Kyqo gives you a unified, complete, powerful foundation to build any kind of application.

- 🏗️ **Full backend** — routing, middleware, validation, ORM, auth, queues, cache, events, notifications, mail, scheduler
- 🖥️ **Full frontend** — composable components, state management, SSR, SPA, hydration, directives, slots
- 🌐 **Cross-language** — PHP-first or TypeScript-first, you choose
- 🧩 **Modular** — install only what you need via packages
- ⚡ **Ambitious** — built to scale, built to last

---

## 📦 Packages

| Package | Description |
|---|---|
| `kyqo/core` | Container, config, events, kernel, exceptions, support |
| `kyqo/http` | Router, request, response, middleware, controllers, validation |
| `kyqo/database` | ORM, query builder, migrations, seeders, connections |
| `kyqo/auth` | Sessions, tokens, guards, permissions, policies |
| `kyqo/view` | Template engine, layouts, partials |
| `kyqo/ui` | Component system, state, slots, directives, SSR, hydration |
| `kyqo/cache` | Redis, Memcached, file cache drivers |
| `kyqo/queue` | Jobs, workers, failed jobs, dispatching |
| `kyqo/mail` | Mailable, transport drivers, templates |
| `kyqo/notification` | Channels: mail, SMS, Slack, broadcast |
| `kyqo/storage` | Local, S3, FTP, file abstraction |
| `kyqo/scheduler` | Task scheduling, cron-like, job management |
| `kyqo/testing` | Unit, feature, integration, e2e test harness |
| `kyqo/cli` | Scaffolding, generators, CLI commands (Artisan-like) |
| `kyqo/realtime` | WebSockets, SSE, broadcasting, presence channels |
| `kyqo/api` | JSON:API, REST builders, versioning, rate limiting |

---

## 🗂️ Project Structure

```
kyqo/
├─ apps/                    # Applications using the framework
│  ├─ web/                  # Classic web app
│  ├─ admin/                # Admin panel
│  └─ api/                  # API-only app
├─ packages/                # Framework core packages
│  ├─ core/
│  ├─ http/
│  ├─ database/
│  ├─ auth/
│  ├─ view/
│  ├─ ui/
│  ├─ cache/
│  ├─ queue/
│  ├─ mail/
│  ├─ notification/
│  ├─ storage/
│  ├─ scheduler/
│  ├─ testing/
│  ├─ cli/
│  ├─ realtime/
│  └─ api/
├─ resources/               # Raw frontend resources
│  ├─ views/
│  ├─ components/
│  ├─ styles/
│  └─ scripts/
├─ config/                  # App config files
├─ bootstrap/               # Framework bootstrapping
├─ routes/                  # Route definitions
├─ storage/                 # Compiled, uploads, logs, cache
├─ public/                  # Publicly served directory
├─ tests/                   # Application tests
├─ docs/                    # Framework documentation
└─ .github/                 # CI/CD workflows
```

---

## 🚀 Roadmap

### V1 — Foundation
- [ ] Core (Container, Config, Logger, Events, Kernel)
- [ ] HTTP (Router, Request, Response, Middleware, Controller, Validation)
- [ ] Database (ORM, Migrations, Query Builder)
- [ ] Auth (Sessions, Guards, Policies)
- [ ] View (Template Engine, Layouts, Components)
- [ ] CLI (Generator, Scaffold, Commands)

### V2 — Full Platform
- [ ] Queue, Cache, Mail, Notifications, Storage, Scheduler
- [ ] UI Runtime (Components, SSR, State, Slots, Directives)
- [ ] Realtime (WebSockets, SSE, Broadcasting)
- [ ] API (JSON:API, Rate Limiting, Versioning)
- [ ] TypeScript SDK

### V3 — Ecosystem
- [ ] Admin panel generator
- [ ] Full documentation site
- [ ] Starter kits (web, api, fullstack, admin)
- [ ] Plugin system
- [ ] Cloud deployment integrations

---

## 📄 License

[MIT](LICENSE)

---

> Built with ❤️ by [Cantin Poiseau](https://github.com/CantinP)
