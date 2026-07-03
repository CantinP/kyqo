# Kyqo Architecture

## Overview

Kyqo is a full-stack framework designed to be usable by PHP and JavaScript/TypeScript developers alike.

## Layers

```
┌─────────────────────────────────────────────────┐
│                  Application Layer               │
│          (app/ — your code lives here)           │
├─────────────────────────────────────────────────┤
│                   Package Layer                  │
│  core | http | database | auth | ui | view ...   │
├─────────────────────────────────────────────────┤
│                Infrastructure Layer              │
│       cache | queue | mail | storage | ...       │
├─────────────────────────────────────────────────┤
│                  Runtime Layer                   │
│         PHP 8.3+ / Node.js 20+ / Vite            │
└─────────────────────────────────────────────────┘
```

## Package Descriptions

### `kyqo/core`
The IoC Container, Application instance, Config repository, Event Dispatcher, Exception Handler, Logger, and Support utilities (Collection, helpers).

### `kyqo/http`
HTTP kernel, Router (GET/POST/PUT/PATCH/DELETE/resource/group), Route model, Request, Response, Middleware pipeline, Controller base, Validation system.

### `kyqo/database`
Database connections (MySQL, PostgreSQL, SQLite), Query Builder, ORM base Model, Migration runner, Seeder system.

### `kyqo/auth`
AuthManager, Guards (session, token), Providers, Password hashing, Remember tokens, Policy system.

### `kyqo/view`
Template Engine, Layout/Section/Yield system, Partials, Component rendering.

### `kyqo/ui`
Component base class, Props, Slots, State, Directives, SSR renderer, TypeScript SDK.

### `kyqo/cache`
CacheManager, File store, Array store, Redis store, remember() helper.

### `kyqo/queue`
QueueManager, Sync/Database/Redis drivers, Job dispatching, Failed jobs.

### `kyqo/mail`
Mailable class, SMTP/Log/Array transports, Markdown templates.

### `kyqo/notification`
Notifiable trait, Mail/Database/Slack notification channels.

### `kyqo/storage`
Filesystem abstraction, Local/S3/FTP disks, File uploads.

### `kyqo/scheduler`
Task scheduling, Cron-like definitions, job management.

### `kyqo/realtime`
WebSockets, SSE, Broadcasting, Presence channels.

### `kyqo/api`
JSON:API builder, REST helpers, Versioning, Rate limiting.

### `kyqo/cli`
Scaffolding CLI, Code generators, Command registration.

### `kyqo/testing`
Test case base, HTTP test helpers, Database assertions, Mocking.
