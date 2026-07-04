<?php

namespace Kyqo\Http\Validation;

use Kyqo\Database\DatabaseManager;

/**
 * Validator Factory — registered as 'validator' in the container.
 *
 * Wires the DB resolver automatically when a DatabaseManager is available.
 */
class ValidatorFactory
{
    public function __construct(
        protected ?DatabaseManager $db = null
    ) {}

    public function make(array $data, array $rules, array $messages = []): Validator
    {
        $validator = new Validator($data, $rules, $messages);

        if ($this->db !== null) {
            $db = $this->db;
            $validator->setDbResolver(
                function (string $table, string $column, mixed $value, string $mode) use ($db) {
                    $row = $db->table($table)->where($column, $value)->first();
                    return $row !== null;
                }
            );
        }

        return $validator;
    }

    public function validate(array $data, array $rules, array $messages = []): array
    {
        return $this->make($data, $rules, $messages)->validate();
    }
}
