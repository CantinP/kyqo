# Kyqo Framework — Documentation Complète

> **Version :** 1.0 · **PHP :** 8.2+ · **Licence :** MIT

## Table des matières

1. [Installation](#1-installation)
2. [Structure des dossiers](#2-structure-des-dossiers)
3. [Configuration](#3-configuration)
4. [Routage](#4-routage)
5. [Contrôleurs](#5-contrôleurs)
6. [Middleware](#6-middleware)
7. [Requête & Réponse](#7-requête--réponse)
8. [ORM & Base de données](#8-orm--base-de-données)
9. [Migrations & Schéma](#9-migrations--schéma)
10. [Validation](#10-validation)
11. [Authentification](#11-authentification)
12. [Vues & Templates](#12-vues--templates)
13. [Mail](#13-mail)
14. [Notifications](#14-notifications)
15. [Broadcasting](#15-broadcasting)
16. [Serveur WebSocket](#16-serveur-websocket)
17. [Files d'attente & Jobs](#17-files-dattente--jobs)
18. [Cache](#18-cache)
19. [Sessions](#19-sessions)
20. [Stockage de fichiers](#20-stockage-de-fichiers)
21. [Événements & Listeners](#21-événements--listeners)
22. [Planificateur de tâches](#22-planificateur-de-tâches)
23. [Internationalisation (i18n)](#23-internationalisation-i18n)
24. [Commandes console](#24-commandes-console)
25. [Tests](#25-tests)
26. [Référence des helpers](#26-référence-des-helpers)

---

## 1. Installation

```bash
git clone https://github.com/CantinP/kyqo.git mon-app
cd mon-app
composer install
cp .env.example .env
# Remplir les identifiants de base de données dans .env
php kyqo migrate
php kyqo serve
```

Ouvrir `http://localhost:8000` dans le navigateur.

---

## 2. Structure des dossiers

```
mon-app/
├── app/
│   ├── Controllers/
│   ├── Models/
│   ├── Middleware/
│   ├── Jobs/
│   ├── Mail/
│   ├── Notifications/
│   └── Events/
├── bootstrap/
│   ├── app.php          # Démarrage de l'application
│   └── websocket.php    # Hooks WebSocket
├── config/              # Fichiers de configuration
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── public/
│   └── index.php        # Point d'entrée
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

Tous les fichiers de configuration se trouvent dans `config/`. Les valeurs sont lues depuis `.env` via le helper `env()`.

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

Accéder à la configuration depuis n'importe où :

```php
config('database.default');       // 'mysql'
config('app.debug', false);       // avec valeur par défaut
```

---

## 4. Routage

```php
// routes/web.php
use Kyqo\Http\Router\Router;

$router->get('/', [HomeController::class, 'index']);
$router->post('/articles', [ArticleController::class, 'store']);
$router->put('/articles/{id}', [ArticleController::class, 'update']);
$router->delete('/articles/{id}', [ArticleController::class, 'destroy']);

// Routes nommées
$router->get('/profil', [ProfilController::class, 'afficher'])->name('profil');

// Groupes de routes
$router->group(['prefix' => '/admin', 'middleware' => ['auth']], function ($router) {
    $router->get('/tableau-de-bord', [AdminController::class, 'dashboard']);
});

// Routes API (CSRF exclu automatiquement)
// routes/api.php
$router->get('/api/utilisateurs', [UtilisateurController::class, 'index']);
```

**Paramètres de route :**

```php
$router->get('/utilisateurs/{id}', function (Request $request, int $id) {
    return response()->json(User::find($id));
});
```

**Générer des URLs :**

```php
route('profil');         // /profil
url('/a-propos');        // https://example.com/a-propos
```

---

## 5. Contrôleurs

```php
// app/Controllers/ArticleController.php
namespace App\Controllers;

use Kyqo\Http\Request;
use Kyqo\Http\Response;
use App\Models\Article;

class ArticleController
{
    public function index(Request $request): Response
    {
        $articles = Article::with('auteur')->latest()->paginate(15);
        return view('articles.index', compact('articles'));
    }

    public function store(Request $request): Response
    {
        $donnees = $request->validate([
            'titre'   => 'required|min:3|max:255',
            'contenu' => 'required|min:10',
        ]);

        $article = Article::create($donnees);

        return redirect(route('articles.show', ['id' => $article->id]));
    }
}
```

Générer un contrôleur :

```bash
php kyqo make:controller ArticleController
```

---

## 6. Middleware

**Créer un middleware :**

```php
// app/Middleware/VerifierEmailConfirme.php
namespace App\Middleware;

use Kyqo\Http\Request;

class VerifierEmailConfirme
{
    public function handle(Request $request, \Closure $next): mixed
    {
        if (!auth()->user()?->email_verified_at) {
            return redirect('/verifier-email');
        }
        return $next($request);
    }
}
```

**Enregistrer dans le Kernel :**

```php
protected array $routeMiddleware = [
    'auth'    => \Kyqo\Auth\Middleware\Authenticate::class,
    'verifie' => \App\Middleware\VerifierEmailConfirme::class,
];
```

**Appliquer aux routes :**

```php
$router->get('/tableau-de-bord', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verifie']);
```

**CSRF** est appliqué globalement sur toutes les routes web non-GET. Les routes `api/*` et `webhooks/*` sont automatiquement exclues.

---

## 7. Requête & Réponse

```php
// Lire les données
$request->input('nom');            // depuis POST / corps JSON
$request->get('page', 1);         // depuis la query string, avec défaut
$request->all();                   // toutes les données
$request->only(['nom', 'email']); // sous-ensemble
$request->except(['mot_de_passe']); // tout sauf
$request->has('email');            // booléen
$request->file('avatar');          // fichier uploadé

// Informations sur la requête
$request->method();    // GET, POST…
$request->uri();       // /articles/1
$request->isJson();    // vrai si Content-Type: application/json
$request->wantsJson(); // vrai si Accept: application/json
$request->ip();
$request->header('Authorization');
```

```php
// Réponses
return response('Bonjour', 200);
return response()->json(['clef' => 'valeur']);
return redirect('/accueil');
return redirect()->back();
return view('bienvenue', ['nom' => 'Alice']);
```

---

## 8. ORM & Base de données

### Modèles

```php
// app/Models/Article.php
namespace App\Models;

use Kyqo\Database\Orm\Model;

class Article extends Model
{
    protected string $table    = 'articles';
    protected array  $fillable = ['titre', 'contenu', 'user_id'];
    protected array  $casts    = ['publie_le' => 'datetime'];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function commentaires(): HasMany
    {
        return $this->hasMany(Commentaire::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'article_tag');
    }

    // Polymorphique
    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }
}
```

```bash
php kyqo make:model Article
```

### Requêtes

```php
// CRUD de base
$article  = Article::find(1);
$articles = Article::all();
$articles = Article::where('publie', true)->orderBy('created_at', 'desc')->get();
$article  = Article::create(['titre' => 'Bonjour', 'contenu' => '...']);
$article->update(['titre' => 'Mis à jour']);
$article->delete();

// Chargement eager
$articles = Article::with('auteur', 'commentaires')->get();
$articles = Article::withCount('commentaires')->get();

// Scopes
class Article extends Model {
    public function scopePublie($query) {
        return $query->where('publie', true);
    }
}
Article::publie()->get();

// Pagination
$articles = Article::paginate(15);     // page depuis ?page=
$articles = Article::paginate(15, 2);  // page 2
```

### Query Builder direct

```php
$users = app('db')->table('users')
    ->select('id', 'nom', 'email')
    ->where('actif', '=', 1)
    ->orderBy('nom')
    ->limit(10)
    ->get();

// Agrégats
$count = app('db')->table('commandes')->where('statut', '=', 'payee')->count();
$total = app('db')->table('commandes')->sum('montant');
```

### Relations polymorphiques

```php
// Un Commentaire peut appartenir à un Article ou une Vidéo
class Commentaire extends Model
{
    public function commentable(): MorphTo
    {
        return $this->morphTo(); // utilise commentable_type + commentable_id
    }
}

$commentaire->commentable; // retourne une instance Article ou Video
```

### SQLite

```php
// config/database.php
'sqlite' => [
    'driver'   => 'sqlite',
    'database' => database_path('database.sqlite'),
],
```

Le framework sélectionne automatiquement `SqliteGrammar` (AUTOINCREMENT, `datetime('now')`, pas de FOR UPDATE).

---

## 9. Migrations & Schéma

```bash
php kyqo make:migration create_articles_table
php kyqo migrate
php kyqo migrate:rollback
```

```php
// database/migrations/2024_01_01_000000_create_articles_table.php
return new class {
    public function up(\Kyqo\Database\Connection $db): void
    {
        $db->getSchema()->create('articles', function (Blueprint $t) {
            $t->id();
            $t->string('titre');
            $t->text('contenu');
            $t->foreignId('user_id')->constrained();
            $t->boolean('publie')->default(false);
            $t->timestamps();
        });
    }

    public function down(\Kyqo\Database\Connection $db): void
    {
        $db->getSchema()->drop('articles');
    }
};
```

**Types de colonnes :** `id()`, `string()`, `text()`, `integer()`, `bigInteger()`, `boolean()`, `decimal()`, `float()`, `date()`, `dateTime()`, `timestamp()`, `timestamps()`, `softDeletes()`, `json()`, `foreignId()`.

---

## 10. Validation

```php
$donnees = $request->validate([
    'nom'           => 'required|string|min:2|max:100',
    'email'         => 'required|email|unique:users,email',
    'mot_de_passe'  => 'required|min:8|confirmed',
    'age'           => 'nullable|integer|min:18',
    'avatar'        => 'nullable|file|mimes:jpg,png|max:2048',
    'role'          => 'required|in:admin,editeur,lecteur',
]);
// $donnees contient uniquement les champs validés
// Lève une ValidationException (422) en cas d'échec
```

**Règles disponibles :** `required`, `nullable`, `string`, `integer`, `numeric`, `boolean`, `array`, `email`, `url`, `min`, `max`, `between`, `in`, `not_in`, `regex`, `confirmed`, `unique`, `exists`, `date`, `before`, `after`, `file`, `mimes`, `image`, `size`, `digits`, `alpha`, `alpha_num`, `json`.

---

## 11. Authentification

```php
// Connexion
if (auth()->attempt(['email' => $email, 'password' => $motDePasse])) {
    return redirect('/tableau-de-bord');
}

// Vérifications
auth()->check();    // bool
auth()->user();     // modèle User ou null
auth()->id();       // int ou null

// Déconnexion
auth()->logout();

// Guard token (API)
auth('token')->user();
```

**Protéger des routes :**

```php
$router->get('/tableau-de-bord', [DashboardController::class, 'index'])
    ->middleware('auth');
```

---

## 12. Vues & Templates

```php
// resources/views/articles/show.kyqo.php
@extends('layouts.app')

@section('titre', $article->titre)

@section('contenu')
    <h1><?= e($article->titre) ?></h1>
    <p><?= e($article->contenu) ?></p>
    @include('partials.commentaires', ['commentaires' => $article->commentaires])
@endsection
```

```php
// resources/views/layouts/app.kyqo.php
<!DOCTYPE html>
<html>
<head><title>@yield('titre')</title></head>
<body>
    @yield('contenu')
</body>
</html>
```

```php
// Dans le contrôleur
return view('articles.show', compact('article'));
```

**Liens de pagination :**

```php
<?= $articles->links() ?>
```

---

## 13. Mail

```bash
php kyqo make:mail FacturePaye
```

```php
// app/Mail/FacturePaye.php
class FacturePaye extends Mailable
{
    public function __construct(public Facture $facture) {}

    public function build(): static
    {
        return $this
            ->subject('Votre facture n°' . $this->facture->numero)
            ->view('emails.facture-payee')
            ->attach(storage_path('factures/' . $this->facture->id . '.pdf'));
    }
}

// Envoi
Mail::to($utilisateur->email)->send(new FacturePaye($facture));
Mail::to($utilisateur)->cc('boss@co.com')->send(new FacturePaye($facture));
```

**.env :**

```
MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=votre_utilisateur
MAIL_PASSWORD=votre_motdepasse
MAIL_FROM_ADDRESS=hello@example.com
```

---

## 14. Notifications

```bash
php kyqo make:notification FacturePaye
```

```php
// app/Notifications/FacturePaye.php
class FacturePaye extends Notification
{
    public function __construct(public Facture $facture) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Facture payée')
            ->greeting('Bonjour ' . $notifiable->name . ' !')
            ->line('La facture n°' . $this->facture->numero . ' a été réglée.')
            ->action('Voir la facture', url('/factures/' . $this->facture->id))
            ->success();
    }

    public function toArray(object $notifiable): array
    {
        return ['facture_id' => $this->facture->id];
    }
}
```

**Envoi :**

```php
// Via le trait
$utilisateur->notify(new FacturePaye($facture));

// Via le helper
notify($utilisateur, new FacturePaye($facture));
```

**Ajouter `Notifiable` au modèle User :**

```php
use Kyqo\Notifications\Notifiable;

class User extends Model
{
    use Notifiable;
}

// Lire les notifications
$utilisateur->notifications();        // toutes
$utilisateur->unreadNotifications();  // non lues
$utilisateur->markNotificationsAsRead();
```

**Notifications Slack :**

```php
public function via($notifiable): array { return ['slack']; }

public function toSlack($notifiable): SlackMessage
{
    return (new SlackMessage)
        ->content('Facture n°' . $this->facture->numero . ' payée !')
        ->success();
}
```

---

## 15. Broadcasting

```php
// app/Events/CommandeExpediee.php
use Kyqo\Broadcasting\ShouldBroadcast;
use Kyqo\Broadcasting\Channel;

class CommandeExpediee implements ShouldBroadcast
{
    public function __construct(public Commande $commande) {}

    public function broadcastOn(): array
    {
        return [new Channel('commandes')];
    }

    public function broadcastWith(): array
    {
        return ['commande_id' => $this->commande->id, 'statut' => $this->commande->statut];
    }
}

// Diffuser
broadcast(new CommandeExpediee($commande));
```

**.env :**

```
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=votre_id
PUSHER_APP_KEY=votre_clef
PUSHER_APP_SECRET=votre_secret
PUSHER_APP_CLUSTER=eu
```

Drivers : `pusher` (sans SDK, compatible Soketi), `log`, `null`.

---

## 16. Serveur WebSocket

Kyqo inclut un serveur WebSocket PHP natif — sans Ratchet ni Swoole.

```bash
php kyqo ws:serve
php kyqo ws:serve --host=0.0.0.0 --port=8080
```

**Client JavaScript :**

```js
const ws = new WebSocket('ws://localhost:8080');

ws.onopen = () => {
    // S'abonner à un canal
    ws.send(JSON.stringify({ action: 'subscribe', channel: 'chat' }));
};

ws.onmessage = (e) => {
    const msg = JSON.parse(e.data);
    if (msg.event === 'message') {
        console.log(msg.data);
    }
};

// Envoyer un message
ws.send(JSON.stringify({ action: 'message', channel: 'chat', data: { texte: 'Bonjour !' } }));
```

**Personnaliser le comportement dans `bootstrap/websocket.php` :**

```php
return function (WsServer $server, Application $app): void {
    $server->onConnect(function (int $clientId) use ($server) {
        $server->sendTo($clientId, ['event' => 'bienvenue']);
    });

    $server->onMessage(function (string $channel, array $data, int $clientId) use ($server) {
        // Rediffuser à tous les abonnés sauf l'expéditeur
        $server->publish($channel, $data, $clientId);
    });

    $server->onDisconnect(function (int $clientId) {
        logger('Client ' . $clientId . ' déconnecté');
    });
};
```

**API du serveur :**

| Méthode | Description |
|---|---|
| `publish($channel, $data, $excludeId)` | Diffuser aux abonnés du canal |
| `sendTo($clientId, $payload)` | Envoyer à un client spécifique |
| `broadcast($payload)` | Envoyer à TOUS les clients connectés |
| `getClientCount()` | Nombre de clients connectés |
| `stop()` | Arrêter proprement le serveur |

> Pour `wss://` (TLS), placer Nginx ou Caddy en reverse proxy devant le serveur.

---

## 17. Files d'attente & Jobs

```bash
php kyqo make:job EnvoyerEmailBienvenue
```

```php
// app/Jobs/EnvoyerEmailBienvenue.php
class EnvoyerEmailBienvenue implements ShouldQueue
{
    public function __construct(public User $utilisateur) {}

    public function handle(): void
    {
        Mail::to($this->utilisateur->email)->send(new BienvenueMail($this->utilisateur));
    }
}

// Dispatcher
EnvoyerEmailBienvenue::dispatch($utilisateur);
EnvoyerEmailBienvenue::dispatchAfter(60, $utilisateur); // délai 60s
```

**Worker :**

```bash
php kyqo queue:work
php kyqo queue:work --queue=emails --tries=3
php kyqo queue:failed
php kyqo queue:retry all
```

**.env :** `QUEUE_DRIVER=sync|database|redis`

---

## 18. Cache

```php
cache()->put('clef', $valeur, ttl: 3600);
$valeur = cache()->get('clef', 'defaut');
cache()->forget('clef');
cache()->flush();

// Pattern remember
$utilisateurs = cache()->remember('tous-les-utilisateurs', 3600, fn () => User::all());

// Permanent
cache()->forever('parametres', $parametres);
```

**.env :** `CACHE_DRIVER=array|file|redis`

---

## 19. Sessions

```php
session()->put('panier', $articles);
$panier = session()->get('panier', []);
session()->forget('panier');
session()->flush(); // vider tout

session()->flash('succes', 'Enregistré !');
$msg = session()->get('succes');
```

**.env :** `SESSION_DRIVER=file|database|redis`

---

## 20. Stockage de fichiers

```php
use Kyqo\Storage\Storage;

// Écrire
Storage::disk('local')->put('rapports/jan.csv', $contenu);

// Lire
$contenu = Storage::disk('local')->get('rapports/jan.csv');

// Vérifier & supprimer
Storage::disk('local')->exists('rapports/jan.csv');
Storage::disk('local')->delete('rapports/jan.csv');

// URL
$url = Storage::disk('local')->url('rapports/jan.csv');

// Upload de fichier
$chemin = $request->file('avatar')->store('avatars');
```

---

## 21. Événements & Listeners

```bash
php kyqo make:event UtilisateurInscrit
php kyqo make:listener EnvoyerEmailBienvenue --event=UtilisateurInscrit
```

```php
// Dispatcher
event(new UtilisateurInscrit($utilisateur));

// Listener
class EnvoyerEmailBienvenue
{
    public function handle(UtilisateurInscrit $event): void
    {
        Mail::to($event->utilisateur->email)->send(new BienvenueMail($event->utilisateur));
    }
}
```

Enregistrer dans un Service Provider :

```php
$events->listen(UtilisateurInscrit::class, EnvoyerEmailBienvenue::class);
```

---

## 22. Planificateur de tâches

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('rapports:quotidien')->dailyAt('06:00');
    $schedule->command('cache:clear')->weekly();
    $schedule->call(fn () => logger('tick'))->everyMinute();
}
```

Ajouter au crontab (s'exécute chaque minute) :

```
* * * * * php /chemin/vers/app/kyqo schedule:run >> /dev/null 2>&1
```

---

## 23. Internationalisation (i18n)

Les **fichiers de langue** se trouvent dans `resources/lang/{locale}/{fichier}.php`.

```php
// resources/lang/fr/messages.php
return [
    'bienvenue' => 'Bienvenue, :name !',
    'pommes'    => '{0} Pas de pomme|{1} Une pomme|[2,*] :count pommes',
    'fichiers'  => '{count, plural, =0{Aucun fichier} one{# fichier} other{# fichiers}}',
];
```

**Helpers :**

```php
trans('messages.bienvenue', ['name' => 'Alice']); // 'Bienvenue, Alice !'
__('messages.bienvenue', ['name' => 'Alice']);      // alias
lang('messages.bienvenue', ['name' => 'Alice']);    // alias

// Pluralisation — trois syntaxes
trans_choice('messages.pommes', 0);   // 'Pas de pomme'
trans_choice('messages.pommes', 5);   // '5 pommes'
trans_choice('messages.fichiers', 1); // '1 fichier'
choice('messages.fichiers', 42);      // '42 fichiers'
```

**Syntaxes de pluralisation :**

| Syntaxe | Exemple |
|---|---|
| Pipe avec comptages exacts | `{0} Aucun\|{1} Un\|[2,*] :count éléments` |
| Pipe deux formes | `:count élément\|:count éléments` |
| ICU-style | `{count, plural, =0{Aucun} one{# élément} other{# éléments}}` |

**Générer les fichiers de langue :**

```bash
php kyqo make:lang fr
```

**Détection automatique** — `LocaleMiddleware` vérifie dans l'ordre : `?lang=`, session, en-tête `Accept-Language`, défaut de la config.

---

## 24. Commandes console

```bash
# Base de données
php kyqo migrate
php kyqo migrate:rollback
php kyqo db:seed
php kyqo db:seed --class=UtilisateurSeeder

# Générateurs
php kyqo make:controller ArticleController
php kyqo make:model Article
php kyqo make:migration create_articles_table
php kyqo make:seeder UtilisateurSeeder
php kyqo make:factory ArticleFactory
php kyqo make:job EnvoyerEmail
php kyqo make:mail BienvenueMail
php kyqo make:notification FacturePaye
php kyqo make:event UtilisateurInscrit
php kyqo make:listener EnvoyerBienvenue --event=UtilisateurInscrit
php kyqo make:lang fr

# File d'attente
php kyqo queue:work
php kyqo queue:failed
php kyqo queue:retry all

# Planificateur
php kyqo schedule:run

# WebSocket
php kyqo ws:serve
php kyqo ws:serve --host=0.0.0.0 --port=8080

# Développement
php kyqo serve
php kyqo serve --port=9000
php kyqo cache:clear
php kyqo route:list
```

**Créer une commande personnalisée :**

```php
php kyqo make:command EnvoyerResume
```

```php
class EnvoyerResume extends Command
{
    protected string $signature   = 'resume:envoyer {--force}';
    protected string $description = 'Envoie le résumé hebdomadaire par email';

    protected function handle(): int
    {
        $this->info('Envoi en cours...');
        // ...
        $this->info('Terminé.');
        return self::SUCCESS;
    }
}
```

---

## 25. Tests

### Tests HTTP

```php
use Kyqo\Testing\TestCase;

class ArticleControllerTest extends TestCase
{
    public function test_liste_les_articles(): void
    {
        $response = $this->get('/api/articles');
        $response->assertOk()
                 ->assertJsonCount(10, 'data');
    }

    public function test_utilisateur_authentifie_peut_creer_article(): void
    {
        $utilisateur = User::factory()->create();

        $response = $this->actingAs($utilisateur)
            ->postJson('/api/articles', [
                'titre'   => 'Mon article',
                'contenu' => 'Un contenu suffisamment long',
            ]);

        $response->assertCreated()
                 ->assertJsonPath('titre', 'Mon article');
    }
}
```

**Assertions disponibles :** `assertStatus()`, `assertOk()`, `assertCreated()`, `assertNotFound()`, `assertForbidden()`, `assertRedirect()`, `assertSee()`, `assertJson()`, `assertJsonPath()`, `assertJsonCount()`, `assertJsonMissing()`, `assertHeader()`, `assertCookie()`.

### Tests de base de données

```php
use Kyqo\Testing\DatabaseTestCase;

class UtilisateurTest extends DatabaseTestCase
{
    // SQLite :memory: est démarré automatiquement
    // Toutes les migrations de database/migrations/ s'exécutent avant chaque test

    public function test_cree_un_utilisateur(): void
    {
        User::create(['name' => 'Alice', 'email' => 'alice@test.com']);

        $this->assertDatabaseHas('users', ['email' => 'alice@test.com']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_supprime_un_utilisateur(): void
    {
        $user = User::create(['name' => 'Bob', 'email' => 'bob@test.com']);
        $user->delete();

        $this->assertDatabaseMissing('users', ['email' => 'bob@test.com']);
    }
}
```

**Schéma personnalisé :**

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

**Options :**

```php
// Partager la BDD entre tous les tests de la classe (plus rapide)
protected bool $refreshPerTest = false;

// Insérer des données de test
protected function seedDatabase(): void
{
    User::create(['name' => 'Admin', 'email' => 'admin@test.com']);
}
```

**Lancer les tests :**

```bash
./vendor/bin/phpunit
./vendor/bin/phpunit tests/Feature/ArticleTest.php
```

---

## 26. Référence des helpers

| Helper | Description |
|---|---|
| `app($abstract)` | Résoudre depuis le conteneur |
| `env($clef, $defaut)` | Lire une variable d'environnement |
| `config($clef, $defaut)` | Lire la configuration |
| `view($template, $donnees)` | Afficher une vue |
| `redirect($url)` | Redirection HTTP |
| `response($contenu, $statut)` | Construire une réponse |
| `request($clef)` | Accéder à la requête courante |
| `auth()` | Gestionnaire d'authentification |
| `session($clef)` | Store de session |
| `cache($clef)` | Store de cache |
| `url($chemin)` | Générer une URL |
| `route($nom, $params)` | Générer l'URL d'une route nommée |
| `csrf_token()` | Obtenir le token CSRF |
| `csrf_field()` | Afficher le champ caché CSRF |
| `method_field($methode)` | Afficher le champ de méthode HTTP |
| `trans($clef, $replace)` | Traduire |
| `__($clef, $replace)` | Alias de `trans()` |
| `lang($clef, $replace)` | Alias de `trans()` |
| `trans_choice($clef, $n)` | Traduction avec pluriel |
| `choice($clef, $n)` | Alias de `trans_choice()` |
| `broadcast($event)` | Diffuser un événement |
| `notify($notifiable, $n)` | Envoyer une notification |
| `event($event)` | Dispatcher un événement |
| `now()` | `DateTimeImmutable` courant |
| `abort($code, $message)` | Lever une exception HTTP |
| `logger($message)` | Écrire dans le log |
| `bcrypt($valeur)` | Hacher un mot de passe |
| `collect($tableau)` | Créer une Collection |
| `storage_path($chemin)` | Chemin absolu du stockage |
| `database_path($chemin)` | Chemin absolu de la base de données |
| `base_path($chemin)` | Racine de l'application |
| `public_path($chemin)` | Dossier public |
| `old($clef, $defaut)` | Valeur de l'input précédent |
| `blank($valeur)` | Vrai si null/chaîne vide/tableau vide |
| `filled($valeur)` | Inverse de `blank()` |
| `rescue($callback, $rescue)` | Exécuter avec catch silencieux |
| `tap($valeur, $callback)` | Exécuter et retourner la valeur |
| `value($valeur)` | Dépaqueter une Closure ou retourner |
