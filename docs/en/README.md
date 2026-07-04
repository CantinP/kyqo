# Kyqo Framework — Complete Documentation

> **Version:** 1.0 · **PHP:** 8.2+ · **License:** MIT

## Table of Contents

1. [Installation](#1-installation)
2. [Directory Structure](#2-directory-structure)
3. [Configuration](#3-configuration)
4. [Routing](#4-routing)
5. [Controllers](#5-controllers)
6. [Middleware](#6-middleware)
7. [Request & Response](#7-request--response)
8. [ORM & Database](#8-orm--database)
9. [Migrations & Schema](#9-migrations--schema)
10. [Validation](#10-validation)
11. [Authentication](#11-authentication)
12. [Views & Templates](#12-views--templates)
13. [Mail](#13-mail)
14. [Notifications](#14-notifications)
15. [Broadcasting](#15-broadcasting)
16. [WebSocket Server](#16-websocket-server)
17. [Queue & Jobs](#17-queue--jobs)
18. [Cache](#18-cache)
19. [Sessions](#19-sessions)
20. [Storage](#20-storage)
21. [Events & Listeners](#21-events--listeners)
22. [Scheduler](#22-scheduler)
23. [Internationalisation (i18n)](#23-internationalisation-i18n)
24. [Console Commands](#24-console-commands)
25. [Testing](#25-testing)
26. [Helpers Reference](#26-helpers-reference)

---

## 1. Installation

```bash
git clone https://github.com/CantinP/kyqo.git my-app
cd my-app
composer install
cp .env.example .env
# Edit .env with your database credentials
php kyqo migrate
php kyqo serve
```

Open `http://localhost:8000` in your browser.

---

## 2. Directory Structure

```
my-app/
├── app/
│   ├── Controllers/
│   ├── Models/
│   ├── Middleware/
│   ├── Jobs/
│   ├── Mail/
│   ├── Notifications/
│   └── Events/
├── bootstrap/
│   ├── app.php          # Application bootstrap
│   └── websocket.php    # WebSocket hooks
├── config/              # Configuration files
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── public/
│   └── index.php        # Entry point
├── resources/
│   ├── views/
│   └── lang/
│       ├── en/
│       └── fr/
├── routes/
│   ├── web.php
│   └── api.php
├── storage/
├── tests/
└── .env
```

---

## 3. Configuration

All configuration files live in `config/`. Values are read from `.env` via the `env()` helper.

```php
// config/database.php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => env('DB_HOST', '127.0.0.1'),
            'database' => env('DB_DATABASE', 'kyqo'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
        ],
        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => database_path('database.sqlite'),
        ],
    ],
];
```

Access config anywhere:

```php
config('database.default');         // 'mysql'
config('app.debug', false);         // with default
```

---

## 4. Routing

```php
// routes/web.php
use Kyqo\Http\Router\Router;

$router->get('/', [HomeController::class, 'index']);
$router->post('/posts', [PostController::class, 'store']);
$router->put('/posts/{id}', [PostController::class, 'update']);
$router->delete('/posts/{id}', [PostController::class, 'destroy']);

// Named routes
$router->get('/profile', [ProfileController::class, 'show'])->name('profile');

// Route groups
$router->group(['prefix' => '/admin', 'middleware' => ['auth']], function ($router) {
    $router->get('/dashboard', [AdminController::class, 'dashboard']);
});

// API routes (CSRF excluded automatically)
// routes/api.php
$router->get('/api/users', [UserController::class, 'index']);
```

**Route parameters:**

```php
$router->get('/users/{id}', function (Request $request, int $id) {
    return response()->json(User::find($id));
});
```

**Generate URLs:**

```php
route('profile');              // /profile
url('/about');                 // https://example.com/about
```

---

## 5. Controllers

```php
// app/Controllers/PostController.php
namespace App\Controllers;

use Kyqo\Http\Request;
use Kyqo\Http\Response;
use App\Models\Post;

class PostController
{
    public function index(Request $request): Response
    {
        $posts = Post::with('author')->latest()->paginate(15);
        return view('posts.index', compact('posts'));
    }

    public function store(Request $request): Response
    {
        $data = $request->validate([
            'title'   => 'required|min:3|max:255',
            'content' => 'required|min:10',
        ]);

        $post = Post::create($data);

        return redirect(route('posts.show', ['id' => $post->id]));
    }
}
```

Generate a controller:

```bash
php kyqo make:controller PostController
```

---

## 6. Middleware

**Creating middleware:**

```php
// app/Middleware/EnsureEmailVerified.php
namespace App\Middleware;

use Kyqo\Http\Request;

class EnsureEmailVerified
{
    public function handle(Request $request, \Closure $next): mixed
    {
        if (!auth()->user()?->email_verified_at) {
            return redirect('/verify-email');
        }
        return $next($request);
    }
}
```

**Registering in Kernel:**

```php
protected array $routeMiddleware = [
    'auth'     => \Kyqo\Auth\Middleware\Authenticate::class,
    'verified' => \App\Middleware\EnsureEmailVerified::class,
];
```

**Applying to routes:**

```php
$router->get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified']);
```

**CSRF** is applied globally to all non-GET web routes. API routes (`api/*`) and webhooks (`webhooks/*`) are automatically excluded.

---

## 7. Request & Response

```php
// Reading input
$request->input('name');           // from POST / JSON body
$request->get('page', 1);         // from query string, with default
$request->all();                   // all input
$request->only(['name', 'email']); // subset
$request->except(['password']);    // all except
$request->has('email');            // boolean
$request->file('avatar');          // uploaded file

// Request info
$request->method();    // GET, POST…
$request->uri();       // /posts/1
$request->isJson();    // true if Content-Type: application/json
$request->wantsJson(); // true if Accept: application/json
$request->ip();
$request->header('Authorization');
```

```php
// Responses
return response('Hello', 200);
return response()->json(['key' => 'value']);
return redirect('/home');
return redirect()->back();
return view('welcome', ['name' => 'Alice']);
```

---

## 8. ORM & Database

### Models

```php
// app/Models/Post.php
namespace App\Models;

use Kyqo\Database\Orm\Model;

class Post extends Model
{
    protected string $table      = 'posts';
    protected array  $fillable   = ['title', 'content', 'user_id'];
    protected array  $casts      = ['published_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }

    // Polymorphic
    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }
}
```

```bash
php kyqo make:model Post
```

### Querying

```php
// Basic CRUD
$post  = Post::find(1);
$posts = Post::all();
$posts = Post::where('published', true)->orderBy('created_at', 'desc')->get();
$post  = Post::create(['title' => 'Hello', 'content' => '...']);
$post->update(['title' => 'Updated']);
$post->delete();

// Eager loading
$posts = Post::with('author', 'comments')->get();
$posts = Post::withCount('comments')->get();

// Scopes
class Post extends Model {
    public function scopePublished($query) {
        return $query->where('published', true);
    }
}
Post::published()->get();

// Pagination
$posts = Post::paginate(15);    // page from ?page=
$posts = Post::paginate(15, 2); // page 2
```

### Raw Query Builder

```php
$users = app('db')->table('users')
    ->select('id', 'name', 'email')
    ->where('active', '=', 1)
    ->orderBy('name')
    ->limit(10)
    ->get();

// Aggregates
$count = app('db')->table('orders')->where('status', '=', 'paid')->count();
$total = app('db')->table('orders')->sum('amount');

// Joins
$data = app('db')->table('posts')
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->select('posts.title', 'users.name')
    ->get();
```

### Polymorphic Relations

```php
// A Comment can belong to a Post or a Video
class Comment extends Model
{
    public function commentable(): MorphTo
    {
        return $this->morphTo(); // uses commentable_type + commentable_id
    }
}

$comment->commentable; // returns Post or Video instance
```

### SQLite

```php
// config/database.php
'sqlite' => [
    'driver'   => 'sqlite',
    'database' => database_path('database.sqlite'),
],
```

The framework auto-selects `SqliteGrammar` (AUTOINCREMENT, `datetime('now')`, no FOR UPDATE).

---

## 9. Migrations & Schema

```bash
php kyqo make:migration create_posts_table
php kyqo migrate
php kyqo migrate:rollback
```

```php
// database/migrations/2024_01_01_000000_create_posts_table.php
return new class {
    public function up(\Kyqo\Database\Connection $db): void
    {
        $db->getSchema()->create('posts', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('content');
            $t->foreignId('user_id')->constrained();
            $t->boolean('published')->default(false);
            $t->timestamps();
        });
    }

    public function down(\Kyqo\Database\Connection $db): void
    {
        $db->getSchema()->drop('posts');
    }
};
```

**Column types:** `id()`, `string()`, `text()`, `integer()`, `bigInteger()`, `boolean()`, `decimal()`, `float()`, `date()`, `dateTime()`, `timestamp()`, `timestamps()`, `softDeletes()`, `json()`, `foreignId()`.

---

## 10. Validation

```php
$data = $request->validate([
    'name'     => 'required|string|min:2|max:100',
    'email'    => 'required|email|unique:users,email',
    'password' => 'required|min:8|confirmed',
    'age'      => 'nullable|integer|min:18',
    'avatar'   => 'nullable|file|mimes:jpg,png|max:2048',
    'role'     => 'required|in:admin,editor,viewer',
]);
// $data contains only validated fields
// Throws ValidationException (422) on failure
```

**Available rules:** `required`, `nullable`, `string`, `integer`, `numeric`, `boolean`, `array`, `email`, `url`, `min`, `max`, `between`, `in`, `not_in`, `regex`, `confirmed`, `unique`, `exists`, `date`, `before`, `after`, `file`, `mimes`, `image`, `size`, `digits`, `alpha`, `alpha_num`, `json`.

---

## 11. Authentication

```php
// Login
if (auth()->attempt(['email' => $email, 'password' => $password])) {
    return redirect('/dashboard');
}

// Check
auth()->check();    // bool
auth()->user();     // User model or null
auth()->id();       // int or null

// Logout
auth()->logout();

// Token guard (API)
auth('token')->user();
```

**Protecting routes:**

```php
$router->get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth');
```

---

## 12. Views & Templates

```php
// resources/views/posts/show.kyqo.php
@extends('layouts.app')

@section('title', $post->title)

@section('content')
    <h1><?= e($post->title) ?></h1>
    <p><?= e($post->content) ?></p>
    @include('partials.comments', ['comments' => $post->comments])
@endsection
```

```php
// resources/views/layouts/app.kyqo.php
<!DOCTYPE html>
<html>
<head><title>@yield('title')</title></head>
<body>
    @yield('content')
</body>
</html>
```

```php
// In controller
return view('posts.show', compact('post'));
```

**Pagination links:**

```php
<?= $posts->links() ?>
```

---

## 13. Mail

```bash
php kyqo make:mail InvoicePaid
```

```php
// app/Mail/InvoicePaid.php
class InvoicePaid extends Mailable
{
    public function __construct(public Invoice $invoice) {}

    public function build(): static
    {
        return $this
            ->subject('Your invoice #' . $this->invoice->number)
            ->view('emails.invoice-paid')
            ->attach(storage_path('invoices/' . $this->invoice->id . '.pdf'));
    }
}

// Send
Mail::to($user->email)->send(new InvoicePaid($invoice));
Mail::to($user)->cc('boss@co.com')->send(new InvoicePaid($invoice));
```

**.env:**

```
MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=your_user
MAIL_PASSWORD=your_pass
MAIL_FROM_ADDRESS=hello@example.com
```

---

## 14. Notifications

```bash
php kyqo make:notification InvoicePaid
```

```php
// app/Notifications/InvoicePaid.php
class InvoicePaid extends Notification
{
    public function __construct(public Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invoice Paid')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Invoice #' . $this->invoice->number . ' has been paid.')
            ->action('View Invoice', url('/invoices/' . $this->invoice->id))
            ->success();
    }

    public function toArray(object $notifiable): array
    {
        return ['invoice_id' => $this->invoice->id];
    }
}
```

**Sending:**

```php
// Via trait
$user->notify(new InvoicePaid($invoice));

// Via helper
notify($user, new InvoicePaid($invoice));
```

**Add `Notifiable` to your User model:**

```php
use Kyqo\Notifications\Notifiable;

class User extends Model
{
    use Notifiable;
}

// Read notifications
$user->notifications();       // all
$user->unreadNotifications(); // unread only
$user->markNotificationsAsRead();
```

**Slack notifications:**

```php
public function via($notifiable): array { return ['slack']; }

public function toSlack($notifiable): SlackMessage
{
    return (new SlackMessage)
        ->content('Invoice #' . $this->invoice->number . ' paid!')
        ->success();
}
```

---

## 15. Broadcasting

```php
// app/Events/OrderShipped.php
use Kyqo\Broadcasting\ShouldBroadcast;
use Kyqo\Broadcasting\Channel;

class OrderShipped implements ShouldBroadcast
{
    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new Channel('orders')];
    }

    public function broadcastWith(): array
    {
        return ['order_id' => $this->order->id, 'status' => $this->order->status];
    }
}

// Broadcast
broadcast(new OrderShipped($order));
```

**.env:**

```
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_id
PUSHER_APP_KEY=your_key
PUSHER_APP_SECRET=your_secret
PUSHER_APP_CLUSTER=eu
```

Drivers: `pusher` (no SDK, Soketi-compatible), `log`, `null`.

---

## 16. WebSocket Server

Kyqo includes a native PHP WebSocket server — no Ratchet or Swoole required.

```bash
php kyqo ws:serve
php kyqo ws:serve --host=0.0.0.0 --port=8080
```

**JavaScript client:**

```js
const ws = new WebSocket('ws://localhost:8080');

ws.onopen = () => {
    // Subscribe to a channel
    ws.send(JSON.stringify({ action: 'subscribe', channel: 'chat' }));
};

ws.onmessage = (e) => {
    const msg = JSON.parse(e.data);
    if (msg.event === 'message') {
        console.log(msg.data);
    }
};

// Send a message
ws.send(JSON.stringify({ action: 'message', channel: 'chat', data: { text: 'Hello!' } }));
```

**Customise server behaviour in `bootstrap/websocket.php`:**

```php
return function (WsServer $server, Application $app): void {
    $server->onConnect(function (int $clientId) use ($server) {
        $server->sendTo($clientId, ['event' => 'welcome']);
    });

    $server->onMessage(function (string $channel, array $data, int $clientId) use ($server) {
        // Broadcast to all subscribers except sender
        $server->publish($channel, $data, $clientId);
    });

    $server->onDisconnect(function (int $clientId) {
        logger('Client ' . $clientId . ' disconnected');
    });
};
```

**Server API:**

| Method | Description |
|---|---|
| `publish($channel, $data, $excludeId)` | Broadcast to channel subscribers |
| `sendTo($clientId, $payload)` | Send to a specific client |
| `broadcast($payload)` | Send to ALL connected clients |
| `getClientCount()` | Number of connected clients |
| `stop()` | Gracefully stop the server |

> For `wss://` (TLS), place Nginx or Caddy as a reverse proxy in front.

---

## 17. Queue & Jobs

```bash
php kyqo make:job SendWelcomeEmail
```

```php
// app/Jobs/SendWelcomeEmail.php
class SendWelcomeEmail implements ShouldQueue
{
    public function __construct(public User $user) {}

    public function handle(): void
    {
        Mail::to($this->user->email)->send(new WelcomeMail($this->user));
    }
}

// Dispatch
SendWelcomeEmail::dispatch($user);
SendWelcomeEmail::dispatchAfter(60, $user); // delay 60s
```

**Worker:**

```bash
php kyqo queue:work
php kyqo queue:work --queue=emails --tries=3
php kyqo queue:failed
php kyqo queue:retry all
```

**.env:** `QUEUE_DRIVER=sync|database|redis`

---

## 18. Cache

```php
cache()->put('key', $value, ttl: 3600);
$value = cache()->get('key', 'default');
cache()->forget('key');
cache()->flush();

// Remember pattern
$users = cache()->remember('all-users', 3600, fn () => User::all());

// Forever
cache()->forever('settings', $settings);
```

**.env:** `CACHE_DRIVER=array|file|redis`

---

## 19. Sessions

```php
session()->put('cart', $items);
$cart = session()->get('cart', []);
session()->forget('cart');
session()->flush(); // clear all

session()->flash('success', 'Saved!');
$msg = session()->get('success');
```

**.env:** `SESSION_DRIVER=file|database|redis`

---

## 20. Storage

```php
use Kyqo\Storage\Storage;

// Write
Storage::disk('local')->put('reports/jan.csv', $content);

// Read
$content = Storage::disk('local')->get('reports/jan.csv');

// Check & delete
Storage::disk('local')->exists('reports/jan.csv');
Storage::disk('local')->delete('reports/jan.csv');

// URL
$url = Storage::disk('local')->url('reports/jan.csv');

// File upload
$path = $request->file('avatar')->store('avatars');
```

---

## 21. Events & Listeners

```bash
php kyqo make:event UserRegistered
php kyqo make:listener SendWelcomeEmail --event=UserRegistered
```

```php
// Dispatch
event(new UserRegistered($user));

// Listener
class SendWelcomeEmail
{
    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user->email)->send(new WelcomeMail($event->user));
    }
}
```

Register in a Service Provider:

```php
$events->listen(UserRegistered::class, SendWelcomeEmail::class);
```

---

## 22. Scheduler

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('reports:daily')->dailyAt('06:00');
    $schedule->command('cache:clear')->weekly();
    $schedule->call(fn () => logger('tick'))->everyMinute();
}
```

Add to crontab (runs every minute):

```
* * * * * php /path/to/app/kyqo schedule:run >> /dev/null 2>&1
```

---

## 23. Internationalisation (i18n)

**Language files** live in `resources/lang/{locale}/{file}.php`.

```php
// resources/lang/fr/messages.php
return [
    'welcome'  => 'Bienvenue, :name !',
    'apples'   => '{0} Pas de pomme|{1} Une pomme|[2,*] :count pommes',
    'files'    => '{count, plural, =0{Aucun fichier} one{# fichier} other{# fichiers}}',
];
```

**Helpers:**

```php
trans('messages.welcome', ['name' => 'Alice']);   // 'Bienvenue, Alice !'
__('messages.welcome', ['name' => 'Alice']);       // alias
lang('messages.welcome', ['name' => 'Alice']);     // alias

// Pluralisation — three syntaxes
trans_choice('messages.apples', 0);  // 'Pas de pomme'
trans_choice('messages.apples', 5);  // '5 pommes'
trans_choice('messages.files', 1);   // '1 fichier'
choice('messages.files', 42);        // '42 fichiers'
```

**Pluralisation syntaxes:**

| Syntax | Example |
|---|---|
| Pipe with exact counts | `{0} None\|{1} One\|[2,*] :count items` |
| Pipe two-form | `:count item\|:count items` |
| ICU-style | `{count, plural, =0{None} one{# item} other{# items}}` |

**Generate language files:**

```bash
php kyqo make:lang fr
```

**Locale detection** — `LocaleMiddleware` checks (in order): `?lang=`, session, `Accept-Language` header, config default.

---

## 24. Console Commands

```bash
# Database
php kyqo migrate
php kyqo migrate:rollback
php kyqo db:seed
php kyqo db:seed --class=UserSeeder

# Generators
php kyqo make:controller PostController
php kyqo make:model Post
php kyqo make:migration create_posts_table
php kyqo make:seeder UserSeeder
php kyqo make:factory PostFactory
php kyqo make:job SendEmail
php kyqo make:mail WelcomeMail
php kyqo make:notification InvoicePaid
php kyqo make:event UserRegistered
php kyqo make:listener SendWelcome --event=UserRegistered
php kyqo make:lang fr

# Queue
php kyqo queue:work
php kyqo queue:failed
php kyqo queue:retry all

# Scheduler
php kyqo schedule:run

# WebSocket
php kyqo ws:serve
php kyqo ws:serve --host=0.0.0.0 --port=8080

# Development
php kyqo serve
php kyqo serve --port=9000
php kyqo cache:clear
php kyqo route:list
```

**Creating a custom command:**

```php
php kyqo make:command SendDigest
```

```php
class SendDigest extends Command
{
    protected string $signature   = 'digest:send {--force}';
    protected string $description = 'Send weekly email digest';

    protected function handle(): int
    {
        $this->info('Sending digest...');
        // ...
        $this->info('Done.');
        return self::SUCCESS;
    }
}
```

---

## 25. Testing

### HTTP Tests

```php
use Kyqo\Testing\TestCase;

class PostControllerTest extends TestCase
{
    public function test_can_list_posts(): void
    {
        $response = $this->get('/api/posts');
        $response->assertOk()
                 ->assertJsonCount(10, 'data');
    }

    public function test_authenticated_user_can_create_post(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/posts', [
                'title'   => 'My Post',
                'content' => 'Some content here',
            ]);

        $response->assertCreated()
                 ->assertJsonPath('title', 'My Post');
    }
}
```

**Available assertions:** `assertStatus()`, `assertOk()`, `assertCreated()`, `assertNotFound()`, `assertForbidden()`, `assertRedirect()`, `assertSee()`, `assertJson()`, `assertJsonPath()`, `assertJsonCount()`, `assertJsonMissing()`, `assertHeader()`, `assertCookie()`.

### Database Tests

```php
use Kyqo\Testing\DatabaseTestCase;

class UserTest extends DatabaseTestCase
{
    // SQLite :memory: is booted automatically
    // All migrations from database/migrations/ run before each test

    public function test_creates_user(): void
    {
        User::create(['name' => 'Alice', 'email' => 'alice@test.com']);

        $this->assertDatabaseHas('users', ['email' => 'alice@test.com']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_deletes_user(): void
    {
        $user = User::create(['name' => 'Bob', 'email' => 'bob@test.com']);
        $user->delete();

        $this->assertDatabaseMissing('users', ['email' => 'bob@test.com']);
    }
}
```

**Custom schema:**

```php
protected function migrateDatabase(): void
{
    $this->schema()->create('users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->timestamps();
    });
}
```

**Options:**

```php
// Share DB across all tests in the class (faster)
protected bool $refreshPerTest = false;

// Seed fixtures
protected function seedDatabase(): void
{
    User::create(['name' => 'Admin', 'email' => 'admin@test.com']);
}
```

**Run tests:**

```bash
./vendor/bin/phpunit
./vendor/bin/phpunit tests/Feature/PostTest.php
```

---

## 26. Helpers Reference

| Helper | Description |
|---|---|
| `app($abstract)` | Resolve from container |
| `env($key, $default)` | Read environment variable |
| `config($key, $default)` | Read configuration |
| `view($template, $data)` | Render a view |
| `redirect($url)` | HTTP redirect |
| `response($content, $status)` | Build a response |
| `request($key)` | Access current request |
| `auth()` | Auth manager |
| `session($key)` | Session store |
| `cache($key)` | Cache store |
| `url($path)` | Generate URL |
| `route($name, $params)` | Generate named route URL |
| `csrf_token()` | Get CSRF token |
| `csrf_field()` | Render CSRF hidden input |
| `method_field($method)` | Render method spoofing input |
| `trans($key, $replace)` | Translate |
| `__($key, $replace)` | Alias for `trans()` |
| `lang($key, $replace)` | Alias for `trans()` |
| `trans_choice($key, $n)` | Plural translation |
| `choice($key, $n)` | Alias for `trans_choice()` |
| `broadcast($event)` | Broadcast an event |
| `notify($notifiable, $n)` | Send a notification |
| `event($event)` | Dispatch an event |
| `now()` | Current `DateTimeImmutable` |
| `abort($code, $message)` | Throw HTTP exception |
| `logger($message)` | Write to log |
| `bcrypt($value)` | Hash a password |
| `collect($array)` | Create a Collection |
| `storage_path($path)` | Absolute storage path |
| `database_path($path)` | Absolute database path |
| `base_path($path)` | Application root path |
| `public_path($path)` | Public directory path |
| `old($key, $default)` | Previous input value |
| `blank($value)` | True if null/empty string/array |
| `filled($value)` | Inverse of `blank()` |
| `rescue($callback, $rescue)` | Execute with silent catch |
| `tap($value, $callback)` | Execute and return value |
| `value($value)` | Unwrap Closure or return value |
