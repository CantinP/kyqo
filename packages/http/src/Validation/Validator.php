<?php

namespace Kyqo\Http\Validation;

/**
 * Kyqo Validator
 *
 * Validates an array of data against a set of rules.
 *
 * Supported rules (pipe-separated):
 *   required, nullable, string, numeric, integer, boolean, array,
 *   email, url, alpha, alpha_num, alpha_dash,
 *   min:{n}, max:{n}, between:{min},{max},
 *   in:{a,b,c}, not_in:{a,b,c},
 *   size:{n} (string length or array count),
 *   regex:{pattern},
 *   confirmed (field must have a matching field_confirmation),
 *   same:{other_field}, different:{other_field},
 *   date, date_format:{format},
 *   exists:{table},{column} (requires DB resolver callback),
 *   unique:{table},{column} (requires DB resolver callback)
 *
 * FIX AUDIT-1: Unknown rules now throw \InvalidArgumentException
 *              instead of silently passing.
 *
 * Usage:
 *   $v = new Validator($data, ['email' => 'required|email', 'age' => 'required|integer|min:18']);
 *   if ($v->fails()) { $errors = $v->errors(); }
 *   $v->validate(); // throws ValidationException on failure
 */
class Validator
{
    protected array $errors  = [];
    protected array $data;
    protected array $rules;
    protected array $messages;
    protected ?\Closure $dbResolver = null;

    /**
     * Rules that the validator recognises natively.
     * Any other rule name will throw an \InvalidArgumentException.
     */
    private const KNOWN_RULES = [
        'required', 'nullable', 'string', 'numeric', 'integer', 'boolean', 'array',
        'email', 'url', 'alpha', 'alpha_num', 'alpha_dash',
        'min', 'max', 'between', 'size',
        'in', 'not_in', 'regex',
        'confirmed', 'same', 'different',
        'date', 'date_format',
        'exists', 'unique',
    ];

    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data     = $data;
        $this->rules    = $rules;
        $this->messages = $messages;

        $this->assertKnownRules();
    }

    public function setDbResolver(\Closure $resolver): static
    {
        $this->dbResolver = $resolver;
        return $this;
    }

    // -------------------------------------------------------------------------

    public function passes(): bool
    {
        $this->errors = [];
        foreach ($this->rules as $field => $ruleString) {
            $rules    = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value    = $this->getValue($field);
            $nullable = in_array('nullable', $rules, true);
            $present  = $this->isPresent($field);

            foreach ($rules as $rule) {
                $rule = trim($rule);
                if ($rule === 'nullable') continue;

                // Skip all non-required rules for null/absent nullable fields
                if ($nullable && !$present && $rule !== 'required') continue;
                if ($nullable && $present && $value === null && $rule !== 'required') continue;

                $this->applyRule($field, $value, $rule);
            }
        }
        return empty($this->errors);
    }

    public function fails(): bool  { return !$this->passes(); }

    public function errors(): array { return $this->errors; }

    /**
     * Run validation and throw ValidationException if it fails.
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }
        return $this->validated();
    }

    /**
     * Return only the validated fields (fields present in rules).
     */
    public function validated(): array
    {
        $result = [];
        foreach (array_keys($this->rules) as $field) {
            if ($this->isPresent($field)) {
                $result[$field] = $this->getValue($field);
            }
        }
        return $result;
    }

    // -------------------------------------------------------------------------

    protected function applyRule(string $field, mixed $value, string $rule): void
    {
        [$ruleName, $param] = $this->parseRule($rule);

        $passed = match ($ruleName) {
            'required'     => $this->isPresent($field) && $value !== null && $value !== '',
            'string'       => is_string($value),
            'numeric'      => is_numeric($value),
            'integer'      => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'boolean'      => is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true),
            'array'        => is_array($value),
            'email'        => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url'          => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
            'alpha'        => is_string($value) && ctype_alpha($value),
            'alpha_num'    => is_string($value) && ctype_alnum($value),
            'alpha_dash'   => is_string($value) && (bool) preg_match('/^[a-zA-Z0-9_\-]+$/', $value),
            'min'          => $this->validateMin($value, (float) $param),
            'max'          => $this->validateMax($value, (float) $param),
            'between'      => $this->validateBetween($value, $param),
            'size'         => $this->validateSize($value, (int) $param),
            'in'           => in_array((string) $value, explode(',', $param), true),
            'not_in'       => !in_array((string) $value, explode(',', $param), true),
            'regex'        => is_string($value) && (bool) preg_match($param, $value),
            'confirmed'    => $this->validateConfirmed($field, $value),
            'same'         => (string) $value === (string) $this->getValue($param),
            'different'    => (string) $value !== (string) $this->getValue($param),
            'date'         => (bool) strtotime((string) $value),
            'date_format'  => $this->validateDateFormat($value, $param),
            'exists'       => $this->validateExists($field, $value, $param),
            'unique'       => $this->validateUnique($field, $value, $param),
            // assertKnownRules() already rejected anything else at construction time.
            default        => throw new \LogicException("Rule [{$ruleName}] passed assertKnownRules() but has no handler."),
        };

        if (!$passed) {
            $this->addError($field, $ruleName, $param);
        }
    }

    protected function validateMin(mixed $value, float $min): bool
    {
        if (is_array($value))   return count($value) >= $min;
        if (is_numeric($value)) return (float) $value >= $min;
        return mb_strlen((string) $value) >= $min;
    }

    protected function validateMax(mixed $value, float $max): bool
    {
        if (is_array($value))   return count($value) <= $max;
        if (is_numeric($value)) return (float) $value <= $max;
        return mb_strlen((string) $value) <= $max;
    }

    protected function validateBetween(mixed $value, string $param): bool
    {
        [$min, $max] = explode(',', $param, 2);
        return $this->validateMin($value, (float) $min) && $this->validateMax($value, (float) $max);
    }

    protected function validateSize(mixed $value, int $size): bool
    {
        if (is_array($value))   return count($value) === $size;
        if (is_numeric($value)) return (float) $value === (float) $size;
        return mb_strlen((string) $value) === $size;
    }

    protected function validateConfirmed(string $field, mixed $value): bool
    {
        return $value === $this->getValue($field . '_confirmation');
    }

    protected function validateDateFormat(mixed $value, string $format): bool
    {
        $d = \DateTime::createFromFormat($format, (string) $value);
        return $d !== false && $d->format($format) === (string) $value;
    }

    protected function validateExists(string $field, mixed $value, string $param): bool
    {
        if ($this->dbResolver === null) return true;
        [$table, $column] = array_pad(explode(',', $param, 2), 2, $field);
        return (bool) ($this->dbResolver)($table, $column, $value, 'exists');
    }

    protected function validateUnique(string $field, mixed $value, string $param): bool
    {
        if ($this->dbResolver === null) return true;
        [$table, $column] = array_pad(explode(',', $param, 2), 2, $field);
        return !(bool) ($this->dbResolver)($table, $column, $value, 'unique');
    }

    // -------------------------------------------------------------------------

    /**
     * FIX AUDIT-1: Reject unknown rule names at construction time.
     *
     * Rules with parameters (e.g. "min:5", "regex:/foo/") are split on ":"
     * and only the rule name is checked.
     *
     * @throws \InvalidArgumentException
     */
    protected function assertKnownRules(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            foreach ($rules as $raw) {
                [$name] = $this->parseRule(trim($raw));
                if (!in_array($name, self::KNOWN_RULES, true)) {
                    throw new \InvalidArgumentException(
                        "Validator: unknown rule [{$name}] on field [{$field}]. "
                        . 'Check for typos. Known rules: ' . implode(', ', self::KNOWN_RULES) . '.'
                    );
                }
            }
        }
    }

    protected function parseRule(string $rule): array
    {
        $pos = strpos($rule, ':');
        if ($pos === false) return [$rule, ''];
        return [substr($rule, 0, $pos), substr($rule, $pos + 1)];
    }

    protected function isPresent(string $field): bool
    {
        return array_key_exists($field, $this->data);
    }

    protected function getValue(string $field): mixed
    {
        // Support dot-notation: 'address.city'
        $keys  = explode('.', $field);
        $value = $this->data;
        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }
        return $value;
    }

    protected function addError(string $field, string $rule, string $param): void
    {
        $msgKey = "{$field}.{$rule}";
        if (isset($this->messages[$msgKey])) {
            $this->errors[$field][] = $this->messages[$msgKey];
            return;
        }
        if (isset($this->messages[$field])) {
            $this->errors[$field][] = $this->messages[$field];
            return;
        }
        $this->errors[$field][] = $this->defaultMessage($field, $rule, $param);
    }

    protected function defaultMessage(string $field, string $rule, string $param): string
    {
        $label = str_replace('_', ' ', $field);
        return match ($rule) {
            'required'    => "The {$label} field is required.",
            'string'      => "The {$label} must be a string.",
            'numeric'     => "The {$label} must be a number.",
            'integer'     => "The {$label} must be an integer.",
            'boolean'     => "The {$label} must be true or false.",
            'array'       => "The {$label} must be an array.",
            'email'       => "The {$label} must be a valid email address.",
            'url'         => "The {$label} must be a valid URL.",
            'alpha'       => "The {$label} may only contain letters.",
            'alpha_num'   => "The {$label} may only contain letters and numbers.",
            'alpha_dash'  => "The {$label} may only contain letters, numbers, dashes and underscores.",
            'min'         => "The {$label} must be at least {$param}.",
            'max'         => "The {$label} may not be greater than {$param}.",
            'between'     => "The {$label} must be between {$param}.",
            'size'        => "The {$label} must be exactly {$param}.",
            'in'          => "The selected {$label} is invalid.",
            'not_in'      => "The selected {$label} is invalid.",
            'regex'       => "The {$label} format is invalid.",
            'confirmed'   => "The {$label} confirmation does not match.",
            'same'        => "The {$label} and {$param} must match.",
            'different'   => "The {$label} and {$param} must be different.",
            'date'        => "The {$label} is not a valid date.",
            'date_format' => "The {$label} does not match the format {$param}.",
            'exists'      => "The selected {$label} is invalid.",
            'unique'      => "The {$label} has already been taken.",
            default       => "The {$label} is invalid.",
        };
    }
}
