# Kyqo Framework

> A lightweight, modular PHP framework inspired by Laravel — built from scratch.

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

---

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Project structure](#project-structure)
- [Configuration](#configuration)
- [Routing](#routing)
- [Controllers](#controllers)
- [Middleware](#middleware)
- [ORM & Database](#orm--database)
- [Authentication](#authentication)
- [Views & Directives](#views--directives)
- [Validation](#validation)
- [Queue & Jobs](#queue--jobs)
- [Cache](#cache)
- [Mail](#mail)
- [Events](#events)
- [Console (CLI)](#console-cli)
- [Facades](#facades)
- [Helpers](#helpers)
- [Testing](#testing)

---

## Requirements

| Tool | Version |
|------|---------|
| PHP  | ≥ 8.1   |
| Composer | any |
| MySQL / PostgreSQL / SQLite | any |

---

## Installation

```bash
composer create-project cantinp/kyqo my-app
cd my-app
cp .env.example .env
php kyqo key:generate
php kyqo migrate
php kyqo serve
```

---

## Project structure

```
my-app/
├── app/
│   ├── Exceptions/Handler.php
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Kernel.php
│   ├── Models/
│   │   └── User.php
│   └── Providers/
├── bootstrap/app.php
├── config/
│   └── app.php
├── database/
│   └── migrations/
├── packages/          ← framework source
│   ├── auth/
│   ├── cache/
│   ├── console/
│   ├── core/
│   ├── database/
│   ├── http/
│   ├── mail/
│   ├── queue/
│   ├── ui/
│   └── view/
├── public/index.php
├── resources/views/
├── routes/
│   ├── web.php
│   └── api.php
├── storage/
├── .env
└── kyqo              ← CLI entry point
```

---

## Configuration

All configuration lives in `config/`. Values are read from `.env` via the `env()` helper:

```php
$debug = env('APP_DEBUG', false);
$dsn   = env('DB_CONNECTION', 'mysql');
```

---

## Routing

```php
// routes/web.php
$router->get('/',    fn () => view('welcome'));
$router->post('/login', [LoginController::class, 'login']);

// Route groups
$router->group(['prefix' => 'admin', 'middleware' => 'auth'], function () use ($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
});

// Route parameters
$router->get('/users/{id}', [UserController::class, 'show']);

// Named routes
$router->get('/profile', [ProfileController::class, 'index'])->name('profile');
```

---

## Controllers

```php
namespace App\Http\Controllers;

use Kyqo\Http\Request;
use Kyqo\Http\Response;

class UserController
{
    public function show(Request $request): Response
    {
        $user = \App\Models\User::findOrFail($request->route('id'));
        return view('users.show', compact('user'));
    }
}
```

---

## Middleware

```php
// app/Http/Middleware/EnsureTokenIsValid.php
class EnsureTokenIsValid
{
    public function handle(Request $request, \Closure $next): mixed
    {
        if ($request->input('token') !== 'secret') {
            abort(403);
        }
        return $next($request);
    }
}

// Register in app/Http/Kernel.php routeMiddleware:
'token' => EnsureTokenIsValid::class,

// Apply to a route:
$router->get('/secret', fn () => 'OK')->middleware('token');
```

---

## ORM & Database

### Models

```php
namespace App\Models;

use Kyqo\Database\Orm\Model;

class Post extends Model
{
    protected array $fillable = ['title', 'body', 'published'];
    protected array $casts    = ['published' => 'bool'];

    public function user(): \Kyqo\Database\Orm\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): \Kyqo\Database\Orm\Relations\BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
```

### Queries

```php
// Basic CRUD
$post  = Post::create(['title' => 'Hello', 'body' => '...', 'published' => true]);
$posts = Post::where('published', true)->orderBy('created_at', 'DESC')->get();
$post->update(['title' => 'Updated']);
$post->delete();

// Eager loading
$users = User::with(['posts', 'roles'])->get();

// Pagination
$page = Post::paginate(15, $request->integer('page', 1));
```

### Migrations

```php
// database/migrations/YYYY_MM_DD_create_posts_table.php
return new class extends Migration {
    public function up(SchemaBuilder $schema): void {
        $schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
            $table->text('body');
            $table->boolean('published')->default(false);
            $table->timestamps();
        });
    }
    public function down(SchemaBuilder $schema): void {
        $schema->dropIfExists('posts');
    }
};
```

```bash
php kyqo migrate
php kyqo migrate:rollback --step=1
```

### Soft deletes

```php
use Kyqo\Database\Orm\Concerns\SoftDeletes;

class Post extends Model {
    use SoftDeletes;
}

$post->delete();          // sets deleted_at
$post->restore();         // clears deleted_at
$post->forceDelete();     // permanent removal
Post::withTrashed()->get();
Post::onlyTrashed()->get();
```

---

## Authentication

```php
// Attempt login
if (auth()->attempt(['email' => $email, 'password' => $password])) {
    return redirect('/dashboard');
}

// Current user
$user = auth()->user();

// Check
if (auth()->check()) { /* logged in */ }

// Logout
auth()->logout();
```

---

## Views & Directives

Template files use the `.kyqo.php` extension and support Blade-like directives:

```php
{{-- resources/views/layouts/app.kyqo.php --}}
<!DOCTYPE html>
<html><head><title>{{ $title ?? 'Kyqo' }}</title></head>
<body>
@yield('content')
</body></html>

{{-- resources/views/home.kyqo.php --}}
@extends('layouts.app')
@section('content')
    <h1>Welcome, {{ $user->name }}</h1>
    @foreach($posts as $post)
        <p>{{ $post->title }}</p>
    @endforeach
@endsection
```

**Directives:** `@extends` `@section` `@endsection` `@yield` `@include` `@if` `@elseif` `@else` `@endif` `@foreach` `@endforeach` `@for` `@while` `@forelse` `@empty` `@endforelse` `@auth` `@guest` `@csrf` `@method` `@dump` `@dd` `@php` `@endphp`

**Output:** `{{ $escaped }}` — `{!! $raw !!}` — `{{-- comment --}}`

---

## Validation

```php
$data = $request->validate([
    'name'     => 'required|string|max:255',
    'email'    => 'required|email|unique:users,email',
    'password' => 'required|min:8|confirmed',
]);
```

Available rules: `required`, `nullable`, `string`, `integer`, `numeric`, `boolean`, `email`, `url`, `min`, `max`, `between`, `in`, `not_in`, `regex`, `confirmed`, `unique`, `exists`, `date`, `date_format`, `before`, `after`, `array`, `file`, `image`, `mimes`, `max_file_size`.

---

## Queue & Jobs

```php
// Define a job
class SendWelcomeEmail extends \Kyqo\Queue\Job
{
    public function __construct(protected User $user) {}

    public function handle(): void
    {
        Mail::to($this->user->email)->send(new WelcomeMail($this->user));
    }
}

// Dispatch
SendWelcomeEmail::dispatch($user);
SendWelcomeEmail::dispatchAfter(60, $user); // delay 60s
SendWelcomeEmail::dispatchSync($user);      // sync (no queue)

// Worker
php kyqo queue:work --queue=default
```

---

## Cache

```php
cache()->put('key', $value, 3600); // 1 hour
$value = cache()->get('key', 'default');
cache()->forget('key');
cache()->remember('key', 3600, fn () => expensiveQuery());
```

---

## Mail

```php
Mail::to('user@example.com')->send(new WelcomeMail($user));
Mail::queue(new WelcomeMail($user));
```

---

## Events

```php
// Fire
event(new UserRegistered($user));

// Listen
Event::listen(UserRegistered::class, function (UserRegistered $event) {
    Log::info('User registered: ' . $event->user->email);
});
```

---

## Console (CLI)

```bash
php kyqo list                       # list all commands
php kyqo make:controller PostController
php kyqo make:model Post --migration
php kyqo make:job SendWelcomeEmail
php kyqo migrate
php kyqo migrate:rollback
php kyqo cache:clear
php kyqo key:generate
php kyqo serve --port=8000
php kyqo queue:work
php kyqo queue:failed
php kyqo queue:retry all
```

---

## Facades

```php
use Kyqo\Core\Support\Facades\{App, Cache, Config, Event, Hash, Log, Storage};

Config::get('app.name');
Log::info('Hello world');
Hash::make('password');
Storage::put('file.txt', 'content');
```

---

## Helpers

```php
env('APP_KEY')             // read environment variable
app()                      // Application instance
config('app.name')         // read config
route_url('profile')       // generate named route URL
view('home', $data)        // render a view
auth()                     // AuthManager
session()                  // Session manager
cache()                    // Cache manager
event(...)                 // fire an event
abort(403, 'Forbidden')    // throw HttpException
redirect('/url')           // redirect response
response('body', 200)      // create Response
base_path('app')           // absolute path from project root
storage_path('logs')       // storage/ path
```

---

## Testing

```bash
composer test
# or
./vendor/bin/phpunit
```

---

## License

Kyqo is open-sourced software licensed under the [MIT license](LICENSE).
