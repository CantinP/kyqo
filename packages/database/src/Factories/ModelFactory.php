<?php

namespace Kyqo\Database\Factories;

/**
 * ModelFactory — define and generate fake model data without external deps.
 *
 * Usage in a factory class:
 *
 *   class UserFactory extends ModelFactory
 *   {
 *       protected string $model = User::class;
 *
 *       public function definition(): array
 *       {
 *           return [
 *               'name'  => $this->fake()->name(),
 *               'email' => $this->fake()->email(),
 *           ];
 *       }
 *   }
 *
 *   // In tests / seeders:
 *   UserFactory::new()->create();           // persist 1
 *   UserFactory::new()->count(5)->create(); // persist 5
 *   UserFactory::new()->make();             // array only, no DB
 *   UserFactory::new()->state(['role' => 'admin'])->create();
 */
abstract class ModelFactory
{
    protected string $model = '';
    protected int    $count  = 1;
    protected array  $states = [];

    private static ?FakeData $fakeInstance = null;

    // ── Builder API ──────────────────────────────────────────────────────────

    public static function new(): static
    {
        return new static();
    }

    public function count(int $n): static
    {
        $clone        = clone $this;
        $clone->count = $n;
        return $clone;
    }

    public function state(array $attributes): static
    {
        $clone           = clone $this;
        $clone->states[] = $attributes;
        return $clone;
    }

    // ── Produce ─────────────────────────────────────────────────────────────

    /**
     * Return attribute arrays without persisting.
     */
    public function make(array $override = []): array|object
    {
        $results = [];
        for ($i = 0; $i < $this->count; $i++) {
            $results[] = $this->buildAttributes($override);
        }
        return $this->count === 1 ? $results[0] : $results;
    }

    /**
     * Create model(s) in the database.
     */
    public function create(array $override = []): object|array
    {
        if ($this->model === '') {
            throw new \LogicException(static::class . ' must define a $model property.');
        }

        $results = [];
        for ($i = 0; $i < $this->count; $i++) {
            $attrs   = $this->buildAttributes($override);
            $results[] = ($this->model)::create($attrs);
        }
        return $this->count === 1 ? $results[0] : $results;
    }

    // ── Definition ──────────────────────────────────────────────────────────

    /**
     * Return the default attribute map. Override in subclass.
     */
    abstract public function definition(): array;

    // ── Helpers ─────────────────────────────────────────────────────────────

    protected function buildAttributes(array $override = []): array
    {
        $attrs = $this->definition();
        foreach ($this->states as $state) {
            $attrs = array_merge($attrs, $state);
        }
        return array_merge($attrs, $override);
    }

    /**
     * Return a FakeData helper for generating realistic-looking values.
     */
    protected function fake(): FakeData
    {
        if (self::$fakeInstance === null) {
            self::$fakeInstance = new FakeData();
        }
        return self::$fakeInstance;
    }
}
